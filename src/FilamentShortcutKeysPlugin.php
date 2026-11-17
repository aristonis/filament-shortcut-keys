<?php

namespace Aristonis\FilamentShortcutKeys;

use Aristonis\FilamentShortcutKeys\Caching\CachedResolver;
use Aristonis\FilamentShortcutKeys\Core\Contracts\MapRepository;
use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\Resolver;
use Aristonis\FilamentShortcutKeys\Core\Resolution\CompositeResolver;
use Aristonis\FilamentShortcutKeys\Core\Resolution\KeyReservations;
use Aristonis\FilamentShortcutKeys\Core\Resolution\ResolvedMap;
use Aristonis\FilamentShortcutKeys\Core\Resolution\ShortcutResolver;
use Aristonis\FilamentShortcutKeys\Core\Sets\CustomSet;
use Aristonis\FilamentShortcutKeys\Core\Sets\GlobalSet;
use Aristonis\FilamentShortcutKeys\Core\Sets\NavigationSet;
use Aristonis\FilamentShortcutKeys\Core\Sets\PageSet;
use Aristonis\FilamentShortcutKeys\Core\Sets\RowActionSet;
use Aristonis\FilamentShortcutKeys\Core\Sets\ShortcutSetRegistry;
use Aristonis\FilamentShortcutKeys\Core\Sets\TableSet;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Exceptions\InvalidModifierSchemeException;
use Aristonis\FilamentShortcutKeys\Filament\ClientMapSerializer;
use Aristonis\FilamentShortcutKeys\Filament\FilamentPageContextProvider;
use Aristonis\FilamentShortcutKeys\Filament\NullPageContextProvider;
use Aristonis\FilamentShortcutKeys\Filament\OverlayCatalog;
use Aristonis\FilamentShortcutKeys\Filament\Pages\ManageShortcuts;
use Aristonis\FilamentShortcutKeys\Filament\Pages\ShortcutReference;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Assets\Js;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;
use Livewire\LivewireManager;

class FilamentShortcutKeysPlugin implements Plugin
{
    /** @var string[] custom row-action names bound to the focused table row, panel-wide */
    protected array $rowActions = [];

    public function getId(): string
    {
        return 'filament-shortcut-keys';
    }

    /**
     * Register custom row-action names (e.g. approve/reject). Each gets a stable shortcut letter,
     * active on any table that exposes a record action of that name.
     *
     * @param  string[]  $names
     */
    public function rowActions(array $names): static
    {
        $this->rowActions = $names;

        return $this;
    }

    /** @return string[] */
    public function getRowActions(): array
    {
        return $this->rowActions;
    }

    public function register(Panel $panel): void
    {
        $panel->pages([ShortcutReference::class, ManageShortcuts::class]);

        $panel->assets([
            Js::make('filament-shortcut-keys', __DIR__ . '/../resources/dist/filament-shortcut-keys.js'),
        ], 'filament-shortcut-keys');

        // PAGE_END is the only render hook that fires inside the page's Livewire component, so it is
        // the one place the current page (and its actions) can be read.
        $panel->renderHook(
            PanelsRenderHook::PAGE_END,
            function () use ($panel): string {
                $page = app(LivewireManager::class)->current();

                if ($page === null) {
                    return '';
                }

                // The sets are assembled here rather than at registration because the panel's text
                // direction depends on the request locale, which is only settled once the app's
                // locale middleware has run — long after plugins register.
                $panelWideSets = $this->panelWideSets();
                $pageSets = $this->pageSets();
                $resolver = $this->buildResolver($panelWideSets, $pageSets);
                $handlerBySet = $this->handlerBySet($panelWideSets, $pageSets);

                $user = Filament::auth()->user();
                $navigation = app(NavigationProvider::class);
                $pageContext = new FilamentPageContextProvider($page);
                $panelId = $panel->getId();

                $map = $resolver->resolve(
                    $navigation,
                    $pageContext,
                    $panelId,
                    $user instanceof Model ? $user->getMorphClass() : '',
                    (string) ($user?->getAuthIdentifier() ?? ''),
                );

                $payload = (new ClientMapSerializer($navigation, $pageContext, $handlerBySet))
                    ->serialize($map, $panelId);

                // Escape tags/quotes so a selector or route value can never break out of the script
                // block, independent of json_encode's default slash-escaping.
                $json = json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

                $script = '<script type="application/json" id="filament-shortcut-keys-map">'
                    . $json
                    . '</script>';

                return $script . $this->renderOverlay($map, $navigation, $pageContext, $panelId);
            },
        );
    }

