<?php

use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\ShortcutSet;
use Aristonis\FilamentShortcutKeys\Core\Enums\BindingSource;
use Aristonis\FilamentShortcutKeys\Core\Enums\MapType;
use Aristonis\FilamentShortcutKeys\Core\Resolution\ShortcutResolver;
use Aristonis\FilamentShortcutKeys\Core\Sets\CustomSet;
use Aristonis\FilamentShortcutKeys\Core\Sets\ShortcutSetRegistry;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\KeyCombo;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapData;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutBinding;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutTarget;
use Aristonis\FilamentShortcutKeys\Exceptions\UnknownShortcutSetException;
use Aristonis\FilamentShortcutKeys\Tests\Fakes\InMemoryMapRepository;
use Aristonis\FilamentShortcutKeys\Tests\Fakes\InMemoryNavigationProvider;
use Aristonis\FilamentShortcutKeys\Tests\Fakes\InMemoryPageContextProvider;

function resolverSet(string $key, ModifierScheme $modifier, array $bindings): ShortcutSet
{
    return new class($key, $modifier, $bindings) implements ShortcutSet
    {
        public function __construct(
            private string $key,
            private ModifierScheme $modifier,
            private array $bindings
        ) {}

        public function key(): string
        {
            return $this->key;
        }

        public function defaultModifier(): ModifierScheme
        {
            return $this->modifier;
        }

        public function discover(NavigationProvider $nav, PageContextProvider $page, string $panelId): array
        {
            return $this->bindings;
        }

        public function clientHandler(): string
        {
            return '';
        }
    };
}

function convention(string $set, string $structureKey, string $hint): ShortcutBinding
{
    return new ShortcutBinding(new ShortcutTarget($set, $structureKey), null, letterHint: $hint);
}

function resolveGroups(ShortcutSetRegistry $registry, array $overlay = []): array
{
    $nav = new InMemoryNavigationProvider([], 'v1');
    $page = new InMemoryPageContextProvider([], false);

    return (new ShortcutResolver($registry, $overlay))->resolve($nav, $page, 'admin')->groups();
}

function resolveWithMap(ShortcutSetRegistry $registry, array $entries, array $overlay = []): array
{
    $nav = new InMemoryNavigationProvider([], 'v1');
    $page = new InMemoryPageContextProvider([], false);
    $map = new MapData(type: MapType::CUSTOM, modifiers: null, entries: $entries, version: 1);
    $maps = new InMemoryMapRepository($map);

    return (new ShortcutResolver($registry, $overlay, $maps))
        ->resolve($nav, $page, 'admin', 'App\\Models\\User', '1')
        ->groups();
}

function bindingFor(array $groups, string $set, string $structureKey): ?ShortcutBinding
{
    foreach ($groups as $group) {
        foreach ($group->bindings as $binding) {
            if ($binding->target->set === $set && $binding->target->structureKey === $structureKey) {
                return $binding;
            }
        }
    }

    return null;
}

it('resolves convention bindings into per-set groups with assigned letters', function () {
    $registry = new ShortcutSetRegistry;
    $registry->register(resolverSet('navigation', ModifierScheme::altShift(), [
        convention('navigation', 'products', 'Products'),
        convention('navigation', 'payments', 'Payments'),
    ]));

    $groups = resolveGroups($registry);

    expect($groups)->toHaveCount(1)
        ->and($groups[0]->setKey)->toBe('navigation')
        ->and($groups[0]->modifier->equals(ModifierScheme::altShift()))->toBeTrue()
        ->and($groups[0]->bindings[0]->keyCombo->equals(KeyCombo::parse('alt+shift+p')))->toBeTrue()
        ->and($groups[0]->bindings[1]->keyCombo->equals(KeyCombo::parse('alt+shift+a')))->toBeTrue();
});

it('keeps sets with different modifier schemes in separate clash pools', function () {
    // both want letter P, but different schemes → no clash, both get P
    $registry = new ShortcutSetRegistry;
    $registry->register(resolverSet('navigation', ModifierScheme::altShift(), [
        convention('navigation', 'products', 'Products'),
    ]));
    $registry->register(resolverSet('global', ModifierScheme::alt(), [
        convention('global', 'print', 'Print'),
    ]));

    $groups = resolveGroups($registry);

    expect($groups)->toHaveCount(2)
        ->and($groups[0]->bindings[0]->keyCombo->equals(KeyCombo::parse('alt+shift+p')))->toBeTrue()
        ->and($groups[1]->bindings[0]->keyCombo->equals(KeyCombo::parse('alt+p')))->toBeTrue();
});

