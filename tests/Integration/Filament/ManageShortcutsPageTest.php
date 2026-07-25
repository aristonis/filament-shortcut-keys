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
