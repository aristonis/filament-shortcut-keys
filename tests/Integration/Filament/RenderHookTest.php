<?php

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapEntryEdit;
use Aristonis\FilamentShortcutKeys\Filament\Pages\ManageShortcuts;
use Aristonis\FilamentShortcutKeys\Persistence\EloquentSystemMapAuthor;
use Aristonis\FilamentShortcutKeys\Tests\Support\Pages\AuditLog;
use Aristonis\FilamentShortcutKeys\Tests\Support\Pages\UsageReport;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\UserResource;
use Illuminate\Foundation\Auth\User as AuthUser;

it('injects the resolved keymap as a JSON script block on a panel page', function () {
    $html = (string) $this->get('/admin')->assertOk()->getContent();

    $map = injectedKeymap($html);

    expect($map)->toBeArray()->not->toBeEmpty();

    $navigation = collect($map)->firstWhere('set', 'navigation');

    expect($navigation)->not->toBeNull()
        ->and($navigation['bindings'])->not->toBeEmpty();
});

it('binds a navigation shortcut for each registered resource', function () {
    $html = (string) $this->get('/admin')->assertOk()->getContent();

    $navigation = collect(injectedKeymap($html))->firstWhere('set', 'navigation');
    $targets = array_column($navigation['bindings'], 'target');

    expect($targets)->toContain('navigation:' . OrderResource::class);
});

it('gives a visible item its own letter even when a hidden page shares the first letter', function () {
    $navigation = collect(injectedKeymap((string) $this->get('/admin')->assertOk()->getContent()))
        ->firstWhere('set', 'navigation');
    $users = collect($navigation['bindings'])->firstWhere('target', 'navigation:' . UserResource::class);

    expect($users['code'])->toBe('KeyU')
        ->and(array_column($navigation['bindings'], 'target'))->not->toContain('navigation:' . UsageReport::class);
});

it('still renders the panel when an included hidden page has no url without parameters', function () {
    config()->set('shortcut-keys.navigation.include_hidden', true);

    $navigation = collect(injectedKeymap((string) $this->get('/admin')->assertOk()->getContent()))
        ->firstWhere('set', 'navigation');

    expect(array_column($navigation['bindings'], 'target'))->toContain('navigation:' . UsageReport::class);
});

it('gives a hidden page a key when the overlay pins it a letter', function () {
    config()->set('shortcut-keys.overlay', ['navigation:' . UsageReport::class => ['letter' => 'r']]);

    $navigation = collect(injectedKeymap((string) $this->get('/admin')->assertOk()->getContent()))
        ->firstWhere('set', 'navigation');
    $report = collect($navigation['bindings'])->firstWhere('target', 'navigation:' . UsageReport::class);

    expect($report['code'] ?? null)->toBe('KeyR');
});

it('letters a page by what each admin sees in the sidebar, and never serves one admin the map of another', function () {
    $targetsFor = function (int $id): array {
        app()->forgetScopedInstances();
        $this->actingAs((new AuthUser)->forceFill(['id' => $id, 'name' => "Admin $id"]));
        $navigation = collect(injectedKeymap((string) $this->get('/admin')->assertOk()->getContent()))
            ->firstWhere('set', 'navigation');

        return array_column($navigation['bindings'], 'target');
    };

    expect($targetsFor(1))->toContain('navigation:' . AuditLog::class)
        ->and($targetsFor(2))->not->toContain('navigation:' . AuditLog::class);
});

it('keeps a page the current user cannot open in the keymap', function () {
    // The manager refuses this visitor, but the keymap is public by design: it is built from every
    // registered page so all users share one cacheable map. Access is enforced when the key fires.
    config()->set('shortcut-keys.customization', 'locked');
    config()->set('shortcut-keys.authorize', null);

    $this->get(ManageShortcuts::getUrl())->assertForbidden();

    $navigation = collect(injectedKeymap((string) $this->get('/admin')->assertOk()->getContent()))
        ->firstWhere('set', 'navigation');

    expect(array_column($navigation['bindings'], 'target'))
        ->toContain('navigation:' . ManageShortcuts::class);
});

it('carries the handler and a navigate activation on each navigation binding', function () {
    $html = (string) $this->get('/admin')->assertOk()->getContent();

    $navigation = collect(injectedKeymap($html))->firstWhere('set', 'navigation');
    $binding = collect($navigation['bindings'])->firstWhere('target', 'navigation:' . OrderResource::class);

    expect($navigation['handler'])->toBe('navigation')
        ->and($binding['code'])->toStartWith('Key')
        ->and($binding['activation']['kind'])->toBe('navigate')
        ->and($binding['activation']['url'])->toContain('/admin');
});

it('injects the cheatsheet overlay with the resolved shortcuts and a reference link', function () {
    $html = (string) $this->get('/admin')->assertOk()->getContent();

    expect($html)
        ->toContain('id="filament-shortcut-keys-overlay"')
        ->toContain('Navigation')            // a set heading rendered from the real map
        ->toContain('shortcut-reference');   // the full-reference link to the reference page
});

it('labels a page action in the overlay by its action label', function () {
    $html = (string) $this->get('/admin/orders')->assertOk()->getContent();

    // The overlay sits at the end of the page, so anything after its marker is its own content;
    // the "export" header action must appear there by its resolved label, not just the raw name.
    $overlay = substr($html, (int) strpos($html, 'id="filament-shortcut-keys-overlay"'));

    expect($overlay)
        ->toContain('Actions')   // the global set heading
        ->toContain('Export');   // the action's resolved label
});

it('tells the client a navigation key waits for the g leader and needs no held modifier', function () {
    $navigation = collect(injectedKeymap((string) $this->get('/admin')->assertOk()->getContent()))
        ->firstWhere('set', 'navigation');

    expect($navigation['modifier'])->toBe('')
        ->and($navigation['leader'])->toBe('KeyG');
});

it('serves a fresh keymap after the modifiers config changes', function () {
    $this->get('/admin')->assertOk();

    config()->set('shortcut-keys.modifiers.navigation', ['alt', 'shift']);

    $navigation = collect(injectedKeymap((string) $this->get('/admin')->assertOk()->getContent()))
        ->firstWhere('set', 'navigation');

    expect($navigation['modifier'])->toBe('alt+shift')
        ->and($navigation['leader'])->toBeNull();
});

it('writes a g shortcut in the cheatsheet as g then the letter', function () {
    // Livewire wraps every Blade conditional in comment markers, which are not part of what users see.
    $html = preg_replace('/<!--.*?-->/s', '', (string) $this->get('/admin')->assertOk()->getContent());

    expect($html)->toMatch('#<kbd>g</kbd>\\s*<span class="fsk-then">then</span>\\s*<kbd>[a-z]</kbd>#');
});

it('serves the panel default map to an admin with no map of their own in locked mode', function () {
    config()->set('shortcut-keys.customization', 'locked');
    $author = new EloquentSystemMapAuthor;
    $preset = $author->createPreset('admin');
    $author->saveEntry('admin', $preset, new MapEntryEdit('navigation:' . OrderResource::class, 'x', false));
    $author->makeDefault('admin', $preset);

    $navigation = collect(injectedKeymap((string) $this->get('/admin')->assertOk()->getContent()))->firstWhere('set', 'navigation');
    $orders = collect($navigation['bindings'])->firstWhere('target', 'navigation:' . OrderResource::class);

    expect($orders['code'])->toBe('KeyX');
});
