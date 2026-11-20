<?php

namespace Aristonis\FilamentShortcutKeys\Core\Enums;

/** The values of the `customization` config key: whether admins may personalize their own maps. */
enum CustomizationMode: string
{
    case PERSONAL = 'personal';
    case LOCKED = 'locked';

    /** Keys come only from the conventions and the config file; nothing is stored, so no tables are needed. */
    case CONFIG = 'config';

    /** The configured mode. An unknown value throws, so a typo cannot silently turn storage on or off. */
    public static function current(): self
    {
        return self::from((string) config('shortcut-keys.customization', self::PERSONAL->value));
    }

    /** Whether shortcut maps are read from and written to the database at all. */
    public function storesMaps(): bool
    {
        return $this !== self::CONFIG;
    }
}
