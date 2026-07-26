<?php

use Aristonis\FilamentShortcutKeys\Filament\Pages\ManageShortcuts;
use Aristonis\FilamentShortcutKeys\Filament\Pages\ShortcutReference;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMap;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    app()->setLocale('ar');
});

it('renders the reference page in the active locale', function () {
    $this->get(ShortcutReference::getUrl())
        ->assertOk()
        ->assertSee(trans('filament-shortcut-keys::shortcut-keys.subheading'))
        ->assertSee(trans('filament-shortcut-keys::shortcut-keys.sets.navigation'))
        ->assertDontSee('Shortcuts available across this panel.');
});

it('renders the cheatsheet overlay in the active locale', function () {
    $this->get(ShortcutReference::getUrl())
        ->assertOk()
        ->assertSee(trans('filament-shortcut-keys::shortcut-keys.overlay.full_reference'));
});

it('mirrors the pagination arrows in the injected keymap for a right-to-left panel', function () {
    $html = (string) $this->get('/admin/orders')->assertOk()->getContent();

    $table = collect(injectedKeymap($html))->firstWhere('set', 'table');
    $codes = collect($table['bindings'])->pluck('code', 'target');

    expect($codes['table:page-next'])->toBe('ArrowLeft')
        ->and($codes['table:page-prev'])->toBe('ArrowRight');
});

it('renders the map manager in the active locale', function () {
    config()->set('shortcut-keys.customization', 'personal');
    ShortcutMap::factory()->default('admin')->create();

    $this->get(ManageShortcuts::getUrl())
        ->assertOk()
        ->assertSee(trans('filament-shortcut-keys::shortcut-keys.manage.your_maps'))
        ->assertSee(trans('filament-shortcut-keys::shortcut-keys.manage.remap'))
        ->assertDontSee('Available maps');
});

it('labels every edit form field in the active locale', function (string $action) {
    config()->set('shortcut-keys.customization', 'personal');
    config()->set('shortcut-keys.authorize', fn () => true);
    ShortcutMap::factory()->default('admin')->create();
    Filament::setCurrentPanel('admin');

    $page = Livewire::test(ManageShortcuts::class)->mountAction($action)->instance();
    $components = $page->getSchema($page->getMountedActionSchemaName())->getComponents();

    expect($components)->not->toBeEmpty();

    foreach ($components as $component) {
        // Filament falls back to a humanized field name, which is always English no matter the
        // locale, so a label that still reads like its own key means the trans() call is missing.
        expect($component->getLabel())->not->toBe(Str::headline($component->getName()));
    }
})->with(['changeActiveMap', 'remap', 'disable', 'reset', 'addCustomBinding', 'clonePreset', 'editPreset']);
