<?php

use Aristonis\FilamentShortcutKeys\Core\Enums\BindingSource;
use Aristonis\FilamentShortcutKeys\Core\Enums\MapType;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\KeyCombo;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutBinding;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutTarget;

it('exposes map types as string values', function () {
    expect(MapType::SYSTEM->value)->toBe('system')
        ->and(MapType::CUSTOM->value)->toBe('custom')
        ->and(MapType::from('system'))->toBe(MapType::SYSTEM);
});

it('exposes binding sources as string values', function () {
    expect(BindingSource::CONVENTION->value)->toBe('convention')
        ->and(BindingSource::OVERLAY->value)->toBe('overlay')
        ->and(BindingSource::USER->value)->toBe('user')
        ->and(BindingSource::CUSTOM->value)->toBe('custom');
});

it('holds a binding with sensible defaults', function () {
    $binding = new ShortcutBinding(
        target: new ShortcutTarget('global', 'export'),
        keyCombo: KeyCombo::parse('alt+e'),
    );

    expect($binding->target->identity())->toBe('global:export')
        ->and($binding->keyCombo->code)->toBe('KeyE')
        ->and($binding->enabled)->toBeTrue()
        ->and($binding->source)->toBe(BindingSource::CONVENTION);
});

it('allows a binding with no key combo yet', function () {
    $binding = new ShortcutBinding(
        target: new ShortcutTarget('navigation', 'App\\X'),
        keyCombo: null,
    );

    expect($binding->keyCombo)->toBeNull();
});
