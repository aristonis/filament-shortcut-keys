<?php

namespace Aristonis\FilamentShortcutKeys\Core\Resolution;

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;

/** One set's slice of a resolved map: the set key, its effective modifier, and the bindings under it. */
final readonly class ResolvedGroup
{
    public function __construct(
        public string $setKey,
        public ModifierScheme $modifier,
        public array $bindings
    ) {}
}
