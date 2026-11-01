<?php
declare(strict_types=1);

namespace App;

/** Read-side queries used by the website and the JSON API. */
final class Listings
{
    public const PAGE_SIZE = 20;

    public static function search(string $section, string $q = '', string $location = '', int $page = 1): array
    {
        $where = ['section = ?', 'is_active']; $params = [$section];
        if ($q !== '') { $where[] = '(title ILIKE ? OR summary ILIKE ? OR organisation ILIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%"); }
        if ($location !== '') { $where[] = 'location ILIKE ?'; $params[] = "%$location%"; }
        $w = implode(' AND ', $where);
        $total = (int) Db::run("SELECT count(*) FROM listings WHERE $w", $params)->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min(max(1, $page), $pages);
        // Our own order: newest day first; within a day, sources are mixed in a stable per-day order,
        // so the list never mirrors any source site.
        $items = Db::run(
            "SELECT id, title, summary, organisation, location, category, source_name, last_date, published_at,
                    image_url, salary_min, salary_max
               FROM listings WHERE $w
              ORDER BY (published_at AT TIME ZONE 'Asia/Kolkata')::date DESC, md5(id::text || CURRENT_DATE::text)
              LIMIT " . self::PAGE_SIZE . ' OFFSET ' . (($page - 1) * self::PAGE_SIZE), $params)->fetchAll();
        return compact('page', 'pages', 'total', 'items');
    }

    public static function find(int $id): ?array
    {
        return Db::run('SELECT * FROM listings WHERE id = ?', [$id])->fetch() ?: null;
    }

    public static function status(): array
    {
        return Db::run('SELECT DISTINCT ON (source_id) source_id, started_at, finished_at, found, inserted, error
                          FROM fetch_runs ORDER BY source_id, started_at DESC')->fetchAll();
    }
}
