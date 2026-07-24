<?php

use Aristonis\FilamentShortcutKeys\Caching\CachedResolver;
use Aristonis\FilamentShortcutKeys\Core\Contracts\MapRepository;
use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\Resolver;
use Aristonis\FilamentShortcutKeys\Core\Enums\MapType;
use Aristonis\FilamentShortcutKeys\Core\Resolution\ResolvedGroup;
use Aristonis\FilamentShortcutKeys\Core\Resolution\ResolvedMap;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapData;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Tests\Fakes\InMemoryMapRepository;
use Aristonis\FilamentShortcutKeys\Tests\Fakes\InMemoryNavigationProvider;
use Aristonis\FilamentShortcutKeys\Tests\Fakes\InMemoryPageContextProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/** An inner Resolver that counts how many times it actually computes a map. */
function countingInner(): Resolver
{
    return new class implements Resolver
    {
        public int $calls = 0;

        public function resolve(
            NavigationProvider $nav,
            PageContextProvider $page,
            string $panelId,
            string $authType = '',
            string $authId = ''
        ): ResolvedMap {
            $this->calls++;

            return new ResolvedMap([new ResolvedGroup('navigation', ModifierScheme::altShift(), [])]);
        }
    };
}

/**
 * A repository whose active map has a fixed identity (type + row id + version), regardless of which
 * user asks — mirroring a shared system-default map that every user resolves to.
 */
function repoForMap(MapType $type, ?int $id, int $version): MapRepository
{
    return new InMemoryMapRepository(new MapData($type, null, [], $version, $id));
}

/** @return array{0: Repository, 1: NavigationProvider, 2: PageContextProvider} */
function cachingFixtures(): array
{
    return [
        Cache::store('array'),
        new InMemoryNavigationProvider([], 'nav-v1'),
        new InMemoryPageContextProvider([], false),
    ];
}

it('computes on the first call and stores the result', function () {
    [$cache, $nav, $page] = cachingFixtures();
    $inner = countingInner();
    $resolver = new CachedResolver($inner, $cache, repoForMap(MapType::SYSTEM, 1, 1), [], 'en');

    $map = $resolver->resolve($nav, $page, 'admin', 'App\\Models\\User', '1');

    expect($inner->calls)->toBe(1)
        ->and($map->groups()[0]->setKey)->toBe('navigation');
});

it('serves the second identical call from cache without recomputing', function () {
    [$cache, $nav, $page] = cachingFixtures();
    $inner = countingInner();
    $resolver = new CachedResolver($inner, $cache, repoForMap(MapType::SYSTEM, 1, 1), [], 'en');

    $resolver->resolve($nav, $page, 'admin', 'App\\Models\\User', '1');
    $resolver->resolve($nav, $page, 'admin', 'App\\Models\\User', '1');

    expect($inner->calls)->toBe(1);
});

it('shares one cache entry for two users resolving to the same active map (D39)', function () {
    [$cache, $nav, $page] = cachingFixtures();
    $inner = countingInner();
    // Both users resolve to the identical shared system map (same type, id, version),
    // so the key is keyed on the map's identity, not on who asked → a single entry.
    $resolver = new CachedResolver($inner, $cache, repoForMap(MapType::SYSTEM, 1, 1), [], 'en');

    $resolver->resolve($nav, $page, 'admin', 'App\\Models\\User', '1');
    $resolver->resolve($nav, $page, 'admin', 'App\\Models\\User', '2');

    expect($inner->calls)->toBe(1);
});

it('recomputes when the active map identity differs (custom vs system)', function () {
    [$cache, $nav, $page] = cachingFixtures();
    $inner = countingInner();

    (new CachedResolver($inner, $cache, repoForMap(MapType::SYSTEM, 1, 1), [], 'en'))
        ->resolve($nav, $page, 'admin', 'App\\Models\\User', '1');
    // A different user on their own forked custom map — distinct row identity → cache miss,
    // so two forks that happen to share a monotonic version never collide.
    (new CachedResolver($inner, $cache, repoForMap(MapType::CUSTOM, 2, 1), [], 'en'))
        ->resolve($nav, $page, 'admin', 'App\\Models\\User', '2');

    expect($inner->calls)->toBe(2);
});

it('recomputes when the active map version changes (cache miss)', function () {
    [$cache, $nav, $page] = cachingFixtures();
    $inner = countingInner();

    (new CachedResolver($inner, $cache, repoForMap(MapType::SYSTEM, 1, 1), [], 'en'))
        ->resolve($nav, $page, 'admin', 'App\\Models\\User', '1');
    // Same map row, bumped version (it was edited) → different identity → miss.
    (new CachedResolver($inner, $cache, repoForMap(MapType::SYSTEM, 1, 2), [], 'en'))
        ->resolve($nav, $page, 'admin', 'App\\Models\\User', '1');

    expect($inner->calls)->toBe(2);
});

it('recomputes when the overlay changes (cache miss)', function () {
    [$cache, $nav, $page] = cachingFixtures();
    $inner = countingInner();

    (new CachedResolver($inner, $cache, repoForMap(MapType::SYSTEM, 1, 1), [], 'en'))
        ->resolve($nav, $page, 'admin', 'App\\Models\\User', '1');
    (new CachedResolver($inner, $cache, repoForMap(MapType::SYSTEM, 1, 1), ['navigation:products' => ['letter' => 'r']], 'en'))
        ->resolve($nav, $page, 'admin', 'App\\Models\\User', '1');

    expect($inner->calls)->toBe(2);
});

it('recomputes when the locale changes (cache miss)', function () {
    [$cache, $nav, $page] = cachingFixtures();
    $inner = countingInner();

    (new CachedResolver($inner, $cache, repoForMap(MapType::SYSTEM, 1, 1), [], 'en'))
        ->resolve($nav, $page, 'admin', 'App\\Models\\User', '1');
    (new CachedResolver($inner, $cache, repoForMap(MapType::SYSTEM, 1, 1), [], 'ar'))
        ->resolve($nav, $page, 'admin', 'App\\Models\\User', '1');

    expect($inner->calls)->toBe(2);
});

it('honors a finite ttl by storing under the same key', function () {
    [$cache, $nav, $page] = cachingFixtures();
    $inner = countingInner();
    $resolver = new CachedResolver($inner, $cache, repoForMap(MapType::SYSTEM, 1, 1), [], 'en', ttl: 60);

    $resolver->resolve($nav, $page, 'admin', 'App\\Models\\User', '1');
    $resolver->resolve($nav, $page, 'admin', 'App\\Models\\User', '1');

    expect($inner->calls)->toBe(1);
});