    /**
     * Resolve just the panel-wide shortcuts (navigation and the user's custom bindings) for the
     * reference page, which lists them away from any live page. Built from the same sets, overlay,
     * locale, and ttl as the render hook, so both land on the same cache-fingerprinted entry and the
     * page can never disagree with the injected keymap.
     */
    public function resolvePanelWideMap(string $panelId, ?Authenticatable $user): ResolvedMap
    {
        return $this->panelWideResolver($this->panelWideSets())->resolve(
            app(NavigationProvider::class),
            new NullPageContextProvider,
            $panelId,
            $user instanceof Model ? $user->getMorphClass() : '',
            (string) ($user?->getAuthIdentifier() ?? ''),
        );
    }

    private function panelWideSets(): ShortcutSetRegistry
    {
        $sets = new ShortcutSetRegistry;
        $sets->register(new NavigationSet($this->configuredModifier('navigation')));
        $sets->register(new CustomSet($this->configuredModifier('custom')));

        return $sets;
    }

    private function pageSets(): ShortcutSetRegistry
    {
        [$global, $table, $page] = $this->builtInPageSets();
        $rowActionModifier = $this->configuredModifier('row-action');
        $pool = (new RowActionSet([], $rowActionModifier))->defaultModifier()->toString();

        // Row actions letter themselves before the resolver runs, so they also have to step around
        // letters a developer pinned in the overlay for another set in their pool; otherwise the
        // pinned shortcut is resolved first and the row action is dropped on every table page.
        $unavailable = [...$this->keyReservations()[$pool] ?? [], ...$this->pinnedCodes($pool, except: 'row-action')];

        $sets = new ShortcutSetRegistry;
        $sets->register($global);
        $sets->register($table);
        $sets->register(new RowActionSet($this->rowActions, $rowActionModifier, $unavailable));
        $sets->register($page);

        return $sets;
    }

    /** @return array{GlobalSet, TableSet, PageSet} */
    private function builtInPageSets(): array
    {
        return [
            new GlobalSet($this->configuredModifier('global')),
            new TableSet($this->panelIsRightToLeft(), $this->configuredModifier('table')),
            new PageSet($this->configuredModifier('page')),
        ];
    }

    /**
     * Keys no letter may take, per pool, across both registries: the leader key belongs to the
     * panel-wide sets but has to stay out of the bare pool the page sets share.
     *
     * @return array<string, string[]>
     */
    private function keyReservations(): array
    {
        $panelWide = $this->panelWideSets()->all();
        $page = $this->builtInPageSets();

        // The two registries are lettered by separate resolvers, so a pool split between them would
        // hand out the same letter twice. Only the page sets that hand out letters can collide: the
        // table's keys are fixed and reserved for both resolvers, and the page set emits nothing yet.
        $panelWidePools = array_map(fn ($set) => $set->defaultModifier()->toString(), $panelWide);
        [$global] = $page;
        $lettered = [
            'global' => $global->defaultModifier()->toString(),
            'row-action' => (new RowActionSet([], $this->configuredModifier('row-action')))->defaultModifier()->toString(),
        ];
        foreach ($lettered as $set => $pool) {
            if (in_array($pool, $panelWidePools, true)) {
                throw InvalidModifierSchemeException::forSharedPool($set, $pool);
            }
        }

        return KeyReservations::for([...$panelWide, ...$page]);
    }

