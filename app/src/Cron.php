<?php
declare(strict_types=1);

namespace App;

/** Minimal 5-field cron matcher: "min hour day month weekday" with *, lists, ranges and steps. */
final class Cron
{
    public static function matches(string $expr, \DateTimeInterface $t): bool
    {
        $f = preg_split('/\s+/', trim($expr));
        if (count($f) !== 5) throw new \InvalidArgumentException("Bad FETCH_CRON: $expr");
        return self::field($f[0], (int) $t->format('i'), 0, 59)
            && self::field($f[1], (int) $t->format('G'), 0, 23)
            && self::field($f[2], (int) $t->format('j'), 1, 31)
            && self::field($f[3], (int) $t->format('n'), 1, 12)
            && (self::field($f[4], (int) $t->format('w'), 0, 7) || ($t->format('w') === '0' && self::field($f[4], 7, 0, 7)));
    }

    private static function field(string $spec, int $v, int $min, int $max): bool
    {
        foreach (explode(',', $spec) as $part) {
            [$range, $step] = array_pad(explode('/', $part, 2), 2, '1');
            [$lo, $hi] = $range === '*' ? [$min, $max] : array_pad(array_map('intval', explode('-', $range, 2)), 2, null);
            $hi ??= str_contains($part, '/') ? $max : $lo;
            if ($v >= $lo && $v <= $hi && ($v - $lo) % max(1, (int) $step) === 0) return true;
        }
        return false;
    }
}
