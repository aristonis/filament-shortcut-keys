<?php

use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\ShortcutSet;
use Aristonis\FilamentShortcutKeys\Core\Sets\ShortcutSetRegistry;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutBinding;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutTarget;
use Aristonis\FilamentShortcutKeys\Tests\Fakes\InMemoryNavigationProvider;
use Aristonis\FilamentShortcutKeys\Tests\Fakes\InMemoryPageContextProvider;

/** A minimal set that emits one binding under its own key, to isolate registry behaviour. */
function fakeSet(string $key): ShortcutSet
{
    return new class($key) implements ShortcutSet
    {
        public function __construct(private string $key) {}

        public function key(): string
        {
            return $this->key;
        }

        public function defaultModifier(): ModifierScheme
        {
            return ModifierScheme::alt();
        }

        public function discover(NavigationProvider $nav, PageContextProvider $page, string $panelId): array
        {
            return [new ShortcutBinding(new ShortcutTarget($this->key, 'one'), null)];
        }

        public function clientHandler(): string
        {
            return $this->key;
        }
    };
}

it('registers a set and retrieves it by key', function () {
    $registry = new ShortcutSetRegistry;
    $set = fakeSet('navigation');

    $registry->register($set);

    expect($registry->get('navigation'))->toBe($set);
});

it('returns null for an unknown key', function () {
    expect((new ShortcutSetRegistry)->get('nope'))->toBeNull();
});

it('lists every registered set in registration order', function () {
    $registry = new ShortcutSetRegistry;
    $registry->register(fakeSet('navigation'));
    $registry->register(fakeSet('global'));

    $keys = array_map(fn (ShortcutSet $s) => $s->key(), $registry->all());

    expect($keys)->toBe(['navigation', 'global']);
});

it('aggregates discover across every registered set', function () {
    $registry = new ShortcutSetRegistry;
    $registry->register(fakeSet('navigation'));
    $registry->register(fakeSet('global'));

    $bindings = $registry->discover(
        new InMemoryNavigationProvider(items: [], versionToken: 'v1'),
        new InMemoryPageContextProvider(actions: [], hasTable: false),
        'admin',
    );

    $identities = array_map(fn (ShortcutBinding $b) => $b->target->identity(), $bindings);

    expect($identities)->toBe(['navigation:one', 'global:one']);
});
