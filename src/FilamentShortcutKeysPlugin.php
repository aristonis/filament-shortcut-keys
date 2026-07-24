<?php

namespace Aristonis\FilamentShortcutKeys;

use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentShortcutKeysPlugin implements Plugin
{
    /** @var string[] custom row-action names bound to the focused table row, panel-wide */
    protected array $rowActions = [];

    public function getId(): string
    {
        return 'filament-shortcut-keys';
    }

    /**
     * Register custom row-action names (e.g. approve/reject). Each gets a stable shortcut letter,
     * active on any table that exposes a record action of that name.
     *
     * @param  string[]  $names
     */
    public function rowActions(array $names): static
    {
        $this->rowActions = $names;

        return $this;
    }

    /** @return string[] */
    public function getRowActions(): array
    {
        return $this->rowActions;
    }

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }
}
