<?php

use Aristonis\FilamentShortcutKeys\Core\Resolution\MapFingerprint;

function fingerprint(array $overrides = []): string
{
    $args = array_merge([
        'panelId' => 'admin',
        'navVersionToken' => 'nav-v1',
        'authType' => 'App\\Models\\User',
        'authId' => '1',
        'activeMapVersion' => 1,
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

it('changes when the user identity changes', function () {
    expect(fingerprint())->not->toBe(fingerprint(['authId' => '2']))
        ->and(fingerprint())->not->toBe(fingerprint(['authType' => 'App\\Models\\Admin']));
});

it('changes when the active map version changes', function () {
    expect(fingerprint())->not->toBe(fingerprint(['activeMapVersion' => 2]));
});

it('changes when the config overlay changes', function () {
    expect(fingerprint())->not->toBe(fingerprint(['overlay' => ['nav:products' => ['letter' => 'R']]]));
});

it('changes when the locale changes', function () {
    expect(fingerprint())->not->toBe(fingerprint(['locale' => 'ar']));
});
