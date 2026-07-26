<?php

use Aristonis\FilamentShortcutKeys\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

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
