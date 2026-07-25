<?php

namespace Aristonis\FilamentShortcutKeys\Exceptions;

final class SystemMapPayloadNotAllowedException extends ShortcutKeysException
{
    public static function forTarget(string $target): self
    {
        return new self(
            "Editing a system map supports remap and disable only; a custom-binding payload is not permitted (target: {$target})",
            10007,
            'SYSTEM_MAP_PAYLOAD_NOT_ALLOWED',
        );
    }
}
