<?php

namespace Aristonis\FilamentShortcutKeys\Models;

use Aristonis\FilamentShortcutKeys\Core\Enums\MapType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A stored shortcut map — a system preset (possibly the panel default) or a user-owned custom map — plus its override entries.
 *
 * @property int $id
 * @property string $panel_id
 * @property MapType $type
 * @property string|null $authenticatable_type
 * @property string|null $authenticatable_id
 * @property int $version
 * @property string|null $default_marker
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ShortcutMapEntry> $entries
 */
final class ShortcutMap extends Model
{
    protected $fillable = [
        'panel_id',
        'type',
        'authenticatable_type',
        'authenticatable_id',
        'version',
        'default_marker',
    ];

    protected $casts = [
        'type' => MapType::class,
        'version' => 'integer',
    ];

    /** @return HasMany<ShortcutMapEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(ShortcutMapEntry::class, 'map_id');
    }
}
