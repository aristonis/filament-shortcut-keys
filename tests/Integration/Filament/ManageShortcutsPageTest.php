<?php

use Aristonis\FilamentShortcutKeys\Filament\Pages\ManageShortcuts;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMap;

it('renders the manager with the available maps in personal mode', function () {
    config()->set('shortcut-keys.customization', 'personal');
    ShortcutMap::factory()->default('admin')->create();

    $this->get(ManageShortcuts::getUrl())
        ->assertOk()
        ->assertSee('Manage shortcuts')
        ->assertSee('Available maps')
        ->assertSee('Default preset');
});

it('shows personal actions but hides authoring actions when the gate denies', function () {
    config()->set('shortcut-keys.customization', 'personal');
    config()->set('shortcut-keys.authorize', null);
    ShortcutMap::factory()->default('admin')->create();

    $this->get(ManageShortcuts::getUrl())
        ->assertOk()
        ->assertSee('Remap a shortcut')
        ->assertDontSee('Create preset');
});

it('shows authoring actions but hides personal actions in locked mode with the gate granting', function () {
    config()->set('shortcut-keys.customization', 'locked');
    config()->set('shortcut-keys.authorize', fn () => true);
    ShortcutMap::factory()->default('admin')->create();

    $this->get(ManageShortcuts::getUrl())
        ->assertOk()
        ->assertSee('Create preset')
        ->assertDontSee('Remap a shortcut');
});

it('forbids the manager in locked mode when the authoring gate denies', function () {
    config()->set('shortcut-keys.customization', 'locked');
    config()->set('shortcut-keys.authorize', null);
    ShortcutMap::factory()->default('admin')->create();

    $this->get(ManageShortcuts::getUrl())->assertForbidden();
});

it('allows the manager in locked mode when the authoring gate grants', function () {
    config()->set('shortcut-keys.customization', 'locked');
    config()->set('shortcut-keys.authorize', fn () => true);
    ShortcutMap::factory()->default('admin')->create();

    $this->get(ManageShortcuts::getUrl())->assertOk();
});

it('lets an authorized admin make a preset the panel default', function () {
    config()->set('shortcut-keys.customization', 'locked');
    config()->set('shortcut-keys.authorize', fn () => true);
    Filament\Facades\Filament::setCurrentPanel('admin');
    $preset = ShortcutMap::factory()->create(['panel_id' => 'admin']);

    $page = Livewire\Livewire::test(ManageShortcuts::class)
        ->callAction('setDefaultPreset', ['map' => $preset->id, 'confirmed' => true]);

    expect($preset->fresh()->default_marker)->toBe('admin');

    // The page shows the new default in the same request.
    expect(collect($page->viewData('maps'))->where('isDefault', true))->toHaveCount(1);
});

it('lists a preset on the page as soon as it is created', function () {
    config()->set('shortcut-keys.customization', 'locked');
    config()->set('shortcut-keys.authorize', fn () => true);
    Filament\Facades\Filament::setCurrentPanel('admin');

    $page = Livewire\Livewire::test(ManageShortcuts::class);
    $before = count($page->viewData('maps'));

    $page->callAction('createPreset');

    expect($page->viewData('maps'))->toHaveCount($before + 1);
});

it('numbers each preset in the list the same way the forms do', function () {
    config()->set('shortcut-keys.customization', 'locked');
    config()->set('shortcut-keys.authorize', fn () => true);
    Filament\Facades\Filament::setCurrentPanel('admin');
    ShortcutMap::factory()->default('admin')->create();
    ShortcutMap::factory()->count(2)->create(['panel_id' => 'admin']);

    $labels = collect(Livewire\Livewire::test(ManageShortcuts::class)->viewData('maps'))->pluck('label')->all();

    expect($labels)->toBe(['Default preset', 'Preset 1', 'Preset 2']);
});
