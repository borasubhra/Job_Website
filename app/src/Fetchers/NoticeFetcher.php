<?php
declare(strict_types=1);

namespace App\Fetchers;

use App\Http;

/**
 * Reads official "notice board" pages (government / PSU recruitment pages). These rarely have RSS:
 * they are lists or tables of links, often to PDF notifications, sometimes on another .gov.in host.
 * Each recruitment-related link becomes one job; the text of its table row / list item is the summary.
 */
final class NoticeFetcher
{
    private const JOB_WORDS = '/\b(recruitment|recruit|advertisement|advt\.?|vacanc(y|ies)|walk[- ]?in|interview|engagement|appointment of|applications? (are )?invited|posts? of|for the posts?|hiring|apprentice|contractual|filling up)\b/i';
    private const NOT_JOBS = '/\b(result|admit card|answer key|syllabus|tender|quotation|e-?auction|rti|corrigendum to tender|merit list of students)\b/i';
    private const MONTHS = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];

    /** @return list<array<string,mixed>> */
    public function fetch(array $src): array
    {
        $limit = (int) ($src['limit'] ?? 15);
        $items = [];
        foreach ($src['pages'] ?? [] as $pageUrl) {
            try {
                if (!Http::allowed($pageUrl)) { $this->log($src, "robots.txt disallows $pageUrl"); continue; }
                $res = Http::get($pageUrl);
                $before = count($items);
                $dom = new \DOMDocument();
                libxml_use_internal_errors(true);
                $dom->loadHTML('<?xml encoding="UTF-8">' . $res['body'], LIBXML_NOWARNING | LIBXML_NOERROR);
                libxml_clear_errors();

                foreach ($dom->getElementsByTagName('a') as $a) {
                    $href = self::absolute($a->getAttribute('href'), $res['url']);
                    if (!$href || isset($items[$href])) continue;
                    $linkText = self::text($a) ?: self::clean($a->getAttribute('title'));
                    $row = self::row($a);                       // null for menu/header links -> link text only
                    $rowText = $row ? self::text($row) : '';
                    // short link text such as "Download" / "View" -> use the row's subject as the title
                    $title = mb_strlen($linkText) >= 20 ? $linkText : ($row ? self::subject($row) : '');
                    $title = trim(preg_replace(['/^\d{1,4}\s*[.)]?\s+/', '/\s*\b(download|view|click here|new|pdf)\b\s*(\(\s*[\d.]+\s*[km]b\s*\))?\s*$/i'], '', $title));
                    if (mb_strlen($title) < 15) continue;
                    $all = "$title $rowText";
                    if (!preg_match(self::JOB_WORDS, $all) || preg_match(self::NOT_JOBS, $all)) continue;

                    $date = self::findDate("$all " . urldecode($href));
                    if ($date && $date < date('Y-m-d', strtotime('-60 days'))) continue;   // old notice

                    $items[$href] = [
                        'title' => mb_substr($title, 0, 250),
                        'url' => $href,
                        'summary' => $rowText !== $title ? mb_substr(preg_replace('/^\d{1,4}\s*[.)]?\s+/', '', $rowText), 0, 400) : '',
                        'published_at' => $date,
                        'image_url' => null,
                    ];
                }
                $this->log($src, "notice board: $pageUrl (+" . (count($items) - $before) . ' recruitment links)');
            } catch (\Throwable $e) {
                $this->log($src, "page failed $pageUrl: {$e->getMessage()}");
            }
        }
        // notice boards list newest first; keep the top `limit` (avoids importing years of old notices on the first run)
        return array_slice(array_values($items), 0, $limit);
    }

    /** The table row / list item / paragraph that contains the link (null if none, e.g. a menu link). */
    private static function row(\DOMNode $n): ?\DOMNode
    {
        for ($p = $n->parentNode, $depth = 0; $p && $depth < 6; $p = $p->parentNode, $depth++) {
            $name = strtolower($p->nodeName);
            if (in_array($name, ['nav', 'header', 'footer'], true)) return null;
            if (in_array($name, ['tr', 'li', 'p', 'article'], true)) return $p;
        }
        return null;
    }

    /** For a table row: the longest cell (the "Subject" column). Otherwise the whole text. */
    private static function subject(\DOMNode $row): string
    {
        if (strtolower($row->nodeName) !== 'tr') return self::text($row);
        $best = '';
        foreach ($row->childNodes as $cell) {
            if ($cell instanceof \DOMElement && mb_strlen($t = self::text($cell)) > mb_strlen($best)) $best = $t;
        }
        return $best;
    }

    /** Text of a node with spaces between elements (textContent glues table cells together). */
    private static function text(\DOMNode $n): string
    {
        $parts = [];
        $walk = function (\DOMNode $x) use (&$walk, &$parts): void {
            if ($x instanceof \DOMText) { $parts[] = $x->nodeValue; return; }
            if (in_array(strtolower($x->nodeName), ['script', 'style'], true)) return;
            foreach ($x->childNodes as $c) $walk($c);
            $parts[] = ' ';
        };
        $walk($n);
        return self::clean(implode('', $parts));
    }

    /** First date in the text that is not in the future (notice date), as YYYY-MM-DD. */
    public static function findDate(string $text): ?string
    {
        $today = date('Y-m-d');
        preg_match_all('/\b(\d{1,2})[.\/-](\d{1,2})[.\/-](20\d\d)\b|\b(\d{1,2})(?:st|nd|rd|th)?\s+([a-z]{3,9})\.?,?\s+(20\d\d)\b|\b([a-z]{3,9})\.?\s+(\d{1,2}),?\s+(20\d\d)\b/i', $text, $all, PREG_SET_ORDER);
        foreach ($all as $m) {
            if (!empty($m[3])) { [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]]; }
            elseif (!empty($m[6])) { [$d, $mo, $y] = [(int) $m[4], self::MONTHS[strtolower(substr($m[5], 0, 3))] ?? 0, (int) $m[6]]; }
            else { [$d, $mo, $y] = [(int) $m[8], self::MONTHS[strtolower(substr($m[7], 0, 3))] ?? 0, (int) $m[9]]; }
            if (!checkdate($mo, $d, $y)) continue;
            $iso = sprintf('%04d-%02d-%02d', $y, $mo, $d);
            if ($iso <= $today) return $iso;
        }
        return null;
    }

    private static function clean(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private static function absolute(string $href, string $base): ?string
    {
        $href = trim(explode('#', $href)[0]);
        if ($href === '' || preg_match('#^(mailto|tel|javascript):#i', $href)) return null;
        if (preg_match('#^https?://#i', $href)) return str_replace(' ', '%20', $href);
        $b = parse_url($base);
        $origin = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
        if (str_starts_with($href, '//')) return $b['scheme'] . ':' . $href;
        $path = str_starts_with($href, '/') ? $href : preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/') . $href;
        // resolve ./ and ../
        $parts = [];
        foreach (explode('/', $path) as $seg) {
            if ($seg === '..') array_pop($parts); elseif ($seg !== '.') $parts[] = $seg;
        }
        return $origin . str_replace(' ', '%20', implode('/', $parts));
    }

    private function log(array $src, string $msg): void
    {
        echo "  [{$src['id']}] $msg\n";
    }
}
