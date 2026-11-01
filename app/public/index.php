<?php
// Front controller: every request comes here (Apache .htaccess, or `php -S localhost:8080 -t public public/index.php`).
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (PHP_SAPI === 'cli-server' && $path !== '/' && is_file(__DIR__ . $path)) return false;   // static files for php -S

require __DIR__ . '/../src/bootstrap.php';

use App\Listings;

const SECTIONS = [
    'northeast' => [
        'url' => '/', 'title' => 'North East Jobs',
        'sub' => 'Government & private jobs in Assam and the North East, updated daily.',
        'locations' => ['Assam', 'Arunachal Pradesh', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Tripura', 'Sikkim'],
    ],
    'india' => [
        'url' => '/india-jobs', 'title' => 'All India Jobs',
        'sub' => 'Latest openings across India, updated daily.',
        'locations' => ['Delhi', 'Mumbai', 'Bangalore', 'Hyderabad', 'Chennai', 'Kolkata', 'Pune', 'Remote'],
    ],
];

function render(string $template, array $vars): void
{
    extract($vars);
    ob_start();
    require __DIR__ . "/../templates/$template.php";
    $content = ob_get_clean();
    require __DIR__ . '/../templates/layout.php';
}

function json_out(mixed $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function slug(string $s): string
{
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-') ?: 'job';
}

try {
    switch (true) {
        case $path === '/' || $path === '/india-jobs':
            $section = $path === '/' ? 'northeast' : 'india';
            $q = trim((string) ($_GET['q'] ?? ''));
            $location = trim((string) ($_GET['location'] ?? ''));
            $result = Listings::search($section, $q, $location, (int) ($_GET['page'] ?? 1));
            render('list', ['section' => $section, 'meta' => SECTIONS[$section], 'q' => $q, 'location' => $location, 'result' => $result,
                            'pageTitle' => SECTIONS[$section]['title']]);
            break;

        case (bool) preg_match('#^/job/(\d+)(?:-[a-z0-9-]*)?$#', $path, $m):
            $job = Listings::find((int) $m[1]);
            if (!$job) { http_response_code(404); render('notfound', ['pageTitle' => 'Not found', 'section' => '']); break; }
            render('job', ['job' => $job, 'section' => $job['section'], 'pageTitle' => $job['title']]);
            break;

        // JSON API (for a future mobile app etc.)
        case $path === '/api/listings':
            json_out(Listings::search(($_GET['section'] ?? '') === 'india' ? 'india' : 'northeast',
                trim((string) ($_GET['q'] ?? '')), trim((string) ($_GET['location'] ?? '')), (int) ($_GET['page'] ?? 1)));
            break;
        case $path === '/api/status':
            json_out(Listings::status());
            break;
        case $path === '/api/health':
            App\Db::run('SELECT 1');
            json_out(['status' => 'ok', 'db' => 'connected', 'php' => PHP_VERSION]);
            break;

        default:
            http_response_code(404);
            render('notfound', ['pageTitle' => 'Not found', 'section' => '']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log($e->getMessage());
    if (str_starts_with($path, '/api/')) { json_out(['status' => 'error', 'db' => 'not reachable'], 500); }
    else echo '<h1>Database not reachable</h1><p>Is Docker running? Try <code>docker compose up -d</code>.</p>';
}
