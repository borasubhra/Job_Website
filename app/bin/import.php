<?php
// Add jobs from JSON files in app/imports/ to the website (as hand-added jobs you can edit on /admin).
//   php bin/import.php                 import every file not imported before
//   php bin/import.php FILE.json       import one file (even if imported before; duplicates are skipped)
// In Docker:  docker compose exec web php bin/import.php
//
// File format:
// { "source": "The Assam Tribune, 1 Nov 2026",
//   "jobs": [ { "section": "northeast", "title": "...", "organisation": "...", "location": "...",
//               "category": "...", "last_date": "2026-11-10", "link": "https://...", "summary": "..." } ] }
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

use App\{Db, Ingest};

Db::migrate();
$dir = dirname(__DIR__) . '/imports';
$files = isset($argv[1]) ? [$argv[1]] : glob("$dir/*.json");
$done = Db::run('SELECT file FROM imports')->fetchAll(PDO::FETCH_COLUMN);
$total = 0;

foreach ($files as $path) {
    if (!is_file($path) && is_file("$dir/$path")) $path = "$dir/$path";
    $name = basename($path);
    if (!isset($argv[1]) && in_array($name, $done, true)) continue;

    $data = json_decode((string) @file_get_contents($path), true);
    if (!is_array($data) || !isset($data['jobs']) || !is_array($data['jobs'])) { echo "SKIP $name: not a valid import file\n"; continue; }
    $credit = mb_substr(trim((string) ($data['source'] ?? 'JobPortal Desk')), 0, 120);
    $added = 0; $skipped = 0;

    foreach ($data['jobs'] as $n => $j) {
        $title = trim((string) ($j['title'] ?? ''));
        $org = trim((string) ($j['organisation'] ?? ''));
        $link = trim((string) ($j['link'] ?? ''));
        $last = trim((string) ($j['last_date'] ?? ''));
        if ($title === '' || $org === '') { echo "  #$n skipped: title and organisation are required\n"; $skipped++; continue; }
        if ($link !== '' && !preg_match('#^https?://#i', $link)) { echo "  #$n link ignored (not http/https): $link\n"; $link = ''; }
        if ($last !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $last)) { echo "  #$n last_date ignored (use YYYY-MM-DD): $last\n"; $last = ''; }
        $key = Ingest::titleKey($title);
        $exists = Db::run("SELECT 1 FROM listings WHERE source_id = 'manual' AND title_key = ? AND organisation = ?", [$key, $org])->fetchColumn();
        if ($exists) { $skipped++; continue; }
        Db::run(
            "INSERT INTO listings (section, source_id, source_name, title, title_key, summary, source_url, organisation, location,
                                   category, last_date, published_at, is_active)
             VALUES (?, 'manual', ?, ?, ?, ?, ?, ?, ?, ?, ?, now(), TRUE)",
            [($j['section'] ?? '') === 'india' ? 'india' : 'northeast', $credit, mb_substr($title, 0, 250), $key,
             mb_substr(trim((string) ($j['summary'] ?? '')), 0, 1500), $link, mb_substr($org, 0, 200),
             mb_substr(trim((string) ($j['location'] ?? '')), 0, 200) ?: null,
             mb_substr(trim((string) ($j['category'] ?? '')), 0, 120) ?: null, $last ?: null]);
        $added++;
    }
    Db::run('INSERT INTO imports (file, jobs_added) VALUES (?, ?) ON CONFLICT (file) DO UPDATE SET imported_at = now(), jobs_added = imports.jobs_added + EXCLUDED.jobs_added', [$name, $added]);
    echo "$name: $added jobs added, $skipped skipped\n";
    $total += $added;
}
// expired jobs (last date passed) are hidden straight away
Db::run("UPDATE listings SET is_active = FALSE WHERE is_active AND last_date IS NOT NULL AND last_date < CURRENT_DATE");
echo $total ? "Done: $total new jobs on the website.\n" : "No new jobs to import.\n";
