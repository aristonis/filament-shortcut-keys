<?php

namespace Aristonis\FilamentShortcutKeys\Caching;

use Aristonis\FilamentShortcutKeys\Core\Contracts\MapRepository;
use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\Resolver;
use Aristonis\FilamentShortcutKeys\Core\Resolution\MapFingerprint;
use Aristonis\FilamentShortcutKeys\Core\Resolution\ResolvedMap;
use Illuminate\Contracts\Cache\Repository;

/**
 * Caches the panel-wide (navigation) map, which is page-independent and shared across every page of a
 * panel. The cache key is a fingerprint of every input that shapes the map — navigation version, user
 * identity, active map version, overlay and locale — so a changed input is a guaranteed cache miss and
 * a stale map is never served.
 *
 * Only the panel-wide resolver is wrapped: page sets are cheap and page-specific, so they are always
 * resolved fresh (see CompositeResolver, which sits above this decorator).
 */
final readonly class CachedResolver implements Resolver
{
    /**
     * @param  Resolver  $inner  the panel-wide resolver whose result is cached
     * @param  array<string, array<string, mixed>>  $overlay  developer force-letter/disable entries
     * @param  int|null  $ttl  cache lifetime in seconds; null caches forever
     */
    public function __construct(
        private Resolver $inner,
        private Repository $cache,
        private MapRepository $maps,
        private array $overlay,
        private string $locale,
        private ?int $ttl = null,
    ) {}

    public function resolve(
        NavigationProvider $nav,
        PageContextProvider $page,
        string $panelId,
        string $authType = '',
        string $authId = ''
    ): ResolvedMap {
        $navVersionToken = $nav->versionToken($panelId);

        // The identity/id are used to LOAD the map. However, the cache key is scoped by the map's own
        // row identity — so every user resolving to the same map shares one entry, while two forks
        // of the same source (same monotonic version) never collide because their ids differ.
        $map = $this->maps->activeMap($authType, $authId, $panelId);
        $mapIdentity = $map->type->value . ':' . ($map->id ?? 'none') . ':' . $map->version;

        $key = MapFingerprint::for(
            $panelId,
            $navVersionToken,
            $mapIdentity,
            $this->overlay,
            $this->locale,
        );

        $compute = fn (): ResolvedMap => $this->inner->resolve($nav, $page, $panelId, $authType, $authId);

        return $this->ttl === null
            ? $this->cache->rememberForever($key, $compute)
            : $this->cache->remember($key, $this->ttl, $compute);
    }
}
