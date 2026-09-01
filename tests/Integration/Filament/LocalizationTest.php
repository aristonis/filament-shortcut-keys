<?php

use Aristonis\FilamentShortcutKeys\Filament\Pages\ManageShortcuts;
use Aristonis\FilamentShortcutKeys\Filament\Pages\ShortcutReference;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMap;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;

/**
 * The package ships English only, so translatability is proven against a locale registered here
 * rather than against a second shipped language. That is the property worth guarding: no copy is
 * hardcoded, so a host publishing the files gets a panel fully in their own language.
 */
beforeEach(function () {
    Lang::addLines([
        'shortcut-keys.subheading' => 'ZZ subheading',
        'shortcut-keys.sets.navigation' => 'ZZ navigation',
        'shortcut-keys.overlay.full_reference' => 'ZZ full reference',
        'shortcut-keys.manage.your_maps' => 'ZZ your maps',
        'shortcut-keys.manage.remap' => 'ZZ remap',
        'shortcut-keys.manage.fields.map' => 'ZZ map',
        'shortcut-keys.manage.fields.source' => 'ZZ source',
        'shortcut-keys.manage.fields.target' => 'ZZ target',
        'shortcut-keys.manage.fields.letter' => 'ZZ letter',
        'shortcut-keys.manage.fields.name' => 'ZZ name',
        'shortcut-keys.manage.fields.kind' => 'ZZ kind',
        'shortcut-keys.manage.fields.value' => 'ZZ value',
        'shortcut-keys.manage.edit_preset_confirm' => 'ZZ edit preset confirm',
    ], 'zz', 'filament-shortcut-keys');

    app()->setLocale('zz');
});

it('renders the reference page in the active locale', function () {
    $this->get(ShortcutReference::getUrl())
        ->assertOk()
        ->assertSee('ZZ subheading')
        ->assertSee('ZZ navigation')
        ->assertDontSee('Shortcuts available across this panel.');
});

it('renders the cheatsheet overlay in the active locale', function () {
    $this->get(ShortcutReference::getUrl())
        ->assertOk()
        ->assertSee('ZZ full reference');
});

it('renders the map manager in the active locale', function () {
    config()->set('shortcut-keys.customization', 'personal');
    ShortcutMap::factory()->default('admin')->create();

    $this->get(ManageShortcuts::getUrl())
        ->assertOk()
        ->assertSee('ZZ your maps')
        ->assertSee('ZZ remap')
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
        // Every label must resolve through translations. Asserting only that it differs from
        // Filament's humanized fallback is not enough: several English labels ("Letter", "Name")
        // are identical to it, so a missing trans() call would read as correct.
        expect($component->getLabel())->toStartWith('ZZ ');
    }
})->with(['changeActiveMap', 'remap', 'disable', 'reset', 'addCustomBinding', 'clonePreset', 'editPreset']);

it('mirrors the pagination arrows in the injected keymap for a right-to-left panel', function () {
    // Direction comes from Filament's own translations, not this package's, so an RTL host gets
    // mirrored arrows even though only English copy ships here.
    app()->setLocale('ar');

    $html = (string) $this->get('/admin/orders')->assertOk()->getContent();

    $table = collect(injectedKeymap($html))->firstWhere('set', 'table');
    $codes = collect($table['bindings'])->pluck('code', 'target');

    expect(trans('filament-panels::layout.direction'))->toBe('rtl')
        ->and($codes['table:page-next'])->toBe('ArrowLeft')
        ->and($codes['table:page-prev'])->toBe('ArrowRight');
});