    /**
     * Letters the overlay pins onto other sets in a pool, as key codes.
     *
     * @return string[]
     */
    private function pinnedCodes(string $pool, string $except): array
    {
        $poolBySet = [];
        foreach ([...$this->panelWideSets()->all(), ...$this->builtInPageSets()] as $set) {
            $poolBySet[$set->key()] = $set->defaultModifier()->toString();
        }

        $codes = [];
        foreach (config('shortcut-keys.overlay', []) as $identity => $rule) {
            $set = strstr((string) $identity, ':', true);
            $letter = ($rule['disabled'] ?? false) ? null : ($rule['letter'] ?? null);

            if ($set === false || $set === $except || ($poolBySet[$set] ?? null) !== $pool || ! is_string($letter)) {
                continue;
            }

            if (preg_match('/^[a-z]$/i', $letter)) {
                $codes[] = 'Key' . strtoupper($letter);
            }
        }

        return $codes;
    }

    /**
     * The modifier scheme a developer configured for a set, or null to keep the set's convention.
     * A set the config never mentions is left alone, so removing a key restores the default rather
     * than silently turning the shortcuts into bare letters.
     */
    private function configuredModifier(string $set): ?ModifierScheme
    {
        $modifiers = config('shortcut-keys.modifiers', []);

        return array_key_exists($set, $modifiers)
            ? ModifierScheme::fromTokens((array) $modifiers[$set])
            : null;
    }

    /** The same key Filament reads to set the layout's `dir`, so shortcuts and chrome agree on direction. */
    private function panelIsRightToLeft(): bool
    {
        return trans('filament-panels::layout.direction') === 'rtl';
    }

    /**
     * The navigation map is page-independent, so it is cached once and shared across every page; the
     * page's own sets are cheap and page-specific, so they are resolved fresh and merged in.
     */
    private function buildResolver(ShortcutSetRegistry $panelWideSets, ShortcutSetRegistry $pageSets): CompositeResolver
    {
        $overlay = config('shortcut-keys.overlay', []);

        return new CompositeResolver(
            $this->panelWideResolver($panelWideSets),
            new ShortcutResolver($pageSets, $overlay, app(MapRepository::class), $this->keyReservations()),
        );
    }

    private function panelWideResolver(ShortcutSetRegistry $panelWideSets): Resolver
    {
        $maps = app(MapRepository::class);
        $overlay = config('shortcut-keys.overlay', []);

        $reserved = $this->keyReservations();

        return new CachedResolver(
            new ShortcutResolver($panelWideSets, $overlay, $maps, $reserved),
            app(CacheRepository::class),
            $maps,
            ['overlay' => $overlay, 'modifiers' => config('shortcut-keys.modifiers', []), 'reserved' => $reserved],
            app()->getLocale(),
            config('shortcut-keys.cache.ttl'),
        );
    }

    /**
     * Maps each set key to the client handler that fires it, so the browser can route a group by its
     * handler rather than its name (a custom row-action set is fired by the table handler).
     *
     * @return array<string, string>
     */
    private function handlerBySet(ShortcutSetRegistry ...$registries): array
    {
        $handlers = [];
        foreach ($registries as $registry) {
            foreach ($registry->all() as $set) {
                $handlers[$set->key()] = $set->clientHandler();
            }
        }

        return $handlers;
    }

    private function renderOverlay(ResolvedMap $map, NavigationProvider $navigation, PageContextProvider $pageContext, string $panelId): string
    {
        $groups = OverlayCatalog::build(
            $map,
            $this->navigationLabels($navigation, $panelId),
            $this->actionLabels($pageContext),
            (array) trans('filament-shortcut-keys::shortcut-keys.table_behaviors'),
        );

        return view('filament-shortcut-keys::overlay', [
            'groups' => $groups,
            'referenceUrl' => ShortcutReference::getUrl(),
        ])->render();
    }

    /** @return array<string, string> */
    private function navigationLabels(NavigationProvider $navigation, string $panelId): array
    {
        $labels = [];
        foreach ($navigation->items($panelId) as $item) {
            $labels[$item->structureKey] = $item->label;
        }

        return $labels;
    }

    /** @return array<string, string> */
    private function actionLabels(PageContextProvider $pageContext): array
    {
        $labels = [];
        foreach ($pageContext->actions() as $action) {
            $labels['global:' . $action->name] = $action->label;
        }
        foreach ($pageContext->rowActions() as $action) {
            $labels['row-action:' . $action->name] = $action->label;
        }

        return $labels;
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }
}
