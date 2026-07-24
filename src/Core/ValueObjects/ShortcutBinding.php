<?php

namespace Aristonis\FilamentShortcutKeys\Core\ValueObjects;

use Aristonis\FilamentShortcutKeys\Core\Enums\BindingSource;

/** One shortcut: what it targets, its key (null until assigned), whether it's enabled, and where it came from. */
final readonly class ShortcutBinding
{
    /**
     * @param  array{selector: string}|array{route: string}|null  $payload  a custom binding's action,
     *                                                                      carried opaquely to the client
     */
    public function __construct(
        public ShortcutTarget $target,
        public ?KeyCombo $keyCombo,
        public bool $enabled = true,
        public BindingSource $source = BindingSource::CONVENTION,
        public ?string $letterHint = null,
        public ?array $payload = null,
    ) {}
}
