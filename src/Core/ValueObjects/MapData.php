<?php

namespace Aristonis\FilamentShortcutKeys\Core\ValueObjects;

use Aristonis\FilamentShortcutKeys\Core\Enums\MapType;

/** A stored shortcut map: its type, optional per-set modifier overrides, override entries, and the row id + version that together form its cache identity. */
final readonly class MapData
{
    /**
     * @param  int|null  $id  the map's globally-unique DB row id; null for the neutral passthrough
     *                        map that has no backing row. Combined with type and version it forms the
     *                        cache identity, so two users sharing one map share one cache entry.
     */
    public function __construct(
        public MapType $type,
        public ?array $modifiers,
        public array $entries,
        public int $version,
        public ?int $id = null,
    ) {}
}
