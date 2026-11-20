<?php

use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\ReservesKeys;
use Aristonis\FilamentShortcutKeys\Core\Contracts\ShortcutSet;
use Aristonis\FilamentShortcutKeys\Core\Resolution\KeyReservations;
use Aristonis\FilamentShortcutKeys\Core\Sets\GlobalSet;
use Aristonis\FilamentShortcutKeys\Core\Sets\NavigationSet;
use Aristonis\FilamentShortcutKeys\Core\Sets\TableSet;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;

it('reserves the leader key among bare keys, so no bare shortcut can take it', function () {
    $reserved = KeyReservations::for([new NavigationSet(ModifierScheme::leader('g')), new GlobalSet(ModifierScheme::none())]);

    expect($reserved[''])->toContain('KeyG');
});

it('reserves the table keys in the table pool only', function () {
    $reserved = KeyReservations::for([new TableSet(modifier: ModifierScheme::none()), new GlobalSet(held('alt'))]);

    expect($reserved[''])->toContain('KeyJ', 'KeyK', 'Slash')
        ->and($reserved['alt'] ?? [])->not->toContain('KeyJ');
});

it('follows the table to whatever pool it was configured onto', function () {
    $reserved = KeyReservations::for([new TableSet(modifier: held('alt'))]);

    expect($reserved['alt'])->toContain('KeyJ')
        ->and($reserved[''] ?? [])->not->toContain('KeyJ');
});

it('lets a third-party set claim keys by implementing ReservesKeys', function () {
    $set = new class implements ReservesKeys, ShortcutSet
    {
        public function key(): string
        {
            return 'vendor';
        }

        public function defaultModifier(): ModifierScheme
        {
            return ModifierScheme::none();
        }

        public function discover(NavigationProvider $navigationProvider, PageContextProvider $pageContextProvider, string $panelId): array
        {
            return [];
        }

        public function clientHandler(): string
        {
            return 'vendor';
        }

        public function reservedCodes(): array
        {
            return ['KeyQ'];
        }
    };

    expect(KeyReservations::for([$set])[''])->toBe(['KeyQ']);
});
