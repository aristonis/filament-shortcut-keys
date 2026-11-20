<?php

use Aristonis\FilamentShortcutKeys\Core\Enums\BindingSource;
use Aristonis\FilamentShortcutKeys\Core\Resolution\ResolvedGroup;
use Aristonis\FilamentShortcutKeys\Core\Resolution\ResolvedMap;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\KeyCombo;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutBinding;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutTarget;
use Aristonis\FilamentShortcutKeys\Filament\OverlayCatalog;

function overlayBinding(string $set, string $structureKey, string $code): ShortcutBinding
{
    return new ShortcutBinding(
        new ShortcutTarget($set, $structureKey),
        new KeyCombo(ModifierScheme::none(), $code),
        true,
        BindingSource::CONVENTION,
    );
}

function overlayGroup(array $groups, string $set): array
{
    return collect($groups)->firstWhere('set', $set);
}

it('labels every set from its own source and formats the keys', function () {
    $map = new ResolvedMap([
        new ResolvedGroup('navigation', ModifierScheme::altShift(), [overlayBinding('navigation', 'App\\OrderResource', 'KeyO')]),
        new ResolvedGroup('global', ModifierScheme::alt(), [overlayBinding('global', 'export', 'KeyE')]),
        new ResolvedGroup('table', ModifierScheme::none(), [overlayBinding('table', 'search', 'Slash')]),
    ]);

    $groups = OverlayCatalog::build(
        $map,
        ['App\\OrderResource' => 'Orders'],
        ['global:export' => 'Export'],
        ['search' => 'Search'],
    );

    expect(overlayGroup($groups, 'navigation')['rows'][0])->toMatchArray(['label' => 'Orders', 'keys' => ['Alt', 'Shift', 'O']])
        ->and(overlayGroup($groups, 'global')['rows'][0])->toMatchArray(['label' => 'Export', 'keys' => ['Alt', 'E']])
        ->and(overlayGroup($groups, 'table')['rows'][0])->toMatchArray(['label' => 'Search', 'keys' => ['/']]);
});

it('falls back to the structure key when a label is missing', function () {
    $map = new ResolvedMap([
        new ResolvedGroup('global', ModifierScheme::alt(), [overlayBinding('global', 'export', 'KeyE')]),
    ]);

    $groups = OverlayCatalog::build($map, [], [], []);

    expect($groups[0]['rows'][0]['label'])->toBe('export');
});

it('shows a g shortcut as two keys pressed one after the other', function () {
    $leader = ModifierScheme::leader('g');
    $map = new ResolvedMap([
        new ResolvedGroup('navigation', $leader, [
            new ShortcutBinding(new ShortcutTarget('navigation', 'App\\OrderResource'), new KeyCombo($leader, 'KeyO'), true, BindingSource::CONVENTION),
        ]),
        new ResolvedGroup('global', ModifierScheme::alt(), [overlayBinding('global', 'export', 'KeyE')]),
    ]);

    $groups = OverlayCatalog::build($map, ['App\\OrderResource' => 'Orders'], ['global:export' => 'Export'], []);

    expect(overlayGroup($groups, 'navigation')['rows'][0])->toMatchArray(['keys' => ['g', 'o'], 'sequence' => true])
        ->and(overlayGroup($groups, 'global')['rows'][0])->toMatchArray(['keys' => ['Alt', 'E'], 'sequence' => false]);
});

it('labels the Escape key the way keyboards print it', function () {
    $map = new ResolvedMap([
        new ResolvedGroup('table', ModifierScheme::none(), [overlayBinding('table', 'cursor-release', 'Escape')]),
    ]);

    $groups = OverlayCatalog::build($map, [], [], ['cursor-release' => 'Leave the row cursor']);

    expect(overlayGroup($groups, 'table')['rows'][0]['keys'])->toBe(['Esc']);
});

it('names an unlabeled row action the way Filament labels it', function () {
    $map = new ResolvedMap([
        new ResolvedGroup('row-action', ModifierScheme::none(), [overlayBinding('row-action', 'mark_paid', 'KeyM')]),
    ]);

    $groups = OverlayCatalog::build($map, [], [], []);

    expect($groups[0]['rows'][0]['label'])->toBe('Mark paid');
});

it('tells the cheatsheet which control a row needs before it is shown', function () {
    $map = new ResolvedMap([
        new ResolvedGroup('row-action', ModifierScheme::none(), [
            overlayBinding('row-action', 'approve', 'KeyA'),
            overlayBinding('row-action', 'reject', 'KeyR'),
        ]),
    ]);

    $groups = OverlayCatalog::build($map, [], [], [], ['row-action:reject' => '#reject']);

    expect(array_column($groups[0]['rows'], 'presence'))->toBe([null, '#reject']);
});
