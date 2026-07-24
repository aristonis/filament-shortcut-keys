<?php

namespace Aristonis\FilamentShortcutKeys\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The developer's authoring gate: may this user edit a panel's SYSTEM shortcut maps? This is an
 * authoring capability check, not per-shortcut permission filtering — the resolved keymap itself is
 * public. Default posture is deny; access must be granted explicitly via configuration.
 *
 * WARNING (Gate-ability form of `shortcut-keys.authorize`): when `authorize` is a string ability
 * name, `$panelId` is forwarded to Laravel's Gate as the ability's argument. Laravel resolves an
 * implicit policy from that argument, so `$panelId` MUST be a framework-registered panel id — never
 * raw request input — and must not collide with a `Gate::policy()` key or a guessable
 * `{PanelId}Policy` class in the host app, or authorization could be routed to an unintended policy.
 */
interface AuthorityGate
{
    public function allowsAuthoring(?Authenticatable $user, string $panelId): bool;
}
