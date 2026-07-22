<?php

namespace Aristonis\FilamentShortcutKeys\Core\Resolution;

/**
 * Builds the cache key for a resolved map from the inputs that determine its contents.
 * Any input differing yields a different key, so a stale map is never served from cache.
 *
 * Identity separates one user's custom map from another's. Permission is deliberately absent:
 * the keymap is public and identical regardless of what a given user can access.
 */
final class MapFingerprint
{
    public static function for(
        string $panelId,
        string $navVersionToken,
        string $authType,
        string $authId,
        int $activeMapVersion,
        array $overlay,
        string $locale,
    ): string {
        return hash('xxh128', implode('|', [
            $panelId,
            $navVersionToken,
            $authType,
            $authId,
            (string) $activeMapVersion,
            md5(json_encode($overlay)),
            $locale,
        ]));
    }
}
