<?php

use Aristonis\FilamentShortcutKeys\Core\Enums\TargetVerdict;
use Aristonis\FilamentShortcutKeys\Core\Maintenance\TargetAudit;

function audit(): TargetAudit
{
    return new TargetAudit([
        'navigation' => ['App\\Filament\\Resources\\OrderResource'],
        'row-action' => ['approve'],
        'table' => ['search', 'page-next'],
    ]);
}

it('calls a target live when its set still registers that structure key', function () {
    expect(audit()->verdict('navigation:App\\Filament\\Resources\\OrderResource'))->toBe(TargetVerdict::LIVE)
        ->and(audit()->verdict('row-action:approve'))->toBe(TargetVerdict::LIVE)
        ->and(audit()->verdict('table:search'))->toBe(TargetVerdict::LIVE);
});

it('calls a target orphaned when its set no longer registers that structure key', function () {
    expect(audit()->verdict('navigation:App\\Filament\\Resources\\GoneResource'))->toBe(TargetVerdict::ORPHANED)
        ->and(audit()->verdict('row-action:archive'))->toBe(TargetVerdict::ORPHANED)
        ->and(audit()->verdict('table:teleport'))->toBe(TargetVerdict::ORPHANED);
});

it('leaves a target unverifiable when its set cannot be enumerated outside a page request', function () {
    expect(audit()->verdict('custom:reports'))->toBe(TargetVerdict::UNVERIFIABLE)
        ->and(audit()->verdict('global:export'))->toBe(TargetVerdict::UNVERIFIABLE)
        ->and(audit()->verdict('page:refresh'))->toBe(TargetVerdict::UNVERIFIABLE);
});

it('leaves a malformed target unverifiable rather than guessing it is dead', function () {
    expect(audit()->verdict('navigation'))->toBe(TargetVerdict::UNVERIFIABLE)
        ->and(audit()->verdict(''))->toBe(TargetVerdict::UNVERIFIABLE);
});

it('keeps a structure key containing colons intact', function () {
    $audit = new TargetAudit(['custom' => ['reports:monthly']]);

    expect($audit->verdict('custom:reports:monthly'))->toBe(TargetVerdict::LIVE)
        ->and($audit->verdict('custom:reports:weekly'))->toBe(TargetVerdict::ORPHANED);
});
