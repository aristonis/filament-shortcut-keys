<?php

namespace Aristonis\FilamentShortcutKeys\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One override row of a map for a single target: a remapped letter and/or a disabled flag, or a custom
 * binding's letter and the payload it activates (a selector to click or a route to open).
 *
 * @property int $id
 * @property int $map_id
 * @property string $target
 * @property string|null $letter
 * @property bool $disabled
 * @property array|null $payload
 * @property-read ShortcutMap $map
 */
final class ShortcutMapEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'map_id',
        'target',
        'letter',
        'disabled',
        'payload',
    ];

    protected $casts = [
        'disabled' => 'boolean',
        'payload' => 'array',
    ];

    /** @return BelongsTo<ShortcutMap, $this> */
    public function map(): BelongsTo
    {
        return $this->belongsTo(ShortcutMap::class, 'map_id');
    }
}
