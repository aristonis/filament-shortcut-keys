<?php

use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource;

/**
 * Extracts the JSON body of the plugin's injected keymap <script> block from a rendered page.
 *
 * @return array<int, array<string, mixed>>|null the decoded ResolvedMap, or null when absent
 */
function injectedKeymap(string $html): ?array
{
    if (! preg_match('/<script[^>]*id="filament-shortcut-keys-map"[^>]*>(.*?)<\/script>/s', $html, $m)) {
        return null;
    }

    return json_decode(trim($m[1]), true);
}

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
