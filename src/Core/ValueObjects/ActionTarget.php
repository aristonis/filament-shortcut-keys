<?php

namespace Aristonis\FilamentShortcutKeys\Core\ValueObjects;

/**
 * One keyable action on the current page, in the shape the client needs to fire it.
 *
 * Exactly one of the two activations is set. A url means the control is an anchor and the client
 * navigates; a selector means it is a Livewire control and the client clicks it.
 *
 * runsImmediately marks an action that does its work on the click itself, with no page, modal or
 * confirmation in between, so a single stray key would run it.
 */
final readonly class ActionTarget
{
    public function __construct(
        public string $name,
        public string $label,
        public ?string $selector = null,
        public ?string $url = null,
        public bool $runsImmediately = false,
    ) {}
}
