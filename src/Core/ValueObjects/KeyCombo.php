<?php

namespace Aristonis\FilamentShortcutKeys\Core\ValueObjects;

use Aristonis\FilamentShortcutKeys\Exceptions\InvalidKeyComboException;

/**
 * Immutable keyboard combo: modifier flags + one physical key code ("KeyP", "Slash").
 * The key is stored as e.code so matching is stable across keyboard layouts and RTL; the four
 * bools mirror the browser's e.*Key flags so server and client compare 1:1. parse() is the only
 * validated entry point — the constructor trusts normalized input.
 */
final readonly class KeyCombo
{
    private const KEY_CODES = [
        '/' => 'Slash',
    ];

    public function __construct(
        public bool $ctrl,
        public bool $alt,
        public bool $shift,
        public bool $meta,
        public string $code,
    ) {}

    public static function parse(string $raw): self
    {
        $normalized = strtolower(trim($raw));

        if ($normalized === '') {
            throw InvalidKeyComboException::forInput($raw);
        }

        $ctrl = $alt = $shift = $meta = false;
        $code = null;

        foreach (explode('+', $normalized) as $token) {
            switch ($token) {
                case 'ctrl':
                case 'control':
                    $ctrl = true;

                    break;
                case 'alt':
                case 'option':
                    $alt = true;

                    break;
                case 'shift':
                    $shift = true;

                    break;
                case 'cmd':
                case 'meta':
                case 'super':
                    $meta = true;

                    break;
                default:
                    if ($code !== null) {
                        throw InvalidKeyComboException::forInput($raw);
                    }
                    $code = self::toCode($token);
            }
        }

        if ($code === null) {
            throw InvalidKeyComboException::forInput($raw);
        }

        return new self($ctrl, $alt, $shift, $meta, $code);
    }

    public function equals(self $other): bool
    {
        return $this->ctrl === $other->ctrl
            && $this->alt === $other->alt
            && $this->shift === $other->shift
            && $this->meta === $other->meta
            && $this->code === $other->code;
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

        $parts[] = self::toToken($this->code);

        return implode('+', $parts);
    }

    private static function toCode(string $token): string
    {
        if (isset(self::KEY_CODES[$token])) {
            return self::KEY_CODES[$token];
        }

        if (preg_match('/^[a-z]$/', $token)) {
            return 'Key' . strtoupper($token);
        }

        throw InvalidKeyComboException::forInput($token);
    }

    private static function toToken(string $code): string
    {
        $token = array_search($code, self::KEY_CODES, true);

        if ($token !== false) {
            return $token;
        }

        if (str_starts_with($code, 'Key')) {
            return strtolower(substr($code, 3));
        }

        return $code;
    }
}
