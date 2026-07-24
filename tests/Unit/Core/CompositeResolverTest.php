<?php

use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\Resolver;
use Aristonis\FilamentShortcutKeys\Core\Contracts\ShortcutSet;
use Aristonis\FilamentShortcutKeys\Core\Resolution\CompositeResolver;
use Aristonis\FilamentShortcutKeys\Core\Resolution\ResolvedMap;
use Aristonis\FilamentShortcutKeys\Core\Resolution\ShortcutResolver;
use Aristonis\FilamentShortcutKeys\Core\Sets\ShortcutSetRegistry;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutBinding;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutTarget;
use Aristonis\FilamentShortcutKeys\Tests\Fakes\InMemoryNavigationProvider;
use Aristonis\FilamentShortcutKeys\Tests\Fakes\InMemoryPageContextProvider;

/** A single-set registry whose one set discovers exactly one convention binding. */
function singleSetResolver(string $set, ModifierScheme $modifier, string $structureKey): ShortcutResolver
{
    $shortcutSet = new class($set, $modifier, $structureKey) implements ShortcutSet
    {
        public function __construct(
            private string $set,
            private ModifierScheme $modifier,
            private string $structureKey
        ) {}

        public function key(): string
        {
            return $this->set;
        }

        public function defaultModifier(): ModifierScheme
        {
            return $this->modifier;
        }

        public function discover(NavigationProvider $nav, PageContextProvider $page, string $panelId): array
        {
            return [new ShortcutBinding(new ShortcutTarget($this->set, $this->structureKey), null, letterHint: 'Item')];
        }

        public function clientHandler(): string
        {
            return '';
        }
    };

    $registry = new ShortcutSetRegistry;
    $registry->register($shortcutSet);

    return new ShortcutResolver($registry);
}

/** Records the arguments passed to resolve() so the composite's fan-out can be asserted. */
function recordingResolver(ResolvedMap $map, array &$seen): Resolver
{
    return new class($map, $seen) implements Resolver
    {
        public function __construct(private ResolvedMap $map, private array &$seen) {}

        public function resolve(
            NavigationProvider $nav,
            PageContextProvider $page,
            string $panelId,
            string $authType = '',
            string $authId = ''
        ): ResolvedMap {
            $this->seen[] = [$panelId, $authType, $authId];

            return $this->map;
        }
    };
}

it('merges the panel-wide and page maps into one resolved map', function () {
    $panelWide = singleSetResolver('navigation', ModifierScheme::altShift(), 'products');
    $page = singleSetResolver('table', ModifierScheme::none(), 'search');

    $composite = new CompositeResolver($panelWide, $page);

    $groups = $composite->resolve(
        new InMemoryNavigationProvider([], 'v1'),
        new InMemoryPageContextProvider([], false),
        'admin',
    )->groups();

    $setKeys = array_map(fn ($group) => $group->setKey, $groups);

    expect($setKeys)->toBe(['navigation', 'table']);
});

it('forwards identical arguments to both wrapped resolvers', function () {
    $seen = [];
    $empty = new ResolvedMap([]);
    $composite = new CompositeResolver(
        recordingResolver($empty, $seen),
        recordingResolver($empty, $seen),
    );

    $composite->resolve(
        new InMemoryNavigationProvider([], 'v1'),
        new InMemoryPageContextProvider([], false),
        'admin',
        'App\\Models\\User',
        '7',
    );

    expect($seen)->toBe([
        ['admin', 'App\\Models\\User', '7'],
        ['admin', 'App\\Models\\User', '7'],
    ]);
});
