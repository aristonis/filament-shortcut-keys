<?php

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapEntryEdit;
use Aristonis\FilamentShortcutKeys\Filament\Pages\ShortcutReference;
use Aristonis\FilamentShortcutKeys\Persistence\EloquentSystemMapAuthor;

it('renders the panel-wide shortcut reference with the navigation group', function () {
    $this->get(ShortcutReference::getUrl())
        ->assertOk()
        ->assertSee('Keyboard shortcuts')
        ->assertSee('Navigation');
});

it('lists a registered resource and omits the per-page actions', function () {
    $html = (string) $this->get(ShortcutReference::getUrl())->assertOk()->getContent();

    // Header actions change from page to page, so they stay in the ? cheatsheet.
    expect($html)
        ->toContain('Orders')
        ->not->toContain('Export');
});

it('lists the table keys and the row-action letters, which are the same on every table', function () {
    $this->get(ShortcutReference::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['Table', 'Search', '/', 'Start or move the row cursor down', 'J'])
        ->assertSeeInOrder(['Row actions', 'Approve', 'Reject']);
});

it('shows a row action on the key the panel default map gives it', function () {
    $author = new EloquentSystemMapAuthor;
    $preset = $author->createPreset('admin');
    $author->saveEntry('admin', $preset, new MapEntryEdit('row-action:approve', 'x', false));
    $author->makeDefault('admin', $preset);

    $this->get(ShortcutReference::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['Approve', 'X', 'Reject']);
});
