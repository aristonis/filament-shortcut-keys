<?php

namespace Aristonis\FilamentShortcutKeys\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * RefreshDatabase, not LazilyRefreshDatabase: the lazy variant deadlocks the suite on PostgreSQL.
 * Deferring the refresh means it lands mid-test, and the connection purge that follows `migrate:fresh`
 * orphans a session still inside a transaction, which every later test then blocks on. Costs about two
 * seconds on sqlite and lets the same suite run on every driver in the database matrix.
 */
class TestCase extends BaseTestCase
{
    use RefreshDatabase;
}
