<?php

namespace Aristonis\FilamentShortcutKeys\Filament;

use Aristonis\FilamentShortcutKeys\Core\Resolution\ResolvedMap;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutTarget;

/**
 * Flattens the full per-page map into labeled rows for the cheatsheet overlay. Unlike the reference
 * page, it covers every set (actions, table, custom, page), pulling each set's labels from its own
 * source.
 */
final class OverlayCatalog
{
    /**
     * @param  array<string, string>  $navLabels  navigation structure key => label
     * @param  array<string, string>  $actionLabels  target identity => label, for the action-backed sets
     * @param  array<string, string>  $behaviorLabels  table behavior key => label
     * @return array<int, array{set: string, rows: array<int, array{label: string, keys: string[]}>}>
     */
    public static function build(ResolvedMap $map, array $navLabels, array $actionLabels, array $behaviorLabels): array
    {
        $groups = [];

        foreach ($map->groups() as $group) {
            $rows = [];

            foreach ($group->bindings as $binding) {
                if (! $binding->enabled || $binding->keyCombo === null) {
                    continue;
                }

                $rows[] = [
                    'label' => self::labelFor($group->setKey, $binding->target, $navLabels, $actionLabels, $behaviorLabels),
                    'keys' => KeyLabel::tokens($group->modifier, $binding->keyCombo->code),
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
            'global', 'row-action' => $actionLabels[$target->identity()] ?? $target->structureKey,
            default => $target->structureKey,
        };
    }
}
