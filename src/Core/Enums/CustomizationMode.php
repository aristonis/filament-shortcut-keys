<?php

namespace Aristonis\FilamentShortcutKeys\Core\Enums;

/** The values of the `customization` config key: whether admins may personalize their own maps. */
enum CustomizationMode: string
{
    case PERSONAL = 'personal';
    case LOCKED = 'locked';
}
