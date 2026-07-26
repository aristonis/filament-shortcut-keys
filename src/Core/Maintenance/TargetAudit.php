<?php

namespace Aristonis\FilamentShortcutKeys\Core\Maintenance;

use Aristonis\FilamentShortcutKeys\Core\Enums\TargetVerdict;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutTarget;

/**
 * Judges a stored override's target against the sets that can be enumerated for a panel. A set the
 * caller did not supply is never judged dead: page-scoped sets only exist while a page is rendering,
 * so a maintenance pass cannot tell "removed" from "not on this page".
 */
final class TargetAudit
{
    /** @param  array<string, string[]>  $liveTargets  set key => structure keys the panel still registers */
    public function __construct(private array $liveTargets) {}

    public function verdict(string $target): TargetVerdict
    {
        $parsed = self::parse($target);

        if ($parsed === null || ! array_key_exists($parsed->set, $this->liveTargets)) {
            return TargetVerdict::UNVERIFIABLE;
        }

        return in_array($parsed->structureKey, $this->liveTargets[$parsed->set], true)
            ? TargetVerdict::LIVE
            : TargetVerdict::ORPHANED;
    }

    /** Splits on the first colon only, so a structure key may contain colons of its own. */
    private static function parse(string $target): ?ShortcutTarget
    {
        $separator = strpos($target, ':');

        if ($separator === false || $separator === 0 || $separator === strlen($target) - 1) {
            return null;
        }

        return new ShortcutTarget(substr($target, 0, $separator), substr($target, $separator + 1));
    }
}
