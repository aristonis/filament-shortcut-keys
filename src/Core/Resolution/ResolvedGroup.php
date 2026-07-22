<?php

namespace Aristonis\FilamentShortcutKeys\Core\Resolution;

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;

final readonly class ResolvedGroup
{
    public function __construct(
        public string $setKey,
        public ModifierScheme $modifier,
        public array $bindings
    ) {}

}
