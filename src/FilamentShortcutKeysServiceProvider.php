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
use Aristonis\FilamentShortcutKeys\Filament\FilamentNavigationProvider;
use Aristonis\FilamentShortcutKeys\Persistence\EloquentMapCatalog;
use Aristonis\FilamentShortcutKeys\Persistence\EloquentMapRepository;
use Aristonis\FilamentShortcutKeys\Persistence\EloquentSystemMapAuthor;
use Aristonis\FilamentShortcutKeys\Testing\TestsFilamentShortcutKeys;
use Livewire\Features\SupportTesting\Testable;
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

        $package->hasMigrations([
            'create_shortcut_maps_table',
            'create_shortcut_map_entries_table',
            'create_shortcut_map_selections_table',
        ]);

        if (file_exists($package->basePath('/../resources/lang'))) {
            $package->hasTranslations();
        }

        if (file_exists($package->basePath('/../resources/views'))) {
            $package->hasViews(static::$viewNamespace);
        }
    }

    public function packageRegistered(): void
    {
        // The ports resolve to their Filament/Eloquent adapters here, so the plugin and resolvers
        // depend only on the contracts and a host app can swap any adapter. PageContextProvider is
        // left unbound because it is page-specific, built per render from the current page.
        $this->app->singleton(NavigationProvider::class, FilamentNavigationProvider::class);
        $this->app->singleton(MapRepository::class, EloquentMapRepository::class);
        // The read and the write side of a user's map are one Eloquent adapter, so share the instance.
        $this->app->singleton(MapEditor::class, fn ($app) => $app->make(MapRepository::class));
        $this->app->singleton(SystemMapAuthor::class, EloquentSystemMapAuthor::class);
        $this->app->singleton(AuthorityGate::class, ConfigAuthorityGate::class);

        // One catalog instance backs both the "what can I pick" and "make this active" ports.
        $this->app->singleton(EloquentMapCatalog::class);
        $this->app->singleton(ListMaps::class, fn ($app) => $app->make(EloquentMapCatalog::class));
        $this->app->singleton(MapSelector::class, fn ($app) => $app->make(EloquentMapCatalog::class));
    }

    public function packageBooted(): void
    {
        // Testing
        Testable::mixin(new TestsFilamentShortcutKeys);
    }
}
