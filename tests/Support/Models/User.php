<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Minimal owner model used by integration tests to exercise the polymorphic map owner
 * relation. Backed by the default `users` table provided by Testbench.
 */
final class User extends Model
{
    protected $table = 'users';

    protected $guarded = [];
}
