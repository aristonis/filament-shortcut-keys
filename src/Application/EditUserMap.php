<?php

namespace Aristonis\FilamentShortcutKeys\Application;

use Aristonis\FilamentShortcutKeys\Core\Contracts\MapEditor;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapEntryEdit;
use Aristonis\FilamentShortcutKeys\Exceptions\EditingLockedException;
use Aristonis\FilamentShortcutKeys\Exceptions\InvalidCustomBindingException;

/**
 * Personal-mode editing of the acting user's shortcut map. Every operation is refused unless the panel
 * runs in `personal` mode, and each one forks the active map on first write so a user only ever edits
 * their own copy. Returns the id of the custom map that was written.
 */
final readonly class EditUserMap
{
    private const PERSONAL = 'personal';

    public function __construct(
        private MapEditor $editor,
        private string $mode,
    ) {}

    public function remap(string $authType, string $authId, string $panelId, string $target, string $letter): int
    {
        $this->guard($panelId);

        return $this->editor->saveEntry($authType, $authId, $panelId, new MapEntryEdit($target, $letter, false));
    }

    public function disable(string $authType, string $authId, string $panelId, string $target): int
    {
        $this->guard($panelId);

        return $this->editor->saveEntry($authType, $authId, $panelId, new MapEntryEdit($target, null, true));
    }

    public function enable(string $authType, string $authId, string $panelId, string $target): int
    {
        $this->guard($panelId);

        // Enabling reverts to convention by dropping the override, rather than storing a no-op row.
        return $this->editor->removeEntry($authType, $authId, $panelId, $target);
    }

    /**
     * @param  array{selector: string}|array{route: string}  $payload
     */
    public function addCustomBinding(string $authType, string $authId, string $panelId, string $target, string $letter, array $payload): int
    {
        $this->guard($panelId);

        if (! str_starts_with($target, 'custom:')) {
            throw InvalidCustomBindingException::forReason("target must be namespaced 'custom:'");
        }

        $this->validatePayload($payload);

        return $this->editor->saveEntry($authType, $authId, $panelId, new MapEntryEdit($target, $letter, false, $payload));
    }

    private function guard(string $panelId): void
    {
        if ($this->mode !== self::PERSONAL) {
            throw EditingLockedException::forPanel($panelId);
        }
    }

    /**
     * A custom binding may only point at a rendered element or an accessible route, never a raw
     * server action. Enforced by shape: exactly one non-empty `selector` or `route` string.
     *
     * @param  array<string, mixed>  $payload
     */
    private function validatePayload(array $payload): void
    {
        $keys = array_keys($payload);

        if ($keys !== ['selector'] && $keys !== ['route']) {
            throw InvalidCustomBindingException::forReason('payload must be exactly one of {selector} or {route}');
        }

        $value = $payload[$keys[0]];

        if (! is_string($value) || trim($value) === '') {
            throw InvalidCustomBindingException::forReason("{$keys[0]} must be a non-empty string");
        }

        // A route is navigated by the client, so it must stay in-app: a same-host absolute path. This
        // blocks off-site redirects and javascript: URIs regardless of the host app's own route guards.
        if ($keys[0] === 'route' && (! str_starts_with($value, '/') || str_starts_with($value, '//'))) {
            throw InvalidCustomBindingException::forReason('route must be a host-relative path starting with a single /');
        }
    }
}
