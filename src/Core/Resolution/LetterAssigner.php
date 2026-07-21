<?php

namespace Aristonis\FilamentShortcutKeys\Core\Resolution;

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\KeyCombo;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutBinding;

class LetterAssigner
{
    public function assign(ModifierScheme $scheme, array $bindings): array
    {
        $comboKey = array_filter($bindings, fn ($binding) => $binding->keyCombo !== null);
        $taken = array_map(fn ($binding) => $binding->keyCombo->code, $comboKey);
        $result = [];
        foreach ($bindings as $binding) {
            if ($binding->keyCombo !== null) {
                $result[] = $binding;

                continue;
            }
            $code = $this->pickCode($binding->letterHint, $taken);
            if ($code === null) {
                // nothing left to assign
                $result[] = $binding;

                continue;
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

    private function pickCode($hint, array $taken): ?string
    {
        preg_match_all('/[a-z]/', strtolower($hint), $matches);
        $fromHint = $matches[0];

        foreach ($fromHint as $latter) {
            $code = 'Key' . strtoupper($latter);
            if (! in_array($code, $taken)) {
                return $code;
            }
        }
        $candidates = array_merge($matches[0], range('a', 'z'));
        foreach ($candidates as $letter) {
            $code = 'Key' . strtoupper($letter);
            if (! in_array($code, $taken)) {
                return $code;
            }
        }

        return null;
    }
}
