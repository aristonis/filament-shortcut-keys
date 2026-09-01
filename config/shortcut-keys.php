<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Customization mode
    |--------------------------------------------------------------------------
    |
    | Whether end users may personalize their own shortcut maps.
    |
    |   'locked'   — everyone uses the panel's system map; no personalization.
    |   'personal' — each user may fork and remap their own shortcuts.
    |
    */

    'customization' => 'personal',

    /*
    |--------------------------------------------------------------------------
    | Per-set modifiers
    |--------------------------------------------------------------------------
    |
    | The modifier scheme applied to each shortcut set. An empty array means
    | bare keys (no modifier), e.g. table search on "/". Values are modifier
    | tokens: 'ctrl', 'alt', 'shift', 'meta'. Remove a set from this list to
    | put it back on its built-in scheme; an unknown token throws.
    |
    | Two sets sharing a scheme share one letter pool, so they can never be
    | assigned the same key.
    |
    */

    'modifiers' => [
        'navigation' => ['alt', 'shift'],
        'global' => ['alt'],
        'table' => [],
        'row-action' => [],
        'custom' => ['alt', 'shift'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authoring authorization
    |--------------------------------------------------------------------------
    |
    | Controls who may edit a panel's SYSTEM shortcut maps. Default is deny.
    |
    |   null                     — deny everyone.
    |   Closure(?Authenticatable $user, string $panelId): bool
    |                            — custom predicate; return true to allow.
    |   string                   — a Gate ability name, checked as
    |                              Gate::forUser($user)->allows($ability, $panelId).
    |
    */

    'authorize' => null,

    /*
    |--------------------------------------------------------------------------
    | Developer overlay
    |--------------------------------------------------------------------------
    |
    | Force or disable specific shortcuts at the code level, keyed by target
    | identity (e.g. 'navigation:products'). Each entry is one of:
    |
    |   ['letter' => 'r']     — force this shortcut onto the letter R.
    |   ['disabled' => true]  — remove this shortcut from the keymap.
    |
    */

    'overlay' => [],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Time-to-live, in seconds, for the cached panel-wide (navigation) keymap.
    |
    | Freshness does not depend on this. The cache key is a fingerprint of everything that shapes the
    | map, so changing the navigation, the active map, the overlay or the locale already produces a
    | different key. What the fingerprint cannot do is remove the entry it moved away from, and
    | nothing else in this package deletes it either: every edit and every deploy that touches
    | navigation leaves its predecessor behind. The lifetime is what reclaims those, which matters
    | most on a store that never expires anything on its own.
    |
    | null caches forever. Only choose it on a store you actively manage, and expect the keyspace to
    | grow with the number of edits your admins make.
    |
    */

    'cache' => [
        'ttl' => 86400,
    ],

];
