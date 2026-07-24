<?php

namespace Aristonis\FilamentShortcutKeys\Core\Contracts;

/**
 * Marks a shortcut set whose bindings come from the user's map rather than panel discovery, and which
 * therefore carries a custom-binding payload. The resolver only turns a payload entry into a binding
 * for a set with this marker, so a stray payload on an ordinary set is ignored, never forced into its
 * clash pool or mis-served as that set's action.
 */
interface AcceptsCustomBindings {}
