<?php

namespace Aristonis\FilamentShortcutKeys\Core\Enums;

enum BindingSource: string
{
    case CONVENTION = 'convention';
    case OVERLAY = 'overlay';
    case USER = 'user';
    case CUSTOM = 'custom';
}
