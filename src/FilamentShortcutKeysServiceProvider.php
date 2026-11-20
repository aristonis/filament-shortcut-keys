<?php

namespace Aristonis\FilamentShortcutKeys;

use Aristonis\FilamentShortcutKeys\Authorization\AuthorityGate;
use Aristonis\FilamentShortcutKeys\Authorization\ConfigAuthorityGate;
use Aristonis\FilamentShortcutKeys\Commands\PruneOrphanedEntriesCommand;
use Aristonis\FilamentShortcutKeys\Core\Contracts\ListMaps;
use Aristonis\FilamentShortcutKeys\Core\Contracts\MapEditor;
use Aristonis\FilamentShortcutKeys\Core\Contracts\MapRepository;
use Aristonis\FilamentShortcutKeys\Core\Contracts\MapSelector;
use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\SystemMapAuthor;
use Aristonis\FilamentShortcutKeys\Core\Enums\CustomizationMode;
use Aristonis\FilamentShortcutKeys\Filament\FilamentNavigationProvider;
use Aristonis\FilamentShortcutKeys\Persistence\ConfigOnlyAuthor;
use Aristonis\FilamentShortcutKeys\Persistence\ConfigOnlyStore;
use Aristonis\FilamentShortcutKeys\Persistence\EloquentMapCatalog;
use Aristonis\FilamentShortcutKeys\Persistence\EloquentMapRepository;
use Aristonis\FilamentShortcutKeys\Persistence\EloquentSystemMapAuthor;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentShortcutKeysServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-shortcut-keys';

    public static string $viewNamespace = 'filament-shortcut-keys';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name);

        $package->hasConfigFile('shortcut-keys');

        $package->hasCommand(PruneOrphanedEntriesCommand::class);

        // Prefixed so the filenames sort into dependency order: both child tables carry a foreign key
        // to shortcut_maps, and a plain `create_shortcut_map_entries_table` sorts BEFORE
        // `create_shortcut_maps_table`. Publishing strips the prefix and stamps its own, so a host's
        // migrations directory still gets clean names.
        $package->hasMigrations([
            '2026_01_01_000001_create_shortcut_maps_table',
            '2026_01_01_000002_create_shortcut_map_entries_table',
            '2026_01_01_000003_create_shortcut_map_selections_table',
        ]);

        $package->hasTranslations();

        $package->hasViews(static::$viewNamespace);
    }

    public function packageRegistered(): void
    {
        // The ports resolve to their Filament/Eloquent adapters here, so the plugin and resolvers
        // depend only on the contracts. Only the persistence and authorization bindings are meant to
        // be swapped by a host app; the resolution ports are @internal. PageContextProvider is left
        // unbound because it is page-specific, built per render from the current page.
        $this->app->singleton(NavigationProvider::class, FilamentNavigationProvider::class);
        $this->app->singleton(AuthorityGate::class, ConfigAuthorityGate::class);

        // In 'config' mode nothing is stored, so every persistence port resolves to a store that never
        // queries; the mode is read when a port is first resolved, after the app's config has loaded.
        $storesMaps = fn (): bool => CustomizationMode::current()->storesMaps();
        $this->app->singleton(ConfigOnlyStore::class);

        $this->app->singleton(MapRepository::class, fn ($app) => $storesMaps() ? $app->make(EloquentMapRepository::class) : $app->make(ConfigOnlyStore::class));
        // The read and the write side of a user's map are one adapter, so share the instance.
        $this->app->singleton(MapEditor::class, fn ($app) => $app->make(MapRepository::class));
        $this->app->singleton(SystemMapAuthor::class, fn ($app) => $storesMaps() ? $app->make(EloquentSystemMapAuthor::class) : new ConfigOnlyAuthor);

        // One catalog instance backs both the "what can I pick" and "make this active" ports.
        $this->app->singleton(EloquentMapCatalog::class);
        $this->app->singleton(ListMaps::class, fn ($app) => $storesMaps() ? $app->make(EloquentMapCatalog::class) : $app->make(ConfigOnlyStore::class));
        $this->app->singleton(MapSelector::class, fn ($app) => $storesMaps() ? $app->make(EloquentMapCatalog::class) : $app->make(ConfigOnlyStore::class));
    }
}
