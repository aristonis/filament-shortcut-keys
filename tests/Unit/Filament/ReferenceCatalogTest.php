<?php

use Aristonis\FilamentShortcutKeys\Core\Enums\BindingSource;
use Aristonis\FilamentShortcutKeys\Core\Resolution\ResolvedGroup;
use Aristonis\FilamentShortcutKeys\Core\Resolution\ResolvedMap;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\KeyCombo;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\NavItem;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutBinding;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutTarget;
use Aristonis\FilamentShortcutKeys\Filament\ReferenceCatalog;

function catalogBinding(string $set, string $structureKey, ?string $code, bool $enabled = true): ShortcutBinding
{
    return new ShortcutBinding(
        new ShortcutTarget($set, $structureKey),
        $code === null ? null : new KeyCombo(ModifierScheme::altShift(), $code),
        $enabled,
        BindingSource::CONVENTION,
    );
}

it('labels a navigation binding from the matching nav item and formats its keys', function () {
    $map = new ResolvedMap([
        new ResolvedGroup('navigation', ModifierScheme::altShift(), [
            catalogBinding('navigation', 'App\\OrderResource', 'KeyO'),
        ]),
    ]);

    $groups = ReferenceCatalog::build($map, [new NavItem('App\\OrderResource', 'Orders', '/admin/orders')]);

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['set'])->toBe('navigation')
        ->and($groups[0]['rows'][0]['label'])->toBe('Orders')
        ->and($groups[0]['rows'][0]['keys'])->toBe(['Alt', 'Shift', 'O']);
});

it('falls back to the structure key when no nav item matches', function () {
    $map = new ResolvedMap([
        new ResolvedGroup('navigation', ModifierScheme::altShift(), [
            catalogBinding('navigation', 'App\\GhostResource', 'KeyG'),
        ]),
    ]);

    $groups = ReferenceCatalog::build($map, []);

    expect($groups[0]['rows'][0]['label'])->toBe('App\\GhostResource');
});

it('uses a custom binding structure key as its label', function () {
    $map = new ResolvedMap([
        new ResolvedGroup('custom', ModifierScheme::altShift(), [
            catalogBinding('custom', 'reports', 'KeyR'),
        ]),
    ]);

    $groups = ReferenceCatalog::build($map, []);

    expect($groups[0]['set'])->toBe('custom')
        ->and($groups[0]['rows'][0]['label'])->toBe('reports')
        ->and($groups[0]['rows'][0]['keys'])->toBe(['Alt', 'Shift', 'R']);
});

it('skips disabled and unassigned bindings, dropping a group left empty', function () {
    $map = new ResolvedMap([
        new ResolvedGroup('navigation', ModifierScheme::altShift(), [
            catalogBinding('navigation', 'App\\OrderResource', 'KeyO', enabled: false),
            catalogBinding('navigation', 'App\\UserResource', null),
        ]),
    ]);

    expect(ReferenceCatalog::build($map, []))->toBe([]);
});
