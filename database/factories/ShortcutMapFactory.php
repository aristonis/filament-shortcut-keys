<?php

namespace Aristonis\FilamentShortcutKeys\Database\Factories;

use Aristonis\FilamentShortcutKeys\Core\Enums\MapType;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMap;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<ShortcutMap>
 */
final class ShortcutMapFactory extends Factory
{
    protected $model = ShortcutMap::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'panel_id' => 'admin',
            'type' => MapType::SYSTEM,
            'authenticatable_type' => null,
            'authenticatable_id' => null,
            'modifiers' => null,
            'version' => 1,
            'default_marker' => null,
        ];
    }

    /** The panel's default system map: default_marker mirrors panel_id to enforce one-per-panel. */
    public function default(string $panelId = 'admin'): static
    {
        return $this->state([
            'panel_id' => $panelId,
            'type' => MapType::SYSTEM,
            'default_marker' => $panelId,
        ]);
    }

    /** A user-owned custom map (assign the owner via ownedBy()). */
    public function custom(): static
    {
        return $this->state([
            'type' => MapType::CUSTOM,
            'default_marker' => null,
        ]);
    }

    public function ownedBy(Model $owner): static
    {
        return $this->state([
            'authenticatable_type' => $owner->getMorphClass(),
            'authenticatable_id' => (string) $owner->getKey(),
        ]);
    }
}
