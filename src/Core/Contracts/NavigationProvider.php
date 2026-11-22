<?php

namespace Aristonis\FilamentShortcutKeys\Core\Contracts;

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\NavItem;

/**
 * @internal Part of the resolution pipeline, not an extension point: the plugin builds its own sets
 *           and providers, so this may gain methods in a minor release.
 */
interface NavigationProvider
{
    /**
     * @return NavItem[] the panel's nav targets that get a shortcut, in sidebar order.
     *                   Do not filter by what the current user may open. Sidebar visibility is read for
     *                   the signed-in admin, so an app that hides pages per user gets per-user letters.
     */
    public function items(string $panelId): array;

    /**
     * @return string[] the structure key of every registered nav target, including those items() leaves
     *                  out, so maintenance never mistakes a live target for a removed one
     */
    public function registeredTargets(string $panelId): array;

    public function versionToken(string $panelId): string;
}
