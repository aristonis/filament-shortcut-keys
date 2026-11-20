<?php

namespace Aristonis\FilamentShortcutKeys\Filament;

use Aristonis\FilamentShortcutKeys\Core\Resolution\ResolvedMap;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutTarget;
use Illuminate\Support\Str;

/**
 * Flattens the full per-page map into labeled rows for the cheatsheet overlay, pulling each set's
 * labels from its own source. Unlike the reference page, it includes the current page's header actions.
 */
final class OverlayCatalog
{
    /**
     * @param  array<string, string>  $navLabels  navigation structure key => label
     * @param  array<string, string>  $actionLabels  target identity => label, for the action-backed sets
     * @param  array<string, string>  $behaviorLabels  table behavior key => label
     * @param  array<string, string>  $presenceSelectors  target identity => the control the page must have
     *                                                    for the row to show; for keys the server could not
     *                                                    check, such as row actions on a nested table
     * @return array<int, array{set: string, rows: array<int, array{label: string, keys: string[], sequence: bool, presence: string|null}>}>
     */
    public static function build(ResolvedMap $map, array $navLabels, array $actionLabels, array $behaviorLabels, array $presenceSelectors = []): array
    {
        $groups = [];

        foreach ($map->groups() as $group) {
            $rows = [];

            foreach ($group->bindings as $binding) {
                $rows[] = [
                    'label' => self::labelFor($group->setKey, $binding->target, $navLabels, $actionLabels, $behaviorLabels),
                    'keys' => KeyLabel::tokens($group->modifier, $binding->keyCombo->code),
                    'sequence' => KeyLabel::isSequence($group->modifier),
                    'presence' => $presenceSelectors[$binding->target->identity()] ?? null,
                ];
            }

            if ($rows !== []) {
                $groups[] = ['set' => $group->setKey, 'rows' => $rows];
            }
        }

        return $groups;
    }

    /**
     * @param  array<string, string>  $navLabels
     * @param  array<string, string>  $actionLabels
     * @param  array<string, string>  $behaviorLabels
     */
    private static function labelFor(
        string $set,
        ShortcutTarget $target,
        array $navLabels,
        array $actionLabels,
        array $behaviorLabels,
    ): string {
        return match ($set) {
            'navigation' => $navLabels[$target->structureKey] ?? $target->structureKey,
            'table' => $behaviorLabels[$target->structureKey] ?? $target->structureKey,
            'global' => $actionLabels[$target->identity()] ?? $target->structureKey,
            // A row action on a nested table has no label the page can read, so it gets the one
            // Filament derives from the name when the action sets none.
            'row-action' => $actionLabels[$target->identity()] ?? Str::of($target->structureKey)->kebab()->replace(['-', '_'], ' ')->ucfirst()->toString(),
            default => $target->structureKey,
        };
    }
}
