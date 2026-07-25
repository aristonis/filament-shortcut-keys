<?php

use Aristonis\FilamentShortcutKeys\Filament\Pages\ShortcutReference;

it('renders the panel-wide shortcut reference with the navigation group', function () {
    $this->get(ShortcutReference::getUrl())
        ->assertOk()
        ->assertSee('Keyboard shortcuts')
        ->assertSee('Navigation');
});

it('lists a registered resource and omits page-scoped sets', function () {
    $html = (string) $this->get(ShortcutReference::getUrl())->assertOk()->getContent();

    // Navigation and custom bindings are panel-wide, so they belong here; the table set is page-scoped.
    expect($html)
        ->toContain('Orders')
        ->not->toContain('>Table<');
});
