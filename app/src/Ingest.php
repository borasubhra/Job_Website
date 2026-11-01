<?php
declare(strict_types=1);

namespace App;

use App\Fetchers\AdzunaFetcher;
use App\Fetchers\NewsFetcher;

/** Fetch -> clean -> de-duplicate -> save, for every source. */
final class Ingest
{
    private const NE_STATES = ['Assam', 'Arunachal Pradesh', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Tripura', 'Sikkim'];
    private const NE_CITIES = [
        'Guwahati' => 'Assam', 'Gauhati' => 'Assam', 'Dispur' => 'Assam', 'Dibrugarh' => 'Assam', 'Silchar' => 'Assam',
        'Jorhat' => 'Assam', 'Tezpur' => 'Assam', 'Nagaon' => 'Assam', 'Tinsukia' => 'Assam', 'Duliajan' => 'Assam',
        'Bongaigaon' => 'Assam', 'Shillong' => 'Meghalaya', 'Tura' => 'Meghalaya', 'Imphal' => 'Manipur',
        'Aizawl' => 'Mizoram', 'Kohima' => 'Nagaland', 'Dimapur' => 'Nagaland', 'Agartala' => 'Tripura',
        'Itanagar' => 'Arunachal Pradesh', 'Gangtok' => 'Sikkim',
    ];
    private const MONTHS = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];

    /** @param string|null $only a source id or section name */
    public static function runAll(?string $only = null): array
    {
        echo '[ingest] start ' . date('c') . "\n";
        $sources = require dirname(__DIR__) . '/config/sources.php';
        $results = [];
        foreach ($sources as $src) {
            if ($only && $src['id'] !== $only && $src['section'] !== $only) continue;
            $results[] = self::runSource($src);
        }
        // hide expired posts and anything older than 60 days
        Db::run("UPDATE listings SET is_active = FALSE WHERE is_active AND
                 ((last_date IS NOT NULL AND last_date < CURRENT_DATE) OR published_at < now() - interval '60 days')");
        echo "[ingest] done\n";
        return $results;
    }

    private static function runSource(array $src): array
    {
        $runId = Db::run('INSERT INTO fetch_runs (source_id) VALUES (?) RETURNING id', [$src['id']])->fetchColumn();
        $found = 0; $inserted = 0; $error = null;
        try {
            $news = new NewsFetcher();
            $items = $src['type'] === 'adzuna' ? (new AdzunaFetcher())->fetch($src) : $news->fetch($src);
            $found = count($items);

            // only visit/save URLs we don't have yet
            $known = [];
            if ($items) {
                $in = implode(',', array_fill(0, count($items), '?'));
                $known = array_flip(Db::run("SELECT source_url FROM listings WHERE source_url IN ($in)", array_column($items, 'url'))->fetchAll(\PDO::FETCH_COLUMN));
            }
            $fresh = array_slice(array_filter($items, fn($i) => !isset($known[$i['url']])), 0, (int) env('MAX_NEW_PER_SOURCE', '40'));

            $insert = Db::pdo()->prepare(
                'INSERT INTO listings (section, source_id, source_name, title, title_key, summary, source_url, image_url,
                   organisation, location, category, salary_min, salary_max, last_date, published_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON CONFLICT DO NOTHING');

            foreach ($fresh as $item) {
                if ($src['type'] === 'news') $item = $news->enrich($item);
                $text = $item['title'] . ' ' . $item['summary'] . ' ' . ($item['body'] ?? '');
                $insert->execute([
                    $src['section'], $src['id'], $src['name'],
                    mb_substr($item['title'], 0, 500),
                    self::titleKey($item['title']),
                    mb_substr($item['summary'], 0, 400),          // short excerpt only; full details stay on the source site
                    $item['url'],
                    $item['image_url'] ?? null,
                    mb_substr($item['organisation'] ?? self::findOrg($item['title']) ?? '', 0, 200) ?: null,
                    $item['location'] ?? ($src['section'] === 'northeast' ? self::findState($text) : null),
                    $item['category'] ?? null,
                    $item['salary_min'] ?? null,
                    $item['salary_max'] ?? null,
                    self::findLastDate($text),
                    self::toTimestamp($item['published_at'] ?? null),
                ]);
                $inserted += $insert->rowCount();
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            echo "  [{$src['id']}] ERROR $error\n";
        }
        Db::run('UPDATE fetch_runs SET finished_at = now(), found = ?, inserted = ?, error = ? WHERE id = ?', [$found, $inserted, $error, $runId]);
        echo "  [{$src['id']}] found $found, new $inserted\n";
        return ['source' => $src['id'], 'found' => $found, 'inserted' => $inserted, 'error' => $error];
    }

    public static function titleKey(string $t): string
    {
        $t = preg_replace('/[^a-z0-9 ]/', ' ', strtolower($t));
        $t = preg_replace('/\b(recruitment|vacancy|vacancies|apply|online|now|for|of|the|in|at|and|posts?|20\d\d)\b/', ' ', $t);
        return trim(preg_replace('/\s+/', ' ', $t));
    }

    public static function findLastDate(string $text): ?string
    {
        if (!preg_match('/last\s+date[^0-9]{0,40}(\d{1,2})(?:st|nd|rd|th)?[\s\-\/.]*([a-z]{3,9}|\d{1,2})[\s\-\/.,]*(\d{4})/i', $text, $m)) return null;
        $month = ctype_digit($m[2]) ? (int) $m[2] : (self::MONTHS[strtolower(substr($m[2], 0, 3))] ?? 0);
        return checkdate($month, (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $month, $m[1]) : null;
    }

    private static function findOrg(string $title): ?string
    {
        return preg_match('/^(.{3,80}?)\s+(recruitment|job|vacanc|walk|hiring|career)/i', $title, $m) ? trim($m[1]) : null;
    }

    private static function findState(string $text): ?string
    {
        foreach (self::NE_STATES as $s) if (preg_match('/\b' . $s . '\b/i', $text)) return $s;
        foreach (self::NE_CITIES as $c => $s) if (preg_match('/\b' . $c . '\b/i', $text)) return $s;
        return null;
    }

    private static function toTimestamp(?string $s): string
    {
        // drop a leading weekday ("Sat, 01 Nov 2026 ...") — a wrong weekday would make PHP shift the date
        $t = $s ? strtotime(preg_replace('/^[A-Za-z]{3,9},\s*/', '', trim($s))) : false;
        return date('c', $t ?: time());
    }
}
