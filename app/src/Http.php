<?php
declare(strict_types=1);

namespace App;

/** Polite HTTP client: identifies itself, respects robots.txt, times out. */
final class Http
{
    private static array $robots = [];

    public static function userAgent(): string
    {
        return env('CRAWLER_USER_AGENT', 'JobPortalBot/1.0');
    }

    /** @return array{body:string,url:string,type:string} */
    public static function get(string $url, int $timeout = 20): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT      => self::userAgent(),
            CURLOPT_ENCODING       => '',
            CURLOPT_HTTPHEADER     => ['Accept: */*'],
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $final = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
        $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $err = curl_error($ch);
        if ($body === false) throw new \RuntimeException("fetch failed: $err");
        if ($status < 200 || $status >= 300) throw new \RuntimeException("HTTP $status for $url");
        return ['body' => (string) $body, 'url' => $final, 'type' => $type];
    }

    /** False if the site's robots.txt disallows our bot for this URL. */
    public static function allowed(string $url): bool
    {
        $p = parse_url($url);
        if (empty($p['host'])) return false;
        $origin = ($p['scheme'] ?? 'https') . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        if (!array_key_exists($origin, self::$robots)) {
            try { self::$robots[$origin] = self::parseRobots(self::get("$origin/robots.txt", 10)['body']); }
            catch (\Throwable) { self::$robots[$origin] = []; }    // no robots.txt -> everything allowed
        }
        $path = ($p['path'] ?? '/') . (isset($p['query']) ? '?' . $p['query'] : '');
        $best = null; $bestLen = -1;
        foreach (self::$robots[$origin] as [$allow, $pattern]) {
            if ($pattern === '') continue;
            $re = '#^' . str_replace(['\*', '\$'], ['.*', '$'], preg_quote($pattern, '#')) . '#';
            if (preg_match($re, $path) && strlen($pattern) > $bestLen) { $best = $allow; $bestLen = strlen($pattern); }
        }
        return $best ?? true;
    }

    /** Returns [[allow(bool), pattern], ...] for the group matching our bot, else for "*". */
    private static function parseRobots(string $txt): array
    {
        $token = strtolower(strtok(self::userAgent(), '/ '));
        $groups = []; $agents = []; $rules = []; $inRules = false;
        foreach (preg_split('/\R/', $txt) as $line) {
            $line = trim(preg_replace('/#.*/', '', $line));
            if (!str_contains($line, ':')) continue;
            [$k, $v] = array_map('trim', explode(':', $line, 2));
            $k = strtolower($k);
            if ($k === 'user-agent') {
                if ($inRules) { $groups[] = [$agents, $rules]; $agents = []; $rules = []; $inRules = false; }
                $agents[] = strtolower($v);
            } elseif ($k === 'allow' || $k === 'disallow') {
                $inRules = true;
                $rules[] = [$k === 'allow', $v];
            }
        }
        if ($agents) $groups[] = [$agents, $rules];
        $star = [];
        foreach ($groups as [$a, $r]) {
            if (in_array($token, $a, true)) return $r;
            if (in_array('*', $a, true)) $star = array_merge($star, $r);
        }
        return $star;
    }
}
