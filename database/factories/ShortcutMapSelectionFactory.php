<?php

namespace Aristonis\FilamentShortcutKeys\Database\Factories;

use Aristonis\FilamentShortcutKeys\Models\ShortcutMap;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMapSelection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<ShortcutMapSelection>
 */
final class ShortcutMapSelectionFactory extends Factory
{
    protected $model = ShortcutMapSelection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'authenticatable_type' => 'Aristonis\\FilamentShortcutKeys\\Tests\\Support\\Models\\User',
            'authenticatable_id' => '1',
            'panel_id' => 'admin',
            'map_id' => ShortcutMap::factory(),
        ];
    }

    public function ownedBy(Model $owner): static
    {
        return $this->state([
            'authenticatable_type' => $owner->getMorphClass(),
            'authenticatable_id' => (string) $owner->getKey(),
        ]);
    }

    public function forPanel(string $panelId): static
    {
        return $this->state(['panel_id' => $panelId]);
    }
}
