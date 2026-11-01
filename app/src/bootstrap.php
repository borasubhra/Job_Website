<?php
declare(strict_types=1);

// Increase on every release, so you can check which version is running: /api/health
const APP_VERSION = '2026-11-01.7 (import page)';

// Autoload App\* classes from src/
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) require $file;
    }
});

// Load ../../.env when running outside Docker (Docker passes the same values as environment variables)
$envFile = dirname(__DIR__, 2) . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if (getenv($k) === false) putenv("$k=$v");
    }
}

function env(string $key, ?string $default = null): ?string
{
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Indian number format: 1200000 -> ₹12,00,000 */
function inr(int|float|null $n): string
{
    if (!$n) return '';
    $s = (string) (int) round((float) $n);
    $last3 = substr($s, -3); $rest = substr($s, 0, -3);
    if ($rest !== '') $last3 = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) . ',' . $last3;
    return '₹' . $last3;
}

date_default_timezone_set(env('FETCH_TIMEZONE', 'Asia/Kolkata'));
