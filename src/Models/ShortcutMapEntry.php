<?php

namespace Aristonis\FilamentShortcutKeys\Models;

use Aristonis\FilamentShortcutKeys\Database\Factories\ShortcutMapEntryFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One override row of a map: a remapped letter and/or a disabled flag for a single target.
 *
 * @property int $id
 * @property int $map_id
 * @property string $target
 * @property string|null $letter
 * @property bool $disabled
 * @property-read ShortcutMap $map
 */
final class ShortcutMapEntry extends Model
{
    /** @use HasFactory<ShortcutMapEntryFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'map_id',
        'target',
        'letter',
        'disabled',
    ];

    protected $casts = [
        'disabled' => 'boolean',
    ];

    /** @return BelongsTo<ShortcutMap, $this> */
    public function map(): BelongsTo
    {
        return $this->belongsTo(ShortcutMap::class, 'map_id');
    }

    protected static function newFactory(): Factory
    {
        return ShortcutMapEntryFactory::new();
    }
}
