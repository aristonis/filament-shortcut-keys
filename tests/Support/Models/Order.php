<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Minimal model backing the test-only OrderResource so the testbench admin panel has a
 * second navigable resource (with a table + row actions) to exercise adapters that read
 * panel internals.
 */
final class Order extends Model
{
    protected $table = 'orders';

    protected $guarded = [];

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
