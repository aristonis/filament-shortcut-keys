<?php

namespace Aristonis\FilamentShortcutKeys\Tests;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Base for tests that need two real database connections racing each other.
 *
 * These cannot use RefreshDatabase: its wrapping transaction hides every write from the second
 * connection, so the race being tested is invisible. The database is migrated fresh per test and the
 * writes really commit.
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

        $this->artisan('migrate:fresh')->run();

        // migrate:fresh honours whatever seeders testbench.yaml declares, and that file is a local
        // dev-serve artifact the CI checkout does not have. Start every race from the same empty
        // tables so the assertions do not depend on it either way.
        foreach (['shortcut_map_selections', 'shortcut_map_entries', 'shortcut_maps'] as $table) {
            DB::table($table)->delete();
        }
    }

    protected function tearDown(): void
    {
        DB::purge(self::SECOND);

        parent::tearDown();
    }

    /** The competing request's connection. */
    protected function second(): ConnectionInterface
    {
        return DB::connection(self::SECOND);
    }
}
