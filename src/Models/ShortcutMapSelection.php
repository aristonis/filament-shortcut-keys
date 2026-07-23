<?php

namespace Aristonis\FilamentShortcutKeys\Models;

use Aristonis\FilamentShortcutKeys\Database\Factories\ShortcutMapSelectionFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * An owner's active-map choice for one panel — the pointer resolved first by activeMap().
 *
 * @property int $id
 * @property string $authenticatable_type
 * @property string $authenticatable_id
 * @property string $panel_id
 * @property int $map_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ShortcutMap $map
 * @property-read Model|null $owner
 */
final class ShortcutMapSelection extends Model
{
    /** @use HasFactory<ShortcutMapSelectionFactory> */
    use HasFactory;

    protected $fillable = [
        'authenticatable_type',
        'authenticatable_id',
        'panel_id',
        'map_id',
    ];

    /** @return BelongsTo<ShortcutMap, $this> */
    public function map(): BelongsTo
    {
        return $this->belongsTo(ShortcutMap::class, 'map_id');
    }

    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo('authenticatable');
    }

    protected static function newFactory(): Factory
    {
        return ShortcutMapSelectionFactory::new();
    }
}
