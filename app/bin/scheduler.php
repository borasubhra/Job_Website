<?php
// Runs forever (Docker "scheduler" service) and starts a job update whenever FETCH_CRON matches.
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

use App\{Cron, Db, Ingest};

$expr = env('FETCH_CRON', '0 6,18 * * *');
echo "[scheduler] job updates scheduled \"$expr\" (" . date_default_timezone_get() . ")\n";
Db::waitUntilReady();
Db::migrate();

$run = function (): void {
    try { Ingest::runAll(); }
    catch (\Throwable $e) { echo '[scheduler] ERROR ' . $e->getMessage() . "\n"; }
};

if (env('FETCH_ON_START', 'true') === 'true') $run();

$last = '';
while (true) {
    $now = new DateTimeImmutable();
    $minute = $now->format('Y-m-d H:i');
    if ($minute !== $last && Cron::matches($expr, $now)) { $last = $minute; $run(); }
    sleep(60 - (int) date('s') + 1);    // wake just after the next minute starts
}
