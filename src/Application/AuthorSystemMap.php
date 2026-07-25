<?php

namespace Aristonis\FilamentShortcutKeys\Application;

use Aristonis\FilamentShortcutKeys\Authorization\AuthorityGate;
use Aristonis\FilamentShortcutKeys\Core\Contracts\SystemMapAuthor;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapEntryEdit;
use Aristonis\FilamentShortcutKeys\Exceptions\AuthoringDeniedException;
use Aristonis\FilamentShortcutKeys\Exceptions\ConfirmationRequiredException;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Gated authoring of a panel's shared system maps (the elevated "map manager" capability). Every
 * operation is refused unless the developer's authority gate grants it. An in-place edit changes the
 * map for every admin using it, so it additionally demands an explicit confirmation flag and cannot
 * happen by accident. Each method returns the id of the affected system map.
 */
final readonly class AuthorSystemMap
{
    public function __construct(
        private AuthorityGate $gate,
        private SystemMapAuthor $author,
    ) {}

    public function createPreset(?Authenticatable $user, string $panelId, ?array $modifiers = null): int
    {
        $this->authorize($user, $panelId);

        return $this->author->createPreset($panelId, $modifiers);
    }

    public function clonePreset(?Authenticatable $user, string $panelId, int $sourceMapId): int
    {
        $this->authorize($user, $panelId);

        return $this->author->clonePreset($panelId, $sourceMapId);
    }

    public function editInPlace(?Authenticatable $user, string $panelId, int $mapId, MapEntryEdit $edit, bool $confirmed): int
    {
        $this->authorize($user, $panelId);
        $this->requireConfirmation($mapId, $confirmed);

        return $this->author->saveEntry($panelId, $mapId, $edit);
    }

    public function revertInPlace(?Authenticatable $user, string $panelId, int $mapId, string $target, bool $confirmed): int
    {
        $this->authorize($user, $panelId);
        $this->requireConfirmation($mapId, $confirmed);

        return $this->author->removeEntry($panelId, $mapId, $target);
    }

    private function authorize(?Authenticatable $user, string $panelId): void
    {
        if (! $this->gate->allowsAuthoring($user, $panelId)) {
            throw AuthoringDeniedException::forPanel($panelId);
        }
    }

    private function requireConfirmation(int $mapId, bool $confirmed): void
    {
        if (! $confirmed) {
            throw ConfirmationRequiredException::forSystemMap($mapId);
        }
    }
}
