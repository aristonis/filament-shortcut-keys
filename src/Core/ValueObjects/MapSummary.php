<?php

namespace Aristonis\FilamentShortcutKeys\Core\ValueObjects;

use Aristonis\FilamentShortcutKeys\Core\Enums\MapType;

/**
 * A selectable map as the manager UI sees it: which one it is, whether it is the owner's active
 * choice, and whether it is the panel's default. Presets carry no name yet, so the UI labels them by
 * type and order.
 */
final readonly class MapSummary
{
    public function __construct(
        public int $id,
        public MapType $type,
        public bool $isActive,
        public bool $isDefault,
    ) {}
}
