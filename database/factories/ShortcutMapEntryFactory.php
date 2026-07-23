<?php

namespace Aristonis\FilamentShortcutKeys\Database\Factories;

use Aristonis\FilamentShortcutKeys\Models\ShortcutMap;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMapEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShortcutMapEntry>
 */
final class ShortcutMapEntryFactory extends Factory
{
    protected $model = ShortcutMapEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'map_id' => ShortcutMap::factory(),
            'target' => 'navigation:' . $this->faker->unique()->slug(1),
            'letter' => $this->faker->randomLetter(),
            'disabled' => false,
        ];
    }

    /** An entry that only disables its target (no remap letter). */
    public function disabled(): static
    {
        return $this->state([
            'letter' => null,
            'disabled' => true,
        ]);
    }
}
