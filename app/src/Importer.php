<?php
declare(strict_types=1);

namespace App;

/** Adds jobs from JSON files in app/imports/ as hand-added jobs. Used by bin/import.php and the admin Import page. */
final class Importer
{
    public static function dir(): string
    {
        return dirname(__DIR__) . '/imports';
    }

    /** @return list<array{file:string, jobs:int|null, error:?string, imported_at:?string, jobs_added:?int}> */
    public static function files(): array
    {
        $done = [];
        foreach (Db::run('SELECT file, imported_at, jobs_added FROM imports')->fetchAll() as $r) $done[$r['file']] = $r;
        $out = [];
        foreach (glob(self::dir() . '/*.json') ?: [] as $path) {
            $name = basename($path);
            [$data, $error] = self::read($path);
            $out[] = ['file' => $name, 'jobs' => $data ? count($data['jobs']) : null, 'error' => $error,
                      'imported_at' => $done[$name]['imported_at'] ?? null, 'jobs_added' => $done[$name]['jobs_added'] ?? null];
        }
        return $out;
    }

    /**
     * Import one file. Jobs already on the site (same title + organisation) are skipped.
     * @return array{file:string, added:int, skipped:int, messages:list<string>}
     */
    public static function import(string $path): array
    {
        $name = basename($path);
        [$data, $error] = self::read($path);
        if ($error) return ['file' => $name, 'added' => 0, 'skipped' => 0, 'messages' => [$error]];

        $credit = mb_substr(trim((string) ($data['source'] ?? 'JobPortal Desk')), 0, 120);
        $added = 0; $skipped = 0; $msgs = [];
        foreach ($data['jobs'] as $n => $j) {
            $title = trim((string) ($j['title'] ?? ''));
            $org = trim((string) ($j['organisation'] ?? ''));
            $link = trim((string) ($j['link'] ?? ''));
            $last = trim((string) ($j['last_date'] ?? ''));
            if ($title === '' || $org === '') { $msgs[] = "Job #$n skipped: title and organisation are required"; $skipped++; continue; }
            if ($link !== '' && !preg_match('#^https?://#i', $link)) { $msgs[] = "Job #$n: link ignored (must start with http/https)"; $link = ''; }
            if ($last !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $last)) { $msgs[] = "Job #$n: last date ignored (use YYYY-MM-DD)"; $last = ''; }
            $key = Ingest::titleKey($title);
            if (Db::run("SELECT 1 FROM listings WHERE source_id = 'manual' AND title_key = ? AND organisation = ?", [$key, $org])->fetchColumn()) { $skipped++; continue; }
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
        Db::run('INSERT INTO imports (file, jobs_added) VALUES (?, ?)
                 ON CONFLICT (file) DO UPDATE SET imported_at = now(), jobs_added = imports.jobs_added + EXCLUDED.jobs_added', [$name, $added]);
        // jobs whose last date has passed are hidden straight away
        Db::run("UPDATE listings SET is_active = FALSE WHERE is_active AND last_date IS NOT NULL AND last_date < CURRENT_DATE");
        return ['file' => $name, 'added' => $added, 'skipped' => $skipped, 'messages' => $msgs];
    }

    /** Import every file not imported before. */
    public static function importNew(): array
    {
        $results = [];
        foreach (self::files() as $f) {
            if ($f['imported_at'] === null) $results[] = self::import(self::dir() . '/' . $f['file']);
        }
        return $results;
    }

    /** @return array{0: ?array, 1: ?string} [data, error] */
    private static function read(string $path): array
    {
        $raw = @file_get_contents($path);
        if ($raw === false) return [null, 'Cannot read the file'];
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);          // tolerate a UTF-8 BOM (Windows editors)
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['jobs']) || !is_array($data['jobs']))
            return [null, 'Not a valid import file: ' . (json_last_error() ? json_last_error_msg() : 'missing "jobs" list')];
        return [$data, null];
    }
}
