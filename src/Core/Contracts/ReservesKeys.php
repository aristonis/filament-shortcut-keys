<?php

namespace Aristonis\FilamentShortcutKeys\Core\Contracts;

/**
 * A set that owns keys in its pool even on a page where it binds nothing, so other sets sharing the
 * pool never auto-assign them. The table's j and k are the case in point: a header action named
 * "Join" must not take j on a page that happens to have no table.
 *
 * @internal Part of the resolution pipeline, not an extension point: the plugin builds its own sets
 *           and providers, so this may gain methods in a minor release.
 */
interface ReservesKeys
{
    /** @return string[] physical key codes, in the set's own modifier pool */
    public function reservedCodes(): array;
}
