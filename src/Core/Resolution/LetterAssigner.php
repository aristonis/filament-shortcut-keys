<?php

namespace Aristonis\FilamentShortcutKeys\Core\Resolution;

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\KeyCombo;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutBinding;

/**
 * Assigns a letter to every unset binding in one clash pool (bindings sharing a modifier scheme),
 * keeping the whole pool free of duplicate keys.
 *
 * Bindings that already carry a combo (forced by config or a user map) are reserved first so an
 * auto-assigned letter never steals a forced key. A binding is dropped when its key can't be made
 * unique — a duplicate forced key, or a pool larger than the 26 letters can hold — because a
 * shortcut with no unique key can't fire. Auto-assignment follows input order (sidebar order).
 */
final readonly class LetterAssigner
{
    public function assign(ModifierScheme $scheme, array $bindings): array
    {
        $taken = $this->reservedCodes($bindings);
        $keptCodes = [];
        $result = [];

        foreach ($bindings as $binding) {
            if ($binding->keyCombo !== null) {
                $code = $binding->keyCombo->code;
                if (in_array($code, $keptCodes, true)) {
                    continue; // a later binding on an already-claimed key — drop the duplicate
                }
                $keptCodes[] = $code;
                $result[] = $binding;

                continue;
            }

            $code = $this->pickCode($binding->letterHint, $taken);
            if ($code === null) {
                continue; // pool has outgrown the 26 letters — leave this one keyless (dropped)
            }

            $taken[] = $code;
            $result[] = new ShortcutBinding(
                $binding->target,
                new KeyCombo($scheme->ctrl, $scheme->alt, $scheme->shift, $scheme->meta, $code),
                $binding->enabled,
                $binding->source,
                $binding->letterHint,
            );
        }

        return $result;
    }

    /** Codes already fixed on bindings, so auto-assignment routes around forced keys. */
    private function reservedCodes(array $bindings): array
    {
        $codes = [];
        foreach ($bindings as $binding) {
            if ($binding->keyCombo !== null && ! in_array($binding->keyCombo->code, $codes, true)) {
                $codes[] = $binding->keyCombo->code;
            }
        }

        return $codes;
    }

    private function pickCode(?string $hint, array $taken): ?string
    {
        preg_match_all('/[a-z]/', strtolower($hint ?? ''), $matches);
        $candidates = array_merge($matches[0], range('a', 'z'));

        foreach ($candidates as $letter) {
            $code = 'Key' . strtoupper($letter);
            if (! in_array($code, $taken, true)) {
                return $code;
            }
        }

        return null;
    }
}
