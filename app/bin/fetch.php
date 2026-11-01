<?php
// Run the job update by hand:
//   php bin/fetch.php            all sources
//   php bin/fetch.php nenow      one source (or a section: northeast / india)
// In Docker:  docker compose exec web php bin/fetch.php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

App\Db::migrate();
foreach (App\Ingest::runAll($argv[1] ?? null) as $r) {
    printf("%-14s found %-4d new %-4d %s\n", $r['source'], $r['found'], $r['inserted'], $r['error'] ?? '');
}
