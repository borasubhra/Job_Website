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

    /** @return list<array<string,mixed>> */
    public function fetch(array $src): array
    {
        $items = $this->fromFeeds($src) ?? $this->fromPages($src);
        if (empty($src['jobsOnly'])) {
            $items = array_filter($items, fn($i) => preg_match(self::JOB_WORDS, $i['title'] . ' ' . $i['summary']));
        }
        $out = [];
        foreach ($items as $i) {
            if ($i['title'] !== '' && $i['url'] !== '' && Http::allowed($i['url'])) $out[] = $i;
        }
        return $out;
    }

    private function fromFeeds(array $src): ?array
    {
        foreach ($src['feeds'] ?? [] as $feedUrl) {
            try {
                if (!Http::allowed($feedUrl)) { $this->log($src, "robots.txt disallows $feedUrl"); continue; }
                $xml = @simplexml_load_string(Http::get($feedUrl)['body'], 'SimpleXMLElement', LIBXML_NOCDATA);
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
                if ($items) { $this->log($src, "RSS ok: $feedUrl (" . count($items) . ' items)'); return $items; }
            } catch (\Throwable $e) {
                $this->log($src, "RSS failed $feedUrl: {$e->getMessage()}");
            }
        }
        return null;
    }

    private function fromPages(array $src): array
    {
        $links = [];
        foreach ($src['pages'] ?? [] as $pageUrl) {
            try {
                if (!Http::allowed($pageUrl)) { $this->log($src, "robots.txt disallows $pageUrl"); continue; }
                $res = Http::get($pageUrl);
                $host = parse_url($res['url'], PHP_URL_HOST);
                $dom = self::dom($res['body']);
                foreach ($dom->getElementsByTagName('a') as $a) {
                    $title = self::clean($a->textContent);
                    $href = self::absolute($a->getAttribute('href'), $res['url']);
                    if (!$href || parse_url($href, PHP_URL_HOST) !== $host) continue;
                    $len = mb_strlen($title);
                    if ($len < 25 || $len > 250) continue;
                    $links[$href] ??= $title;
                }
                $this->log($src, "page scan: $pageUrl (" . count($links) . ' candidate links)');
                if ($links) break;
            } catch (\Throwable $e) {
                $this->log($src, "page failed $pageUrl: {$e->getMessage()}");
            }
        }
        $out = [];
        foreach ($links as $url => $title) $out[] = ['title' => $title, 'url' => $url, 'summary' => '', 'published_at' => null, 'image_url' => null];
        return $out;
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
