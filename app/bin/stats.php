<?php
// Show how many jobs are on the website, per section and per source.
//   docker compose exec web php bin/stats.php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

use App\Db;

Db::migrate();
$rows = Db::run("SELECT section, source_name, count(*) FILTER (WHERE is_active) AS live, count(*) FILTER (WHERE NOT is_active) AS hidden
                   FROM listings GROUP BY section, source_name ORDER BY section DESC, live DESC")->fetchAll();
$total = ['northeast' => 0, 'india' => 0];
printf("%-12s %-48s %6s %7s\n", 'Section', 'Source', 'Live', 'Hidden');
foreach ($rows as $r) {
    printf("%-12s %-48s %6d %7d\n", $r['section'], mb_strimwidth($r['source_name'], 0, 48, '…'), $r['live'], $r['hidden']);
    $total[$r['section']] += $r['live'];
}
printf("\nNorth East Jobs: %d live   |   All India Jobs: %d live\n", $total['northeast'], $total['india']);
$imports = Db::run('SELECT file, jobs_added, imported_at FROM imports ORDER BY imported_at DESC LIMIT 5')->fetchAll();
if ($imports) {
    echo "\nImport files done:\n";
    foreach ($imports as $i) printf("  %-40s %3d jobs  (%s)\n", $i['file'], $i['jobs_added'], date('j M Y H:i', strtotime($i['imported_at'])));
}
