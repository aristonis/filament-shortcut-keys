<?php

namespace Aristonis\FilamentShortcutKeys\Core\ValueObjects;

use Aristonis\FilamentShortcutKeys\Exceptions\InvalidModifierSchemeException;

final readonly class ModifierScheme
{
    /** The modifiers a browser reports per key event, and therefore the only ones a scheme can name. */
    private const TOKENS = ['ctrl', 'alt', 'shift', 'meta'];

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

    /**
     * Builds a scheme from the modifier tokens a developer configured for a set. An empty list is a
     * valid answer (bare keys). An unrecognised token throws rather than being dropped, so a typo in
     * config surfaces on the next page render instead of silently changing everyone's shortcuts.
     *
     * @param  string[]  $tokens
     */
    public static function fromTokens(array $tokens): self
    {
        foreach ($tokens as $token) {
            if (! in_array($token, self::TOKENS, true)) {
                throw InvalidModifierSchemeException::forToken((string) $token);
            }
        }

        return new self(
            ctrl: in_array('ctrl', $tokens, true),
            alt: in_array('alt', $tokens, true),
            shift: in_array('shift', $tokens, true),
            meta: in_array('meta', $tokens, true),
        );
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
