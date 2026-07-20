<?php

namespace Aristonis\FilamentShortcutKeys\Core\Enums;

enum BindingSource: string
{
    // convention | overlay | user | custom
    case CONVENTION = 'convention';
    case OVERLAY = 'overlay';
    case USER = 'user';
    case CUSTOM = 'custom';
}
