<?php

namespace Aristonis\FilamentShortcutKeys\Core\Resolution;

/**
 * Builds the cache key for the panel-wide (navigation) map from the inputs that determine its
 * contents. Any input differing yields a different key, so a stale map is never served from cache.
 *
 * The navigation map is page-independent, so there is no page token: the same key serves every page
 * of a panel. The key is scoped by the active map's identity, not by user identity — every user
 * resolving to the same map (e.g. the shared system default) shares one entry, while two forks of the
 * same source stay distinct because their row ids differ. Permission is deliberately absent: the
 * keymap is public and identical regardless of what a given user can access.
 */
final class MapFingerprint
{
    public static function for(
        string $panelId,
        string $navVersionToken,
        string $mapIdentity,
        array $overlay,
        string $locale,
    ): string {
        return hash('xxh128', implode('|', [
            $panelId,
            $navVersionToken,
            $mapIdentity,
            md5(json_encode($overlay, JSON_THROW_ON_ERROR)),
            $locale,
        ]));
    }
}
