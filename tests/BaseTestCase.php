<?php

namespace Aristonis\FilamentShortcutKeys\Tests;

use Aristonis\FilamentShortcutKeys\FilamentShortcutKeysServiceProvider;
use Aristonis\FilamentShortcutKeys\Tests\Support\Panel\AdminPanelProvider;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

/**
 * Panel + provider wiring shared by every test case, with no opinion on how the database is reset.
 *
 * The default TestCase wraps each test in a transaction, which the concurrency suite cannot use: a
 * second connection cannot see uncommitted rows, so a fork/lock race is unobservable from inside one.
 */
abstract class BaseTestCase extends Orchestra
{
    use WithWorkbench;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Aristonis\\FilamentShortcutKeys\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        $providers = [
            ActionsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentShortcutKeysServiceProvider::class,
        ];

        sort($providers);

        // Appended after sort, so the test admin panel registers once Filament's own
        // providers have booted (it depends on the Filament panel manager being available).
        $providers[] = AdminPanelProvider::class;

        return $providers;
    }

    public function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', static::databaseConnection());
        // Mounting Livewire/Filament pages boots a full request, which needs an app key.
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));

        if (static::databaseConnection() !== 'testing') {
            $app['config']->set('database.connections.' . static::databaseConnection(), static::databaseConfig());
        }
    }

    /**
     * The driver under test. Defaults to testbench's in-memory sqlite; the CI database matrix sets
     * FSK_DB_DRIVER to run the same suite against mysql and pgsql.
     *
     * Deliberately NOT read from DB_CONNECTION: testbench.yaml sets that for `testbench serve`, and
     * WithWorkbench loads it into the test process too, which would silently point the whole suite at
     * the on-disk dev database and let a refresh wipe it.
     */
    protected static function databaseConnection(): string
    {
        return env('FSK_DB_DRIVER', 'testing');
    }

    /** @return array<string, mixed> */
    protected static function databaseConfig(): array
    {
        $driver = static::databaseConnection();

        return [
            'driver' => $driver,
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', $driver === 'pgsql' ? '5432' : '3306'),
            'database' => env('DB_DATABASE', 'fsk_test'),
            'username' => env('DB_USERNAME', $driver === 'pgsql' ? 'postgres' : 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => $driver === 'pgsql' ? 'utf8' : 'utf8mb4',
            'prefix' => '',
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/Support/migrations');
    }
}
