<?php

namespace Aristonis\FilamentShortcutKeys\Models;

use Aristonis\FilamentShortcutKeys\Core\Enums\MapType;
use Aristonis\FilamentShortcutKeys\Database\Factories\ShortcutMapFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A stored shortcut map — either a panel's default system map or a user-owned custom map — plus its override entries.
 *
 * @property int $id
 * @property string $panel_id
 * @property MapType $type
 * @property string|null $authenticatable_type
 * @property string|null $authenticatable_id
 * @property array<string, mixed>|null $modifiers
 * @property int $version
 * @property string|null $default_marker
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ShortcutMapEntry> $entries
 * @property-read Collection<int, ShortcutMapSelection> $selections
 * @property-read Model|null $owner
 */
final class ShortcutMap extends Model
{
    /** @use HasFactory<ShortcutMapFactory> */
    use HasFactory;

    protected $fillable = [
        'panel_id',
        'type',
        'authenticatable_type',
        'authenticatable_id',
        'modifiers',
        'version',
        'default_marker',
    ];

    protected $casts = [
        'type' => MapType::class,
        'modifiers' => 'array',
        'version' => 'integer',
    ];

    /** @return HasMany<ShortcutMapEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(ShortcutMapEntry::class, 'map_id');
    }

    /** @return HasMany<ShortcutMapSelection, $this> */
    public function selections(): HasMany
    {
        return $this->hasMany(ShortcutMapSelection::class, 'map_id');
    }

    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo('authenticatable');
    }

    protected static function newFactory(): Factory
    {
        return ShortcutMapFactory::new();
    }
}
