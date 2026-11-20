<?php

namespace Aristonis\FilamentShortcutKeys;

use Aristonis\FilamentShortcutKeys\Caching\CachedResolver;
use Aristonis\FilamentShortcutKeys\Core\Contracts\MapRepository;
use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\Resolver;
use Aristonis\FilamentShortcutKeys\Core\Contracts\ShortcutSet;
use Aristonis\FilamentShortcutKeys\Core\Resolution\CompositeResolver;
use Aristonis\FilamentShortcutKeys\Core\Resolution\KeyReservations;
use Aristonis\FilamentShortcutKeys\Core\Resolution\ResolvedMap;
use Aristonis\FilamentShortcutKeys\Core\Resolution\ShortcutResolver;
use Aristonis\FilamentShortcutKeys\Core\Sets\CustomSet;
use Aristonis\FilamentShortcutKeys\Core\Sets\GlobalSet;
use Aristonis\FilamentShortcutKeys\Core\Sets\NavigationSet;
use Aristonis\FilamentShortcutKeys\Core\Sets\RowActionSet;
use Aristonis\FilamentShortcutKeys\Core\Sets\ShortcutSetRegistry;
use Aristonis\FilamentShortcutKeys\Core\Sets\TableSet;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Exceptions\InvalidModifierSchemeException;
use Aristonis\FilamentShortcutKeys\Filament\AnyTablePageContextProvider;
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
                [$panelWideSets, $pageSets, $reserved] = $this->assembleSets();

                // The navigation map is page-independent, so it is cached once and shared across every
                // page; the page's own sets are cheap and page-specific, so they are resolved fresh and
                // merged in.
                $resolver = new CompositeResolver(
                    $this->panelWideResolver($panelWideSets, $reserved),
                    $this->pageResolver($pageSets, $reserved),
                );
                $handlerBySet = $this->handlerBySet($panelWideSets, $pageSets);

                [$authType, $authId] = $this->owner(Filament::auth()->user());
                $navigation = app(NavigationProvider::class);
                $pageContext = new FilamentPageContextProvider($page);
                $panelId = $panel->getId();

                $map = $resolver->resolve($navigation, $pageContext, $panelId, $authType, $authId);

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
        $panelWideSets = $this->panelWideSets();
        $reserved = $this->keyReservations($panelWideSets, $this->builtInPageSets());
        [$authType, $authId] = $this->owner($user);

        return $this->panelWideResolver($panelWideSets, $reserved)
            ->resolve(app(NavigationProvider::class), new NullPageContextProvider, $panelId, $authType, $authId);
    }

    /**
     * Resolve the table keys and the row-action letters for the reference page. Both are the same on
     * every table in the panel, so they are resolved against a table with no page around it, through
     * the same sets, overlay and maps as a live page. The header-action set finds no actions there.
     */
    public function resolveTableMap(string $panelId, ?Authenticatable $user): ResolvedMap
    {
        [, $pageSets, $reserved] = $this->assembleSets();
        [$authType, $authId] = $this->owner($user);

        return $this->pageResolver($pageSets, $reserved)
            ->resolve(app(NavigationProvider::class), new AnyTablePageContextProvider, $panelId, $authType, $authId);
    }

    /**
     * Both registries and the keys reserved across them, built once per resolution and handed to every
     * step that needs them. Nothing is kept on the plugin: it is a singleton, so a cached copy would
     * outlive the request's locale and any config change.
     *
     * @return array{0: ShortcutSetRegistry, 1: ShortcutSetRegistry, 2: array<string, string[]>} panel-wide sets, page sets, reserved keys by pool
     */
    private function assembleSets(): array
    {
        $panelWideSets = $this->panelWideSets();
        $builtInPageSets = $this->builtInPageSets();
        $reserved = $this->keyReservations($panelWideSets, $builtInPageSets);

        return [$panelWideSets, $this->pageSets($panelWideSets, $builtInPageSets, $reserved), $reserved];
    }

    private function panelWideSets(): ShortcutSetRegistry
    {
        $sets = new ShortcutSetRegistry;
        $sets->register(new NavigationSet($this->configuredModifier('navigation')));
        $sets->register(new CustomSet($this->configuredModifier('custom')));

        return $sets;
    }

    /**
     * @param  array{GlobalSet, TableSet}  $builtInPageSets
     * @param  array<string, string[]>  $reserved
     */
    private function pageSets(ShortcutSetRegistry $panelWideSets, array $builtInPageSets, array $reserved): ShortcutSetRegistry
    {
        [$global, $table] = $builtInPageSets;
        $rowActionModifier = $this->configuredModifier('row-action');
        $pool = (new RowActionSet([], $rowActionModifier))->defaultModifier()->toString();

        // Row actions letter themselves before the resolver runs, so they also have to step around
        // letters a developer pinned in the overlay for another set in their pool; otherwise the
        // pinned shortcut is resolved first and the row action is dropped on every table page.
        $pinned = $this->pinnedCodes([...$panelWideSets->all(), ...$builtInPageSets], $pool, except: 'row-action');
        $unavailable = [...$reserved[$pool] ?? [], ...$pinned];

        $sets = new ShortcutSetRegistry;
        $sets->register($global);
        $sets->register($table);
        $sets->register(new RowActionSet($this->rowActions, $rowActionModifier, $unavailable));

        return $sets;
    }

    /** @return array{GlobalSet, TableSet} */
    private function builtInPageSets(): array
    {
        return [
            new GlobalSet($this->configuredModifier('global')),
            new TableSet($this->panelIsRightToLeft(), $this->configuredModifier('table')),
        ];
    }

    /**
     * Keys no letter may take, per pool, across both registries: the leader key belongs to the
     * panel-wide sets but has to stay out of the bare pool the page sets share.
     *
     * @param  array{GlobalSet, TableSet}  $builtInPageSets
     * @return array<string, string[]>
     */
    private function keyReservations(ShortcutSetRegistry $panelWideSets, array $builtInPageSets): array
    {
        $panelWide = $panelWideSets->all();

        // The two registries are lettered by separate resolvers, so a pool split between them would
        // hand out the same letter twice. Only the page sets that hand out letters can collide: the
        // table's keys are fixed and reserved for both resolvers.
        $panelWidePools = array_map(fn ($set) => $set->defaultModifier()->toString(), $panelWide);
        [$global] = $builtInPageSets;
        $lettered = [
            'global' => $global->defaultModifier()->toString(),
            'row-action' => (new RowActionSet([], $this->configuredModifier('row-action')))->defaultModifier()->toString(),
        ];
        foreach ($lettered as $set => $pool) {
            if (in_array($pool, $panelWidePools, true)) {
                throw InvalidModifierSchemeException::forSharedPool($set, $pool);
            }
        }

        return KeyReservations::for([...$panelWide, ...$builtInPageSets]);
    }

    /**
     * Every registered row action's key, in its pool. A row action is emitted only where a table has
     * it, so without this a header action would take that letter on one page and lose it on the next,
     * and a page that gains a relation manager would rekey its header actions. Claimed rather than
     * reserved, so an overlay pin can still take one of these keys.
     *
     * @return array<string, string[]>
     */
    private function claimedRowActionCodes(ShortcutSetRegistry $pageSets): array
    {
        $rowActions = $pageSets->get('row-action');

        if (! $rowActions instanceof RowActionSet) {
            return [];
        }

        $overlay = config('shortcut-keys.overlay', []);
        $codes = [];

        foreach ($rowActions->codes() as $name => $code) {
            $rule = $overlay['row-action:' . $name] ?? [];
            if ($rule['disabled'] ?? false) {
                continue;
            }

            // A pin moves the key; the automatic one is kept as well, which costs at most a letter and
            // stays right if the resolver turns the pin down.
            $codes[] = $code;
            $letter = $rule['letter'] ?? null;
            if (is_string($letter) && preg_match('/^[a-z]$/i', $letter)) {
                $codes[] = 'Key' . strtoupper($letter);
            }
        }

        return [$rowActions->defaultModifier()->toString() => array_values(array_unique($codes))];
    }

    /**
     * Letters the overlay pins onto other sets in a pool, as key codes.
     *
     * @param  ShortcutSet[]  $sets  every set whose pins count
     * @return string[]
     */
    private function pinnedCodes(array $sets, string $pool, string $except): array
    {
        $poolBySet = [];
        foreach ($sets as $set) {
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

    /** @param  array<string, string[]>  $reserved */
    private function pageResolver(ShortcutSetRegistry $pageSets, array $reserved): Resolver
    {
        return new ShortcutResolver(
            $pageSets,
            config('shortcut-keys.overlay', []),
            app(MapRepository::class),
            $reserved,
            $this->claimedRowActionCodes($pageSets),
        );
    }

    /** @param  array<string, string[]>  $reserved */
    private function panelWideResolver(ShortcutSetRegistry $panelWideSets, array $reserved): Resolver
    {
        $maps = app(MapRepository::class);
        $overlay = config('shortcut-keys.overlay', []);

        return new CachedResolver(
            new ShortcutResolver($panelWideSets, $overlay, $maps, $reserved),
            app(CacheRepository::class),
            $maps,
            ['overlay' => $overlay, 'modifiers' => config('shortcut-keys.modifiers', []), 'reserved' => $reserved],
            app()->getLocale(),
            config('shortcut-keys.cache.ttl'),
        );
    }

    /** @return array{0: string, 1: string} the user's morph class and auth id; both empty for a guest */
    private function owner(?Authenticatable $user): array
    {
        return [
            $user instanceof Model ? $user->getMorphClass() : '',
            (string) ($user?->getAuthIdentifier() ?? ''),
        ];
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
            $this->unverifiedRowActions($map, $pageContext),
        );

        return view('filament-shortcut-keys::overlay', [
            'groups' => $groups,
            'referenceUrl' => ShortcutReference::getUrl(),
        ])->render();
    }

    /**
     * Row actions the page could not check because they may live on a nested table, each with the
     * control the cheatsheet looks for before it shows the row.
     *
     * @return array<string, string> target identity => selector
     */
    private function unverifiedRowActions(ResolvedMap $map, PageContextProvider $pageContext): array
    {
        if (! $pageContext->hasNestedTables()) {
            return [];
        }

        $known = array_map(fn ($action) => 'row-action:' . $action->name, $pageContext->rowActions());
        $selectors = [];

        foreach ($map->groups() as $group) {
            if ($group->setKey !== 'row-action') {
                continue;
            }

            foreach ($group->bindings as $binding) {
                $identity = $binding->target->identity();

                if (! in_array($identity, $known, true)) {
                    $selectors[$identity] = FilamentPageContextProvider::rowActionSelector($binding->target->structureKey);
                }
            }
        }

        return $selectors;
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
