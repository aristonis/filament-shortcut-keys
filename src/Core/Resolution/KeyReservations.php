<?php

namespace Aristonis\FilamentShortcutKeys\Core\Resolution;

use Aristonis\FilamentShortcutKeys\Core\Contracts\ReservesKeys;
use Aristonis\FilamentShortcutKeys\Core\Contracts\ShortcutSet;
use Aristonis\FilamentShortcutKeys\Exceptions\InvalidModifierSchemeException;

/**
 * The keys no auto-assigned letter may take, per pool. Built from every set in both registries at
 * once: the leader key lives in the navigation set's registry but has to be kept out of the bare
 * pool that header and row actions share in the other one.
 */
final class KeyReservations
{
    /**
     * @param  ShortcutSet[]  $sets
     * @return array<string, string[]> key codes by pool name (ModifierScheme::toString())
     */
    public static function for(array $sets): array
    {
        $reserved = [];

        foreach ($sets as $set) {
            $scheme = $set->defaultModifier();

            if ($set instanceof ReservesKeys) {
                $reserved[$scheme->toString()] = [...$reserved[$scheme->toString()] ?? [], ...$set->reservedCodes()];
            }

            // The leader is itself pressed bare, so a bare shortcut on the same key could never fire.
            if ($scheme->leader !== null) {
                $reserved[''] = [...$reserved[''] ?? [], $scheme->leader];
            }
        }

        $reserved = array_map(fn (array $codes) => array_values(array_unique($codes)), $reserved);
        self::assertLeadersFree($sets);

        return $reserved;
    }

    /**
     * A leader on a key another set already binds bare (the table's j, say) would start a wait instead
     * of doing its job, so it is refused rather than left to shadow that key.
     *
     * @param  ShortcutSet[]  $sets
     */
    private static function assertLeadersFree(array $sets): void
    {
        $bareCodes = [];
        $leaders = [];

        foreach ($sets as $set) {
            $scheme = $set->defaultModifier();

            if ($set instanceof ReservesKeys && $scheme->toString() === '') {
                $bareCodes = [...$bareCodes, ...$set->reservedCodes()];
            }

            if ($scheme->leader !== null) {
                $leaders[] = $scheme->leader;
            }
        }

        foreach ($leaders as $leader) {
            if (in_array($leader, $bareCodes, true)) {
                throw InvalidModifierSchemeException::forReservedLeader($leader);
            }
        }
    }
}
