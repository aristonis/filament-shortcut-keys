<?php

namespace Aristonis\FilamentShortcutKeys\Core\ValueObjects;

use Aristonis\FilamentShortcutKeys\Core\Enums\BindingSource;

final readonly class ShortcutBinding
{
    public function __construct(
        public ShortcutTarget $target,
        public ?KeyCombo $keyCombo,
        public bool $enabled = true,
        public BindingSource $source = BindingSource::CONVENTION
    ) {}
}
