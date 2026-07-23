<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Support\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Owner model with a string (UUID) primary key, used to prove the polymorphic map owner
 * design round-trips a non-integer owner id without truncation or numeric coercion.
 */
final class UuidOwner extends Model
{
    use HasUuids;

    protected $table = 'uuid_owners';

    protected $guarded = [];
}
