<?php
declare(strict_types=1);

namespace App\Fetchers;

use App\Http;

/** Official Adzuna jobs API (https://developer.adzuna.com), country = India. */
final class AdzunaFetcher
{
    public function fetch(array $src): array
    {
        $id = env('ADZUNA_APP_ID'); $key = env('ADZUNA_APP_KEY');
        if (!$id || !$key) { echo "  [{$src['id']}] skipped: set ADZUNA_APP_ID and ADZUNA_APP_KEY in .env\n"; return []; }
        $out = [];
        foreach ($src['queries'] as $what) {
            for ($page = 1; $page <= ($src['pagesPerQuery'] ?? 1); $page++) {
                $url = "https://api.adzuna.com/v1/api/jobs/in/search/$page?" . http_build_query([
                    'app_id' => $id, 'app_key' => $key, 'results_per_page' => 50, 'max_days_old' => 3, 'what' => $what,
                ]);
                try {
                    $data = json_decode(Http::get($url)['body'], true, flags: JSON_THROW_ON_ERROR);
                    foreach ($data['results'] ?? [] as $j) {
                        $out[] = [
                            'title' => trim(strip_tags($j['title'] ?? '')),
                            'url' => $j['redirect_url'] ?? '',
                            'summary' => trim(strip_tags($j['description'] ?? '')),
                            'published_at' => $j['created'] ?? null,
                            'image_url' => null,
                            'organisation' => $j['company']['display_name'] ?? null,
                            'location' => $j['location']['display_name'] ?? null,
                            'category' => $j['category']['label'] ?? null,
                            'salary_min' => isset($j['salary_min']) ? (int) round($j['salary_min']) : null,
                            'salary_max' => isset($j['salary_max']) ? (int) round($j['salary_max']) : null,
                        ];
                    }
                } catch (\Throwable $e) {
                    echo "  [{$src['id']}] \"$what\" failed: {$e->getMessage()}\n";
                }
                sleep(1);
            }
        }
        return array_values(array_filter($out, fn($i) => $i['title'] !== '' && $i['url'] !== ''));
    }
}
