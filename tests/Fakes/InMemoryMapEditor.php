<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Fakes;

use Aristonis\FilamentShortcutKeys\Core\Contracts\MapEditor;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapEntryEdit;

/** Records the edits an EditUserMap issues, so a unit test can assert on them without a database. */
final class InMemoryMapEditor implements MapEditor
{
    /** @var array<int, array{authType: string, authId: string, panelId: string, edit: MapEntryEdit}> */
    public array $saved = [];

    /** @var array<int, array{authType: string, authId: string, panelId: string, target: string}> */
    public array $removed = [];

    public function __construct(public int $mapId = 42) {}

    public function saveEntry(string $authType, string $authId, string $panelId, MapEntryEdit $edit): int
    {
        $this->saved[] = compact('authType', 'authId', 'panelId', 'edit');

        return $this->mapId;
    }

    public function removeEntry(string $authType, string $authId, string $panelId, string $target): int
    {
        $this->removed[] = compact('authType', 'authId', 'panelId', 'target');

        return $this->mapId;
    }
}
