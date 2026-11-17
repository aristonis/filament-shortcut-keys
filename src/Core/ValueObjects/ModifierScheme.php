<?php

namespace Aristonis\FilamentShortcutKeys\Core\ValueObjects;

use Aristonis\FilamentShortcutKeys\Exceptions\InvalidModifierSchemeException;

/**
 * How a set's letters are pressed: with held modifiers (Alt+Shift+O), bare (O), or after a leader key
 * that is pressed and released first (g then o). A leader holds no modifier, which is the point of it:
 * the browser, the OS and the keyboard layout own many modifier combos, but a bare key pressed on the
 * page always reaches the page.
 */
final readonly class ModifierScheme
{
    /** The modifiers a browser reports per key event, and therefore the only ones a scheme can name. */
    private const TOKENS = ['ctrl', 'alt', 'shift', 'meta'];

    /**
     * @param  string|null  $leader  the physical key pressed before the letter (e.g. "KeyG"), or null
     */
    public function __construct(
        public bool $ctrl,
        public bool $alt,
        public bool $shift,
        public bool $meta,
        public ?string $leader = null,
    ) {}

    public static function leader(string $letter): self
    {
        return new self(ctrl: false, alt: false, shift: false, meta: false, leader: 'Key' . strtoupper($letter));
    }

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
        $leaders = array_values(array_filter($tokens, fn ($token) => preg_match('/^[a-z]$/', $token) === 1));

        if ($leaders !== []) {
            if (count($tokens) > 1) {
                throw InvalidModifierSchemeException::forMixedLeader(array_map('strval', $tokens));
            }

            return self::leader($leaders[0]);
        }

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
            && $this->meta === $other->meta
            && $this->leader === $other->leader;
    }

    /** Names the scheme; a leader scheme gets its own name so it never shares a letter pool with bare keys. */
    public function toString(): string
    {
        if ($this->leader !== null) {
            return 'leader:' . strtolower(substr($this->leader, 3));
        }

        return $this->heldModifiers();
    }

    /** Only the modifiers held with the letter, as the browser reports them; "" for bare and leader schemes. */
    public function heldModifiers(): string
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
