<?php

use Aristonis\FilamentShortcutKeys\Core\Resolution\MapFingerprint;

function fingerprint(array $overrides = []): string
{
    $args = array_merge([
        'panelId' => 'admin',
        'navVersionToken' => 'nav-v1',
        'mapIdentity' => 'system:1:1',
        'overlay' => [],
        'locale' => 'en',
    ], $overrides);

    return MapFingerprint::for(...$args);
}

it('is stable for identical inputs', function () {
    expect(fingerprint())->toBe(fingerprint());
});

it('changes when the panel changes', function () {
    expect(fingerprint())->not->toBe(fingerprint(['panelId' => 'customer']));
});

it('changes when the navigation version token changes', function () {
    expect(fingerprint())->not->toBe(fingerprint(['navVersionToken' => 'nav-v2']));
});

it('changes when the active map identity changes', function () {
    // A different type, row id, or version all shift the identity string, so the key must move.
    expect(fingerprint())->not->toBe(fingerprint(['mapIdentity' => 'custom:2:1']))
        ->and(fingerprint())->not->toBe(fingerprint(['mapIdentity' => 'system:1:2']));
});

it('changes when the config overlay changes', function () {
    expect(fingerprint())->not->toBe(fingerprint(['overlay' => ['nav:products' => ['letter' => 'R']]]));
});

it('changes when the locale changes', function () {
    expect(fingerprint())->not->toBe(fingerprint(['locale' => 'ar']));
});
