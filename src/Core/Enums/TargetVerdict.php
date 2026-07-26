<?php

namespace Aristonis\FilamentShortcutKeys\Core\Enums;

/** What an audit could establish about a stored override's target. */
enum TargetVerdict: string
{
    case LIVE = 'live';

    case ORPHANED = 'orphaned';

    /** The set cannot be enumerated from outside a page request, so the target is left alone. */
    case UNVERIFIABLE = 'unverifiable';
}
