<?php

namespace Aristonis\FilamentShortcutKeys\Persistence;

use Aristonis\FilamentShortcutKeys\Models\ShortcutMap;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMapEntry;

/**
 * Copies a map's override rows onto a new map. Shared by forking a user's copy and cloning a preset,
 * so the two can never disagree on which columns make up an override.
 *
 * @internal
 */
trait CopiesMapEntries
{
    private function copyEntries(ShortcutMap $from, ShortcutMap $to): void
    {
        $rows = $from->entries
            ->map(fn (ShortcutMapEntry $entry): array => [
                'target' => $entry->target,
                'letter' => $entry->letter,
                'disabled' => $entry->disabled,
                'payload' => $entry->payload,
            ])
            ->all();

        if ($rows !== []) {
            $to->entries()->createMany($rows);
        }
    }
}
