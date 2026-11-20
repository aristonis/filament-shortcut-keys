<?php

namespace Aristonis\FilamentShortcutKeys\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 */
final class ShortcutMapSelection extends Model
{
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
}
