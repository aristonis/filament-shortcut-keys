<?php

namespace Aristonis\FilamentShortcutKeys\Authorization;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/**
 * Config-backed authoring gate reading `shortcut-keys.authorize`. Supported forms:
 *
 *   - null / absent → deny (the safe default).
 *   - Closure(?Authenticatable $user, string $panelId): bool → its boolean result.
 *   - string (a Gate ability name) → the ability, checked for the given user; denied when unauthenticated.
 */
final readonly class ConfigAuthorityGate implements AuthorityGate
{
    private const CONFIG_KEY = 'shortcut-keys.authorize';

    public function allowsAuthoring(?Authenticatable $user, string $panelId): bool
    {
        $rule = config(self::CONFIG_KEY);

        if ($rule instanceof Closure) {
            // Strict true only: a truthy non-bool (e.g. 1, 'yes') must not silently grant authoring.
            return $rule($user, $panelId) === true;
        }

        if (is_string($rule)) {
            // A named Gate ability is always user-scoped: no user means no authority.
            return $user !== null && Gate::forUser($user)->allows($rule, $panelId);
        }

        return false;
    }
}
