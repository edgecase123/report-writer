<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Support;

use PHPUnit\Framework\Assert;

/**
 * assertSnapshotMatches($actual, $snapshotPath):
 *   - If the snapshot file is missing, writes $actual and fails with a
 *     "snapshot bootstrapped" message so the run is not silently green.
 *   - If UPDATE_SNAPSHOTS=1 is set in the environment, overwrites the snapshot
 *     with $actual and passes.
 *   - Otherwise diffs $actual against the file contents byte-for-byte.
 *
 * Reused by A3.2 (this ticket), A3.3 (Open Tabs), A3.5 (Register Close).
 */
trait SnapshotAssertions
{
    private function assertSnapshotMatches(string $actual, string $snapshotPath): void
    {
        $update = getenv('UPDATE_SNAPSHOTS') === '1';

        if (!file_exists($snapshotPath) || $update) {
            $dir = dirname($snapshotPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($snapshotPath, $actual);
            if (!$update) {
                Assert::fail("Snapshot bootstrapped at {$snapshotPath}. Re-run the test to verify.");
            }
            return;
        }

        Assert::assertSame(
            (string) file_get_contents($snapshotPath),
            $actual,
            "Snapshot mismatch at {$snapshotPath}. Re-run with UPDATE_SNAPSHOTS=1 to accept."
        );
    }
}
