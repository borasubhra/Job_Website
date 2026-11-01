<?php
declare(strict_types=1);

namespace App\Fetchers;

use App\Http;

/**
 * Reads a news site: RSS/Atom feed first; if none, scans listing pages for article links,
 * then reads each new article's meta tags (description, date, image).
 */
final class NewsFetcher
{
    private const JOB_WORDS = '/\b(recruitment|vacanc(y|ies)|jobs?|career|walk[- ]?in|interview|apply|posts?|hiring|notification|apprentice|bharti|engagement of|openings?)\b/i';

    /**
     * Collect up to `limit` job items. Reads feed/listing pages 1, 2, 3 ... (up to `maxPages`)
     * until enough jobs are found, because most sites show only ~10 posts per page.
     *
     * @return list<array<string,mixed>>
     */
    public function fetch(array $src): array
    {
        $limit = self::limit($src);
        $keep = fn(array $i): bool => $i['title'] !== '' && $i['url'] !== ''
            && (!empty($src['jobsOnly']) || preg_match(self::JOB_WORDS, $i['title'] . ' ' . $i['summary']));

        $items = $this->fromFeeds($src, $keep, $limit) ?? $this->fromPages($src, $keep, $limit);
        $out = [];
        foreach ($items as $i) {
            if (Http::allowed($i['url'])) $out[] = $i;
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    public static function limit(array $src): int
    {
        return max(1, (int) ($src['limit'] ?? env('MAX_NEW_PER_SOURCE', '40')));
    }

    /** RSS/Atom feed, page by page (WordPress: ?paged=2). Returns null if no feed works. */
    private function fromFeeds(array $src, callable $keep, int $limit): ?array
    {
        foreach ($src['feeds'] ?? [] as $feedUrl) {
            $items = [];
            for ($page = 1; $page <= ($src['maxPages'] ?? 5); $page++) {
                $url = $page === 1 ? $feedUrl : $feedUrl . (str_contains($feedUrl, '?') ? '&' : '?') . "paged=$page";
                try {
                    if (!Http::allowed($url)) { $this->log($src, "robots.txt disallows $url"); break; }
                    $added = 0;
                    foreach ($this->parseFeed(Http::get($url)['body']) as $i) {
                        if (!isset($items[$i['url']]) && $keep($i)) { $items[$i['url']] = $i; $added++; }
                    }
                    $this->log($src, "RSS page $page: $url (+$added, total " . count($items) . ')');
                    if ($added === 0 || count($items) >= $limit) break;   // no more pages, or enough
                } catch (\Throwable $e) {
                    $this->log($src, "RSS failed $url: {$e->getMessage()}");
                    break;                                                // page 2+ missing is normal
                }
            }
            if ($items) return array_values($items);
        }
        return null;
    }

    private function parseFeed(string $body): array
    {
        $xml = @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false) throw new \RuntimeException('not a valid feed');
        $items = [];
        foreach ($xml->channel->item ?? [] as $it) {                          // RSS 2.0
            $media = $it->children('http://search.yahoo.com/mrss/');
            $items[] = [
                'title' => self::clean((string) $it->title),
                'url' => trim((string) $it->link),
                'summary' => self::clean((string) $it->description),
                'published_at' => (string) $it->pubDate ?: null,
                'image_url' => (string) ($it->enclosure['url'] ?? '') ?: ((string) ($media->content?->attributes()['url'] ?? '') ?: null),
            ];
        }
        foreach ($xml->entry ?? [] as $it) {                                  // Atom
            $items[] = [
                'title' => self::clean((string) $it->title),
                'url' => (string) ($it->link['href'] ?? ''),
                'summary' => self::clean((string) ($it->summary ?: $it->content)),
                'published_at' => (string) ($it->published ?: $it->updated) ?: null,
                'image_url' => null,
            ];
        }
        return $items;
    }

    /** Fallback: scan listing pages (page 2 = `pagePattern` with {n}, default WordPress ".../page/2"). */
    private function fromPages(array $src, callable $keep, int $limit): array
    {
        foreach ($src['pages'] ?? [] as $idx => $pageUrl) {
            $items = [];
            for ($page = 1; $page <= ($src['maxPages'] ?? 5); $page++) {
                $url = $page === 1 ? $pageUrl
                    : (isset($src['pagePattern']) && $idx === 0 ? str_replace('{n}', (string) $page, $src['pagePattern'])
                                                                : rtrim($pageUrl, '/') . "/page/$page");
                try {
                    if (!Http::allowed($url)) { $this->log($src, "robots.txt disallows $url"); break; }
                    $res = Http::get($url);
                    $host = parse_url($res['url'], PHP_URL_HOST);
                    $added = 0;
                    foreach (self::dom($res['body'])->getElementsByTagName('a') as $a) {
                        $title = self::clean($a->textContent);
                        $href = self::absolute($a->getAttribute('href'), $res['url']);
                        if (!$href || parse_url($href, PHP_URL_HOST) !== $host) continue;
                        $len = mb_strlen($title);
                        if ($len < 25 || $len > 250 || isset($items[$href])) continue;
                        $i = ['title' => $title, 'url' => $href, 'summary' => '', 'published_at' => null, 'image_url' => null];
                        if ($keep($i)) { $items[$href] = $i; $added++; }
                    }
                    $this->log($src, "page scan $page: $url (+$added, total " . count($items) . ')');
                    if ($added === 0 || count($items) >= $limit) break;
                } catch (\Throwable $e) {
                    $this->log($src, "page failed $url: {$e->getMessage()}");
                    break;
                }
            }
            if ($items) return array_values($items);
        }
        return [];
    }

    /** Fill description / date / image from the article page (only called for new items). */
    public function enrich(array $item): array
    {
        try {
            $dom = self::dom(Http::get($item['url'])['body']);
            $meta = [];
            foreach ($dom->getElementsByTagName('meta') as $m) {
                $k = $m->getAttribute('property') ?: $m->getAttribute('name');
                if ($k !== '' && !isset($meta[$k])) $meta[$k] = trim($m->getAttribute('content'));
            }
            if ($item['summary'] === '') $item['summary'] = $meta['og:description'] ?? $meta['description'] ?? '';
            if (!$item['published_at']) {
                $item['published_at'] = $meta['article:published_time'] ?? null;
                if (!$item['published_at']) foreach ($dom->getElementsByTagName('time') as $t) {
                    if ($t->getAttribute('datetime')) { $item['published_at'] = $t->getAttribute('datetime'); break; }
                }
            }
            $item['image_url'] ??= ($meta['og:image'] ?? null) ?: null;
            $article = $dom->getElementsByTagName('article')->item(0) ?? $dom->getElementsByTagName('body')->item(0);
            $item['body'] = mb_substr(self::clean($article?->textContent ?? ''), 0, 20000);
        } catch (\Throwable) {
            // keep what we have
        }
        sleep(1); // be polite to the source site
        return $item;
    }

    private static function dom(string $html): \DOMDocument
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        return $dom;
    }

    private static function clean(string $s): string
    {
        $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    private static function absolute(string $href, string $base): ?string
    {
        $href = trim(explode('#', $href)[0]);
        if ($href === '' || preg_match('#^(mailto|tel|javascript):#i', $href)) return null;
        if (preg_match('#^https?://#i', $href)) return $href;
        $b = parse_url($base);
        $origin = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
        if (str_starts_with($href, '//')) return $b['scheme'] . ':' . $href;
        if (str_starts_with($href, '/')) return $origin . $href;
        $dir = preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/');
        return $origin . $dir . $href;
    }

    private function log(array $src, string $msg): void
    {
        echo "  [{$src['id']}] $msg\n";
    }
}
