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
     * @return NavItem[] every registered nav target for the panel, in sidebar order.
     *                   The keymap is public: return all targets, not just what the current user can see.
     */
    public function items(string $panelId): array;

    public function versionToken(string $panelId): string;
}
