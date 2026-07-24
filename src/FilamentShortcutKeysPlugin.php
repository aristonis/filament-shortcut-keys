<?php

namespace Aristonis\FilamentShortcutKeys;

use Aristonis\FilamentShortcutKeys\Caching\CachedResolver;
use Aristonis\FilamentShortcutKeys\Core\Contracts\MapRepository;
use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Resolution\CompositeResolver;
use Aristonis\FilamentShortcutKeys\Core\Resolution\ShortcutResolver;
use Aristonis\FilamentShortcutKeys\Core\Sets\GlobalSet;
use Aristonis\FilamentShortcutKeys\Core\Sets\NavigationSet;
use Aristonis\FilamentShortcutKeys\Core\Sets\PageSet;
use Aristonis\FilamentShortcutKeys\Core\Sets\RowActionSet;
use Aristonis\FilamentShortcutKeys\Core\Sets\ShortcutSetRegistry;
use Aristonis\FilamentShortcutKeys\Core\Sets\TableSet;
use Aristonis\FilamentShortcutKeys\Filament\FilamentPageContextProvider;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
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
        $resolver = $this->buildResolver();

        // PAGE_END is the only render hook that fires inside the page's Livewire component, so it is
        // the one place the current page (and its actions) can be read.
        $panel->renderHook(
            PanelsRenderHook::PAGE_END,
            function () use ($resolver, $panel): string {
                $page = app(LivewireManager::class)->current();

                if ($page === null) {
                    return '';
                }

                $user = Filament::auth()->user();

                $map = $resolver->resolve(
                    app(NavigationProvider::class),
                    new FilamentPageContextProvider($page),
                    $panel->getId(),
                    $user instanceof Model ? $user->getMorphClass() : '',
                    (string) ($user?->getAuthIdentifier() ?? ''),
                );

                return '<script type="application/json" id="filament-shortcut-keys-map">'
                    . json_encode($map->toArray())
                    . '</script>';
            },
        );
    }

    /**
     * The navigation map is page-independent, so it is cached once and shared across every page; the
     * page's own sets are cheap and page-specific, so they are resolved fresh and merged in.
     */
    private function buildResolver(): CompositeResolver
    {
        $maps = app(MapRepository::class);
        $overlay = config('shortcut-keys.overlay', []);

        $panelWideSets = new ShortcutSetRegistry;
        $panelWideSets->register(new NavigationSet);

        $pageSets = new ShortcutSetRegistry;
        $pageSets->register(new GlobalSet);
        $pageSets->register(new TableSet);
        $pageSets->register(new RowActionSet($this->rowActions));
        $pageSets->register(new PageSet);

        $panelWide = new CachedResolver(
            new ShortcutResolver($panelWideSets, $overlay, $maps),
            app(CacheRepository::class),
            $maps,
            $overlay,
            app()->getLocale(),
            config('shortcut-keys.cache.ttl'),
        );

        return new CompositeResolver($panelWide, new ShortcutResolver($pageSets, $overlay, $maps));
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
