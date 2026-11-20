<?php

use Aristonis\FilamentShortcutKeys\Application\AuthorSystemMap;
use Aristonis\FilamentShortcutKeys\Authorization\AuthorityGate;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapEntryEdit;
use Aristonis\FilamentShortcutKeys\Exceptions\AuthoringDeniedException;
use Aristonis\FilamentShortcutKeys\Exceptions\ConfirmationRequiredException;
use Aristonis\FilamentShortcutKeys\Tests\Fakes\InMemorySystemMapAuthor;
use Illuminate\Contracts\Auth\Authenticatable;

function gateAllowing(bool $allows): AuthorityGate
{
    return new class($allows) implements AuthorityGate
    {
        public function __construct(private bool $allows) {}

        public function allowsAuthoring(?Authenticatable $user, string $panelId): bool
        {
            return $this->allows;
        }
    };
}

function systemAuthor(bool $allows, InMemorySystemMapAuthor $author): AuthorSystemMap
{
    return new AuthorSystemMap(gateAllowing($allows), $author);
}

it('creates a preset when the gate grants authoring', function () {
    $author = new InMemorySystemMapAuthor(mapId: 5);

    $id = systemAuthor(true, $author)->createPreset(null, 'admin');

    expect($id)->toBe(5)
        ->and($author->created)->toHaveCount(1)
        ->and($author->created[0]['panelId'])->toBe('admin');
});

it('clones a preset when the gate grants authoring', function () {
    $author = new InMemorySystemMapAuthor(mapId: 8);

    $id = systemAuthor(true, $author)->clonePreset(null, 'admin', 3);

    expect($id)->toBe(8)
        ->and($author->cloned[0])->toBe(['panelId' => 'admin', 'sourceMapId' => 3]);
});

it('edits a system map in place once confirmed', function () {
    $author = new InMemorySystemMapAuthor;

    $id = systemAuthor(true, $author)->editInPlace(null, 'admin', 4, new MapEntryEdit('navigation:orders', 'o', false), confirmed: true);

    expect($id)->toBe(4)
        ->and($author->saved)->toHaveCount(1)
        ->and($author->saved[0]['mapId'])->toBe(4)
        ->and($author->saved[0]['edit']->letter)->toBe('o');
});

it('denies every authoring capability when the gate refuses', function () {
    $author = new InMemorySystemMapAuthor;
    $denied = systemAuthor(false, $author);

    expect(fn () => $denied->createPreset(null, 'admin'))->toThrow(AuthoringDeniedException::class);
    expect(fn () => $denied->clonePreset(null, 'admin', 1))->toThrow(AuthoringDeniedException::class);
    expect(fn () => $denied->editInPlace(null, 'admin', 1, new MapEntryEdit('navigation:orders', 'o', false), confirmed: true))->toThrow(AuthoringDeniedException::class);

    expect($author->created)->toBeEmpty()
        ->and($author->cloned)->toBeEmpty()
        ->and($author->saved)->toBeEmpty();
});

it('refuses an in-place edit without explicit confirmation', function () {
    $author = new InMemorySystemMapAuthor;
    $granted = systemAuthor(true, $author);

    expect(fn () => $granted->editInPlace(null, 'admin', 4, new MapEntryEdit('navigation:orders', 'o', false), confirmed: false))
        ->toThrow(ConfirmationRequiredException::class);

    expect($author->saved)->toBeEmpty();
});

it('makes a preset the panel default once confirmed', function () {
    $author = new InMemorySystemMapAuthor;

    systemAuthor(true, $author)->makeDefault(null, 'admin', 4, confirmed: true);

    expect($author->defaults[0])->toBe(['panelId' => 'admin', 'mapId' => 4]);
});

it('refuses to change the panel default without the gate or without confirmation', function () {
    $author = new InMemorySystemMapAuthor;

    expect(fn () => systemAuthor(false, $author)->makeDefault(null, 'admin', 4, confirmed: true))
        ->toThrow(AuthoringDeniedException::class);
    expect(fn () => systemAuthor(true, $author)->makeDefault(null, 'admin', 4, confirmed: false))
        ->toThrow(ConfirmationRequiredException::class);
    expect($author->defaults)->toBeEmpty();
});
