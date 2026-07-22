<?php

namespace Aristonis\FilamentShortcutKeys\Exceptions;

abstract class ShortcutKeysException extends \RuntimeException
{
    public function __construct(string $message, int $code, protected string $stringCode, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function stringCode(): string
    {
        return $this->stringCode;
    }
}
