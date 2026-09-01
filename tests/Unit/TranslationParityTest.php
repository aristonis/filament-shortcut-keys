<?php

use Illuminate\Support\Arr;

$langPath = __DIR__ . '/../../resources/lang';

/** @return string[] every dot-path that maps to a string, so nested groups compare like flat lists */
function translationKeys(string $file): array
{
    $keys = array_keys(Arr::dot(require $file));
    sort($keys);

    return $keys;
}

it('ships the locales the package promises', function () use ($langPath) {
    // English only in v1. A second language is a promise to keep it in step with every future copy
    // change, and one that was authored but never read by a native speaker is worse than none.
    expect(array_map('basename', glob($langPath . '/*', GLOB_ONLYDIR)))
        ->toEqualCanonicalizing(['en']);
});

it('translates every english key in each other locale', function () use ($langPath) {
    $english = translationKeys($langPath . '/en/shortcut-keys.php');

    foreach (glob($langPath . '/*/shortcut-keys.php') as $file) {
        expect(translationKeys($file))->toBe($english, basename(dirname($file)) . ' is out of sync with en');
    }
});

it('leaves no translation empty', function () use ($langPath) {
    foreach (glob($langPath . '/*/shortcut-keys.php') as $file) {
        foreach (Arr::dot(require $file) as $key => $value) {
            expect(trim((string) $value))->not->toBe('', "$key is empty in " . basename(dirname($file)));
        }
    }
});
