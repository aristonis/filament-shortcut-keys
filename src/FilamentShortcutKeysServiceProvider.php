<?php

namespace Aristonis\FilamentShortcutKeys;

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
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name(static::$name);

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

    public function packageBooted(): void
    {
        // Testing
        Testable::mixin(new TestsFilamentShortcutKeys);
    }
}
