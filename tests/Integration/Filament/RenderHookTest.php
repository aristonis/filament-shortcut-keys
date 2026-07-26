<?php

use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource;

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
