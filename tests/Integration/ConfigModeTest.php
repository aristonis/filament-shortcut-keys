<?php

use Aristonis\FilamentShortcutKeys\Application\AuthorSystemMap;
use Aristonis\FilamentShortcutKeys\Authorization\AuthorityGate;
use Aristonis\FilamentShortcutKeys\Core\Contracts\MapRepository;
use Aristonis\FilamentShortcutKeys\Core\Contracts\SystemMapAuthor;
use Aristonis\FilamentShortcutKeys\Core\Enums\CustomizationMode;
use Aristonis\FilamentShortcutKeys\Exceptions\EditingLockedException;
use Aristonis\FilamentShortcutKeys\Filament\Pages\ManageShortcuts;
use Aristonis\FilamentShortcutKeys\Filament\Pages\ShortcutReference;
use Aristonis\FilamentShortcutKeys\FilamentShortcutKeysPlugin;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource;
use Filament\Panel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * In 'config' mode the keys come only from the conventions and the config file, so the package never
 * touches its tables: an app can skip the migrations entirely.
 */
beforeEach(fn () => config()->set('shortcut-keys.customization', 'config'));

/** @return string[] every query that reached one of the package's tables while $run ran */
function shortcutTableQueries(callable $run): array
{
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        if (str_contains($query->sql, 'shortcut_map')) {
            $queries[] = $query->sql;
        }
    });

    $run();

    return $queries;
}

it('renders the keymap without touching the package tables', function () {
    $queries = shortcutTableQueries(function () {
        $this->get('/admin/orders')->assertOk();
        $this->get(ShortcutReference::getUrl())->assertOk();
    });

    expect($queries)->toBe([]);
});

it('still applies the config overlay', function () {
    config()->set('shortcut-keys.overlay', ['navigation:' . OrderResource::class => ['letter' => 'x']]);

    $navigation = collect(injectedKeymap((string) $this->get('/admin')->assertOk()->getContent()))->firstWhere('set', 'navigation');
    $orders = collect($navigation['bindings'])->firstWhere('target', 'navigation:' . OrderResource::class);

    expect($orders['code'])->toBe('KeyX');
});

it('closes the manage page, even to an admin the authoring gate grants', function () {
    config()->set('shortcut-keys.authorize', fn () => true);

    $this->get(ManageShortcuts::getUrl())->assertForbidden();
});

it('refuses shared-map authoring from code', function () {
    config()->set('shortcut-keys.authorize', fn () => true);

    (new AuthorSystemMap(app(AuthorityGate::class), app(SystemMapAuthor::class)))
        ->createPreset(auth()->user(), 'admin');
})->throws(EditingLockedException::class);

it('resolves an empty stored map', function () {
    expect(app(MapRepository::class)->activeMap('users', '1', 'admin')->entries)->toBe([]);
});

it('has nothing to prune and never queries the tables', function () {
    $queries = shortcutTableQueries(function () {
        $this->artisan('shortcut-keys:prune', ['--force' => true])
            ->expectsOutputToContain('nothing is stored')
            ->assertSuccessful();
    });

    expect($queries)->toBe([]);
});

it('runs with the package tables never migrated', function () {
    Schema::dropIfExists('shortcut_map_selections');
    Schema::dropIfExists('shortcut_map_entries');
    Schema::dropIfExists('shortcut_maps');

    $this->get('/admin/orders')->assertOk();
    $this->get(ShortcutReference::getUrl())->assertOk();
});

it('does not register the manage page', function () {
    $panel = Panel::make()->id('config-only');

    FilamentShortcutKeysPlugin::make()->register($panel);

    expect($panel->getPages())->toContain(ShortcutReference::class)
        ->not->toContain(ManageShortcuts::class);
});

it('refuses an unknown customization mode instead of guessing', function () {
    config()->set('shortcut-keys.customization', 'confg');

    CustomizationMode::current();
})->throws(ValueError::class);
