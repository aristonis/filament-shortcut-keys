<?php

use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\ShortcutSet;
use Aristonis\FilamentShortcutKeys\Core\Enums\BindingSource;
use Aristonis\FilamentShortcutKeys\Core\Enums\MapType;
use Aristonis\FilamentShortcutKeys\Core\Resolution\ShortcutResolver;
use Aristonis\FilamentShortcutKeys\Core\Sets\ShortcutSetRegistry;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\KeyCombo;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapData;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutBinding;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutTarget;
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

it('forces a config-overlay letter and frees the convention letter for others (AC-13)', function () {
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

it('drops a config-overlay disabled shortcut from the resolved map (AC-13)', function () {
    $registry = new ShortcutSetRegistry;
    $registry->register(resolverSet('global', ModifierScheme::alt(), [
        convention('global', 'export', 'Export'),
        convention('global', 'print', 'Print'),
    ]));

    $groups = resolveGroups($registry, ['global:export' => ['disabled' => true]]);

    expect(bindingFor($groups, 'global', 'export'))->toBeNull()
        ->and(bindingFor($groups, 'global', 'print'))->not->toBeNull();
});

it('applies a user active-map remap and frees the convention letter (AC-9)', function () {
    // products would convention to P; the user's map remaps it to Z, freeing P for payments.
    $registry = new ShortcutSetRegistry;
    $registry->register(resolverSet('navigation', ModifierScheme::altShift(), [
        convention('navigation', 'products', 'Products'),
        convention('navigation', 'payments', 'Payments'),
    ]));

    $groups = resolveWithMap($registry, ['navigation:products' => ['key' => 'alt+shift+z']]);

    $products = bindingFor($groups, 'navigation', 'products');
    $payments = bindingFor($groups, 'navigation', 'payments');

    expect($products->keyCombo->equals(KeyCombo::parse('alt+shift+z')))->toBeTrue()
        ->and($products->source)->toBe(BindingSource::USER)
        ->and($payments->keyCombo->equals(KeyCombo::parse('alt+shift+p')))->toBeTrue();
});

it('drops a user active-map disabled shortcut from the resolved map (AC-9)', function () {
    $registry = new ShortcutSetRegistry;
    $registry->register(resolverSet('global', ModifierScheme::alt(), [
        convention('global', 'export', 'Export'),
        convention('global', 'print', 'Print'),
    ]));

    $groups = resolveWithMap($registry, ['global:print' => ['disabled' => true]]);

    expect(bindingFor($groups, 'global', 'print'))->toBeNull()
        ->and(bindingFor($groups, 'global', 'export'))->not->toBeNull();
});

it('falls through to convention for targets the user map does not mention (FR-26)', function () {
    // map only touches products; payments is untouched → still convention-assigned.
    $registry = new ShortcutSetRegistry;
    $registry->register(resolverSet('navigation', ModifierScheme::altShift(), [
        convention('navigation', 'products', 'Products'),
        convention('navigation', 'payments', 'Payments'),
    ]));

    $groups = resolveWithMap($registry, ['navigation:products' => ['key' => 'alt+shift+z']]);

    $payments = bindingFor($groups, 'navigation', 'payments');

    expect($payments->source)->toBe(BindingSource::CONVENTION)
        ->and($payments->keyCombo->equals(KeyCombo::parse('alt+shift+p')))->toBeTrue();
});
