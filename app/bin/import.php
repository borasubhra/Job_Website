<?php
// Add jobs from JSON files in app/imports/ to the website (as hand-added jobs you can edit on /admin).
// You can also do this in the browser: /admin/imports
//   php bin/import.php                 import every file not imported before
//   php bin/import.php FILE.json       import one file again (jobs already on the site are skipped)
// In Docker:  docker compose exec web php bin/import.php
//
// File format:
// { "source": "Advertisement in The Assam Tribune, 1 Nov 2026",
//   "jobs": [ { "section": "northeast", "title": "...", "organisation": "...", "location": "...",
//               "category": "...", "last_date": "2026-11-10", "link": "https://...", "summary": "..." } ] }
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

use App\{Db, Importer};

Db::migrate();
if (isset($argv[1])) {
    $path = is_file($argv[1]) ? $argv[1] : Importer::dir() . '/' . basename($argv[1]);
    $results = [Importer::import($path)];
} else {
    $results = Importer::importNew();
    if (!Importer::files()) echo "No import files found in " . Importer::dir() . " - run: git pull\n";
}
$total = 0;
foreach ($results as $r) {
    echo "{$r['file']}: {$r['added']} jobs added, {$r['skipped']} skipped\n";
    foreach ($r['messages'] as $m) echo "  $m\n";
    $total += $r['added'];
}
echo $total ? "Done: $total new jobs on the website.\n" : "No new jobs to import.\n";
