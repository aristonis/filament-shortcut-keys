<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model;

/** A line on an Order, listed by the order's relation manager. */
final class OrderItem extends Model
{
    protected $table = 'order_items';

    protected $guarded = [];
}