it('forces a config-overlay letter and frees the convention letter for others', function () {
    // products would convention to P; overlay forces it to R, so payments is free to take P.
    $registry = new ShortcutSetRegistry;
    $registry->register(resolverSet('navigation', ModifierScheme::altShift(), [
        convention('navigation', 'products', 'Products'),
        convention('navigation', 'payments', 'Payments'),
    ]));

    $groups = resolveGroups($registry, ['navigation:products' => ['letter' => 'R']]);

    $products = bindingFor($groups, 'navigation', 'products');
    $payments = bindingFor($groups, 'navigation', 'payments');

    expect($products->keyCombo->equals(KeyCombo::parse('alt+shift+r')))->toBeTrue()
        ->and($products->source)->toBe(BindingSource::OVERLAY)
        ->and($payments->keyCombo->equals(KeyCombo::parse('alt+shift+p')))->toBeTrue();
});

it('drops a config-overlay disabled shortcut from the resolved map', function () {
    $registry = new ShortcutSetRegistry;
    $registry->register(resolverSet('global', ModifierScheme::alt(), [
        convention('global', 'export', 'Export'),
        convention('global', 'print', 'Print'),
    ]));

    $groups = resolveGroups($registry, ['global:export' => ['disabled' => true]]);

    expect(bindingFor($groups, 'global', 'export'))->toBeNull()
        ->and(bindingFor($groups, 'global', 'print'))->not->toBeNull();
});

it('applies a user active-map remap and frees the convention letter', function () {
    // products would convention to P; the user's map remaps it to Z, freeing P for payments.
    $registry = new ShortcutSetRegistry;
    $registry->register(resolverSet('navigation', ModifierScheme::altShift(), [
        convention('navigation', 'products', 'Products'),
        convention('navigation', 'payments', 'Payments'),
    ]));

    $groups = resolveWithMap($registry, ['navigation:products' => ['letter' => 'z']]);

    $products = bindingFor($groups, 'navigation', 'products');
    $payments = bindingFor($groups, 'navigation', 'payments');

    expect($products->keyCombo->equals(KeyCombo::parse('alt+shift+z')))->toBeTrue()
        ->and($products->source)->toBe(BindingSource::USER)
        ->and($payments->keyCombo->equals(KeyCombo::parse('alt+shift+p')))->toBeTrue();
});

it('drops a user active-map disabled shortcut from the resolved map', function () {
    $registry = new ShortcutSetRegistry;
    $registry->register(resolverSet('global', ModifierScheme::alt(), [
        convention('global', 'export', 'Export'),
        convention('global', 'print', 'Print'),
    ]));

    $groups = resolveWithMap($registry, ['global:print' => ['disabled' => true]]);

    expect(bindingFor($groups, 'global', 'print'))->toBeNull()
        ->and(bindingFor($groups, 'global', 'export'))->not->toBeNull();
});

it('falls through to convention for targets the user map does not mention', function () {
    // map only touches products; payments is untouched → still convention-assigned.
    $registry = new ShortcutSetRegistry;
    $registry->register(resolverSet('navigation', ModifierScheme::altShift(), [
        convention('navigation', 'products', 'Products'),
        convention('navigation', 'payments', 'Payments'),
    ]));

    $groups = resolveWithMap($registry, ['navigation:products' => ['letter' => 'z']]);

    $payments = bindingFor($groups, 'navigation', 'payments');

    expect($payments->source)->toBe(BindingSource::CONVENTION)
        ->and($payments->keyCombo->equals(KeyCombo::parse('alt+shift+p')))->toBeTrue();
});

it('throws when a binding references an unregistered set', function () {
    $registry = new ShortcutSetRegistry;
    $registry->register(resolverSet('navigation', ModifierScheme::altShift(), [
        convention('ghost', 'thing', 'Thing'),
    ]));

    expect(fn () => resolveGroups($registry))
        ->toThrow(UnknownShortcutSetException::class);
});

