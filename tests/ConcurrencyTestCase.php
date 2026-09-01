<?php

namespace Aristonis\FilamentShortcutKeys\Tests;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Base for tests that need two real database connections racing each other.
 *
 * These cannot use RefreshDatabase: its wrapping transaction hides every write from the second
 * connection, so the race being tested is invisible. The writes really commit, which is why this case
 * clears its own tables before and after each test instead of relying on a rollback.
 *
 * sqlite is skipped by design — it serialises everything through a single connection, so the losing
 * branch of a race never executes. That is exactly why these assertions need the CI database matrix.
 */
abstract class ConcurrencyTestCase extends BaseTestCase
{
    /** A second connection to the same database, standing in for a concurrent request. */
    protected const SECOND = 'fsk_second';

    protected function setUp(): void
    {
        parent::setUp();

        if (in_array(static::databaseConnection(), ['testing', 'sqlite'], true)) {
            $this->markTestSkipped('Races need a driver with real concurrent connections; run the database matrix.');
        }

        config(['database.connections.' . self::SECOND => static::databaseConfig()]);

        // Deliberately NOT migrate:fresh. This case shares a database with the rest of the suite, and
        // dropping every table mid-run leaves whichever test comes next to rediscover the schema —
        // which is exactly how this failed in CI, on an ordering that never came up locally.
        if (! Schema::hasTable('shortcut_maps')) {
            $this->artisan('migrate')->run();
        }

        $this->clearMaps();
    }

    /**
     * These tests commit for real, so their rows outlive them. Clearing on the way in and on the way
     * out keeps each race starting empty without touching anything the rest of the suite owns.
     */
    private function clearMaps(): void
    {
        foreach (['shortcut_map_selections', 'shortcut_map_entries', 'shortcut_maps'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
    }

    protected function tearDown(): void
    {
        $this->clearMaps();
        DB::purge(self::SECOND);

        parent::tearDown();
    }

    /** The competing request's connection. */
    protected function second(): ConnectionInterface
    {
        return DB::connection(self::SECOND);
    }
}
