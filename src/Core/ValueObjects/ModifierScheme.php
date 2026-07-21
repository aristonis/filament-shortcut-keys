<?php

namespace Aristonis\FilamentShortcutKeys\Core\ValueObjects;

final readonly class ModifierScheme
{
    public function __construct(
        public bool $ctrl,
        public bool $alt,
        public bool $shift,
        public bool $meta
    ) {}

    public static function alt(): self
    {
        return new self(ctrl: false, alt: true, shift: false, meta: false);
    }

    public static function none(): self
    {
        return new self(ctrl: false, alt: false, shift: false, meta: false);
    }

    public static function altShift(): self
    {
        return new self(ctrl: false, alt: true, shift: true, meta: false);
    }

    public function equals(self $other): bool
    {
        return $this->ctrl === $other->ctrl
            && $this->alt === $other->alt
            && $this->shift === $other->shift
            && $this->meta === $other->meta;
    }

    public function toString(): string
    {
        $parts = [];
        if ($this->ctrl) {
            $parts[] = 'ctrl';
        }
        if ($this->alt) {
            $parts[] = 'alt';
        }
        if ($this->shift) {
            $parts[] = 'shift';
        }
        if ($this->meta) {
            $parts[] = 'meta';
        }

        return implode('+', $parts);
    }
}