it('ignores an invalid overlay letter and keeps the convention key', function () {
    $registry = new ShortcutSetRegistry;
    $registry->register(resolverSet('navigation', ModifierScheme::altShift(), [
        convention('navigation', 'products', 'Products'),
    ]));

    $groups = resolveGroups($registry, ['navigation:products' => ['letter' => 'enter']]);
    $products = bindingFor($groups, 'navigation', 'products');

    expect($products->keyCombo->equals(KeyCombo::parse('alt+shift+p')))->toBeTrue()
        ->and($products->source)->toBe(BindingSource::CONVENTION);
});

it('resolves a pool larger than the alphabet without emitting a null key', function () {
    $bindings = [];
    for ($i = 0; $i < 30; $i++) {
        $bindings[] = convention('navigation', "r{$i}", 'Item');
    }
    $registry = new ShortcutSetRegistry;
    $registry->register(resolverSet('navigation', ModifierScheme::altShift(), $bindings));

    $groups = resolveGroups($registry);

    expect($groups[0]->bindings)->toHaveCount(26);
    foreach ($groups[0]->bindings as $binding) {
        expect($binding->keyCombo)->not->toBeNull();
    }
});

it('adds a user custom binding from the active map', function () {
    $registry = new ShortcutSetRegistry;
    $registry->register(new CustomSet);

    $groups = resolveWithMap($registry, [
        'custom:deploy' => ['letter' => 'd', 'payload' => ['selector' => '#deploy']],
    ]);

    $binding = bindingFor($groups, 'custom', 'deploy');
    expect($binding)->not->toBeNull()
        ->and($binding->keyCombo->code)->toBe('KeyD')
        ->and($binding->keyCombo->modifiers->toString())->toBe('leader:g')
        ->and($binding->payload)->toBe(['selector' => '#deploy'])
        ->and($binding->source)->toBe(BindingSource::USER);
});

it('ignores a payload entry whose set this resolver does not own', function () {
    $registry = new ShortcutSetRegistry; // no CustomSet registered here

    $groups = resolveWithMap($registry, [
        'custom:deploy' => ['letter' => 'd', 'payload' => ['selector' => '#x']],
    ]);

    expect(bindingFor($groups, 'custom', 'deploy'))->toBeNull();
});

it('shares one letter pool so a custom binding cannot steal a nav letter', function () {
    $registry = new ShortcutSetRegistry;
    $registry->register(resolverSet('navigation', ModifierScheme::leader('g'), [convention('navigation', 'orders', 'orders')]));
    $registry->register(new CustomSet);

    $groups = resolveWithMap($registry, [
        'custom:special' => ['letter' => 'o', 'payload' => ['route' => '/x']],
    ]);

    expect(bindingFor($groups, 'custom', 'special')->keyCombo->code)->toBe('KeyO')
        ->and(bindingFor($groups, 'navigation', 'orders')->keyCombo->code)->not->toBe('KeyO');
});

it('ignores a payload entry whose set does not accept custom bindings', function () {
    // A set keyed 'custom' but without the AcceptsCustomBindings marker must not receive the payload.
    $registry = new ShortcutSetRegistry;
    $registry->register(resolverSet('custom', ModifierScheme::altShift(), []));

    $groups = resolveWithMap($registry, [
        'custom:deploy' => ['letter' => 'd', 'payload' => ['selector' => '#x']],
    ]);

    expect(bindingFor($groups, 'custom', 'deploy'))->toBeNull();
});

it('routes auto letters around keys reserved for their pool', function () {
    $registry = new ShortcutSetRegistry;
    $registry->register(resolverSet('global', ModifierScheme::none(), [convention('global', 'go-live', 'Go live')]));

    $nav = new InMemoryNavigationProvider([], 'v1');
    $page = new InMemoryPageContextProvider([], false);
    $groups = (new ShortcutResolver($registry, reservedCodes: ['' => ['KeyG']]))->resolve($nav, $page, 'admin')->groups();

    expect(bindingFor($groups, 'global', 'go-live')->keyCombo->code)->toBe('KeyO');
});

it('forces an overlay letter onto a bare set, so header-action pins keep working', function () {
    $registry = new ShortcutSetRegistry;
    $registry->register(resolverSet('global', ModifierScheme::none(), [convention('global', 'export', 'Export')]));

    $groups = resolveGroups($registry, ['global:export' => ['letter' => 'x']]);

    expect(bindingFor($groups, 'global', 'export')->keyCombo->code)->toBe('KeyX');
});

