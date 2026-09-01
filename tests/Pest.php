<?php

use Aristonis\FilamentShortcutKeys\Tests\ConcurrencyTestCase;
use Aristonis\FilamentShortcutKeys\Tests\TestCase;
use Illuminate\Support\Facades\DB;

// Listed per directory rather than the whole tree: Pest binds a folder to one case, and Concurrency
// needs a different one — races need real committed writes on two connections, which the
// transaction-wrapped case would hide.
uses(TestCase::class)->in(__DIR__ . '/Unit', __DIR__ . '/Integration', __DIR__ . '/DebugTest.php');
uses(ConcurrencyTestCase::class)->in(__DIR__ . '/Concurrency');

/**
 * Extracts the JSON body of the plugin's injected keymap <script> block from a rendered page.
 *
 * @return array<int, array<string, mixed>>|null the decoded ResolvedMap, or null when absent
 */
function injectedKeymap(string $html): ?array
{
    if (! preg_match('/<script[^>]*id="filament-shortcut-keys-map"[^>]*>(.*?)<\/script>/s', $html, $m)) {
        return null;
    }

    return json_decode(trim($m[1]), true);
}

/**
 * Wraps an action that is expected to breach a database constraint in a savepoint.
 *
 * PostgreSQL aborts the entire transaction on any failed statement, so a bare violation inside a
 * test poisons the wrapping RefreshDatabase transaction: the rest of that test, and the migration
 * bookkeeping after it, fail with "current transaction is aborted". Rolling back to a savepoint
 * confines the damage to the statement under test. A no-op difference on sqlite and MySQL.
 */
function violatesConstraint(Closure $action): Closure
{
    return fn () => DB::transaction($action);
}
