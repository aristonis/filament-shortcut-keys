<?php

namespace Aristonis\FilamentShortcutKeys\Core\ValueObjects;

/**
 * The desired state of one override row: a remapped letter, a disabled flag, and an optional custom
 * binding payload. The editor upserts a map's row for the target to exactly this state.
 */
final readonly class MapEntryEdit
{
    /**
     * @param  array{selector: string}|array{route: string}|null  $payload  a custom binding's action,
     *                                                                      null for a plain override
     */
    public function __construct(
        public string $target,
        public ?string $letter,
        public bool $disabled,
        public ?array $payload = null,
    ) {}
}