it('ignores an overlay letter that lands on a reserved key', function () {
    $registry = new ShortcutSetRegistry;
    $registry->register(resolverSet('global', ModifierScheme::none(), [convention('global', 'export', 'Export')]));

    $nav = new InMemoryNavigationProvider([], 'v1');
    $page = new InMemoryPageContextProvider([], false);
    $groups = (new ShortcutResolver($registry, ['global:export' => ['letter' => 'g']], reservedCodes: ['' => ['KeyG']]))
        ->resolve($nav, $page, 'admin')->groups();

    expect(bindingFor($groups, 'global', 'export')->keyCombo->code)->toBe('KeyE');
});

it('forces a letter onto a leader set', function () {
    $registry = new ShortcutSetRegistry;
    $registry->register(resolverSet('navigation', ModifierScheme::leader('g'), [convention('navigation', 'orders', 'Orders')]));

    $groups = resolveGroups($registry, ['navigation:orders' => ['letter' => 'x']]);

    expect(bindingFor($groups, 'navigation', 'orders')->keyCombo->equals(new KeyCombo(ModifierScheme::leader('g'), 'KeyX')))->toBeTrue();
});

it('turns on a shortcut the set left off when the overlay pins a letter to it', function () {
    $off = new ShortcutBinding(new ShortcutTarget('global', 'send'), null, false, letterHint: 'send');
    $registry = new ShortcutSetRegistry;
    $registry->register(resolverSet('global', ModifierScheme::none(), [$off]));

    expect(bindingFor(resolveGroups($registry), 'global', 'send'))->toBeNull()
        ->and(bindingFor(resolveGroups($registry, ['global:send' => ['letter' => 'x']]), 'global', 'send')->keyCombo->code)->toBe('KeyX');
});

it('keeps a pinned letter when a screen is added ahead of it in the sidebar', function () {
    // Without the pin, Partners takes P on its own first letter and Products is pushed to R.
    $resolve = function (array $items) {
        $registry = new ShortcutSetRegistry;
        $registry->register(resolverSet('navigation', ModifierScheme::leader('g'), $items));

        return bindingFor(resolveGroups($registry, ['navigation:products' => ['letter' => 'p']]), 'navigation', 'products');
    };

    $before = $resolve([convention('navigation', 'orders', 'Orders'), convention('navigation', 'products', 'Products')]);
    $after = $resolve([convention('navigation', 'partners', 'Partners'), convention('navigation', 'orders', 'Orders'), convention('navigation', 'products', 'Products')]);

    expect($before->keyCombo->code)->toBe('KeyP')
        ->and($after->keyCombo->code)->toBe('KeyP');
});

it('moves an unpinned letter when a screen is added ahead of it, which is why pinning exists', function () {
    $resolve = function (array $items) {
        $registry = new ShortcutSetRegistry;
        $registry->register(resolverSet('navigation', ModifierScheme::leader('g'), $items));

        return bindingFor(resolveGroups($registry), 'navigation', 'products');
    };

    expect($resolve([convention('navigation', 'products', 'Products')])->keyCombo->code)->toBe('KeyP')
        ->and($resolve([convention('navigation', 'partners', 'Partners'), convention('navigation', 'products', 'Products')])->keyCombo->code)->not->toBe('KeyP');
});

it('routes auto letters around keys claimed elsewhere but still lets a pin take one', function () {
    $registry = new ShortcutSetRegistry;
    $registry->register(resolverSet('global', ModifierScheme::none(), [
        convention('global', 'archive', 'Archive'),
        convention('global', 'export', 'Export'),
    ]));

    $nav = new InMemoryNavigationProvider([], 'v1');
    $page = new InMemoryPageContextProvider([], false);
    $groups = (new ShortcutResolver($registry, ['global:export' => ['letter' => 'a']], claimedCodes: ['' => ['KeyA']]))
        ->resolve($nav, $page, 'admin')->groups();

    expect(bindingFor($groups, 'global', 'archive')->keyCombo->code)->toBe('KeyR')
        ->and(bindingFor($groups, 'global', 'export')->keyCombo->code)->toBe('KeyA');
});
