<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Fakes;

use Aristonis\FilamentShortcutKeys\Core\Contracts\SystemMapAuthor;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapEntryEdit;

/** Records the authoring calls an AuthorSystemMap issues, so a unit test can assert on them without a database. */
final class InMemorySystemMapAuthor implements SystemMapAuthor
{
    /** @var array<int, array{panelId: string, modifiers: array<string, mixed>|null}> */
    public array $created = [];

    /** @var array<int, array{panelId: string, sourceMapId: int}> */
    public array $cloned = [];

    /** @var array<int, array{panelId: string, mapId: int, edit: MapEntryEdit}> */
    public array $saved = [];

    /** @var array<int, array{panelId: string, mapId: int, target: string}> */
    public array $removed = [];

    public function __construct(public int $mapId = 99) {}

    public function createPreset(string $panelId, ?array $modifiers = null): int
    {
        $this->created[] = compact('panelId', 'modifiers');

        return $this->mapId;
    }

    public function clonePreset(string $panelId, int $sourceMapId): int
    {
        $this->cloned[] = compact('panelId', 'sourceMapId');

        return $this->mapId;
    }

    public function saveEntry(string $panelId, int $mapId, MapEntryEdit $edit): int
    {
        $this->saved[] = compact('panelId', 'mapId', 'edit');

        return $mapId;
    }

    public function removeEntry(string $panelId, int $mapId, string $target): int
    {
        $this->removed[] = compact('panelId', 'mapId', 'target');

        return $mapId;
    }
}
