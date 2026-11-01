<?php
declare(strict_types=1);

namespace App;

/** Admin pages: list, add, edit, hide/show, delete hand-added jobs (source_id = 'manual'). */
final class Admin
{
    public const LOCATIONS = ['Assam', 'Arunachal Pradesh', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Tripura', 'Sikkim', 'All India', 'Remote'];

    /** Route /admin/* requests. Returns [template, vars] to render, or null after a redirect. */
    public static function handle(string $path): ?array
    {
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store');
        $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

        if ($path === '/admin/login') {
            $error = Auth::setupProblem();
            if ($post && !$error) {
                if (Auth::attempt(trim((string) ($_POST['user'] ?? '')), (string) ($_POST['password'] ?? ''))) return self::redirect('/admin');
                $error = 'Wrong username or password.';
            }
            return ['admin/login', ['error' => $error]];
        }

        if (!Auth::check()) return self::redirect('/admin/login');
        if ($post && !Auth::verifyCsrf()) { http_response_code(400); return ['admin/message', ['message' => 'Form expired. Go back, reload the page and try again.']]; }

        if ($path === '/admin/logout' && $post) { Auth::logout(); return self::redirect('/admin/login'); }

        if ($path === '/admin' || $path === '/admin/') {
            $show = in_array($_GET['show'] ?? '', ['manual', 'auto', 'hidden'], true) ? $_GET['show'] : 'manual';
            $where = match ($show) { 'manual' => "source_id = 'manual'", 'auto' => "source_id <> 'manual' AND is_active", 'hidden' => 'NOT is_active' };
            $jobs = Db::run("SELECT id, section, source_id, source_name, title, organisation, location, last_date, is_active, published_at
                               FROM listings WHERE $where ORDER BY published_at DESC, id DESC LIMIT 200")->fetchAll();
            $counts = Db::run("SELECT count(*) FILTER (WHERE source_id = 'manual') manual,
                                      count(*) FILTER (WHERE source_id <> 'manual' AND is_active) auto,
                                      count(*) FILTER (WHERE NOT is_active) hidden FROM listings")->fetch();
            return ['admin/list', ['jobs' => $jobs, 'show' => $show, 'counts' => $counts, 'flash' => self::flash()]];
        }

        if ($path === '/admin/imports') {
            $results = null;
            if ($post) {
                $file = basename((string) ($_POST['file'] ?? ''));
                $results = $file !== '' ? [Importer::import(Importer::dir() . '/' . $file)] : Importer::importNew();
            }
            $sources = Db::run("SELECT section, source_name, count(*) FILTER (WHERE is_active) live, count(*) FILTER (WHERE NOT is_active) hidden
                                  FROM listings GROUP BY 1, 2 ORDER BY 1 DESC, 3 DESC")->fetchAll();
            return ['admin/imports', ['files' => Importer::files(), 'results' => $results, 'sources' => $sources, 'dir' => Importer::dir()]];
        }

        if ($path === '/admin/new' || preg_match('#^/admin/edit/(\d+)$#', $path, $m)) {
            $id = isset($m[1]) ? (int) $m[1] : null;
            $job = $id ? Listings::find($id) : null;
            if ($id && (!$job || $job['source_id'] !== 'manual')) { http_response_code(404); return ['admin/message', ['message' => 'Only jobs added on this admin page can be edited.']]; }
            $data = $job ?? ['section' => 'northeast', 'title' => '', 'organisation' => '', 'location' => 'Assam', 'last_date' => '',
                             'source_url' => '', 'summary' => '', 'category' => '', 'is_active' => true];
            $errors = [];
            if ($post) {
                [$data, $errors] = self::validate($_POST);
                if (!$errors) {
                    $id ? self::update($id, $data) : $id = self::insert($data);
                    self::flash($job ? 'Job updated.' : 'Job added and published.');
                    return self::redirect('/admin');
                }
            }
            return ['admin/form', ['data' => $data, 'errors' => $errors, 'id' => $id]];
        }

        if ($post && preg_match('#^/admin/(toggle|delete)/(\d+)$#', $path, $m)) {
            $id = (int) $m[2];
            if ($m[1] === 'toggle') {
                Db::run('UPDATE listings SET is_active = NOT is_active WHERE id = ?', [$id]);
                self::flash('Job visibility changed.');
            } else {
                Db::run("DELETE FROM listings WHERE id = ? AND source_id = 'manual'", [$id]);
                self::flash('Job deleted.');
            }
            return self::redirect($_POST['back'] ?? '/admin');
        }

        http_response_code(404);
        return ['admin/message', ['message' => 'Page not found.']];
    }

    /** @return array{0: array, 1: array<string,string>} */
    private static function validate(array $in): array
    {
        $d = [
            'section'      => ($in['section'] ?? '') === 'india' ? 'india' : 'northeast',
            'title'        => trim((string) ($in['title'] ?? '')),
            'organisation' => trim((string) ($in['organisation'] ?? '')),
            'location'     => trim((string) ($in['location'] ?? '')),
            'category'     => trim((string) ($in['category'] ?? '')),
            'last_date'    => trim((string) ($in['last_date'] ?? '')),
            'source_url'   => trim((string) ($in['source_url'] ?? '')),
            'summary'      => trim((string) ($in['summary'] ?? '')),
            'is_active'    => !empty($in['is_active']),
        ];
        $e = [];
        if (mb_strlen($d['title']) < 5 || mb_strlen($d['title']) > 250) $e['title'] = 'Enter a job title (5–250 characters).';
        if ($d['organisation'] === '' || mb_strlen($d['organisation']) > 200) $e['organisation'] = 'Enter the organisation / department.';
        if (mb_strlen($d['location']) > 200) $e['location'] = 'Location is too long.';
        if (mb_strlen($d['category']) > 120) $e['category'] = 'Too long.';
        if ($d['last_date'] !== '') {
            $dt = \DateTime::createFromFormat('!Y-m-d', $d['last_date']);
            if (!$dt || $dt->format('Y-m-d') !== $d['last_date']) $e['last_date'] = 'Enter a valid date.';
        }
        if ($d['source_url'] !== '' && (!filter_var($d['source_url'], FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $d['source_url'])))
            $e['source_url'] = 'Enter a full link starting with https:// (or leave it empty and write how to apply in the description).';
        if (mb_strlen($d['summary']) > 1500) $e['summary'] = 'Keep the description under 1500 characters.';
        return [$d, $e];
    }

    private static function insert(array $d): int
    {
        return (int) Db::run(
            "INSERT INTO listings (section, source_id, source_name, title, title_key, summary, source_url, organisation, location,
                                   category, last_date, published_at, is_active)
             VALUES (?, 'manual', 'JobPortal Desk', ?, ?, ?, ?, ?, ?, ?, ?, now(), ?) RETURNING id",
            [$d['section'], $d['title'], Ingest::titleKey($d['title']), $d['summary'], $d['source_url'], $d['organisation'],
             $d['location'] ?: null, $d['category'] ?: null, $d['last_date'] ?: null, $d['is_active'] ? 't' : 'f'])->fetchColumn();
    }

    private static function update(int $id, array $d): void
    {
        Db::run(
            "UPDATE listings SET section = ?, title = ?, title_key = ?, summary = ?, source_url = ?, organisation = ?, location = ?,
                    category = ?, last_date = ?, is_active = ? WHERE id = ? AND source_id = 'manual'",
            [$d['section'], $d['title'], Ingest::titleKey($d['title']), $d['summary'], $d['source_url'], $d['organisation'],
             $d['location'] ?: null, $d['category'] ?: null, $d['last_date'] ?: null, $d['is_active'] ? 't' : 'f', $id]);
    }

    private static function redirect(string $to): ?array
    {
        if (!str_starts_with($to, '/admin')) $to = '/admin';       // never redirect off-site
        header('Location: ' . $to, true, 303);
        return null;
    }

    private static function flash(?string $set = null): ?string
    {
        Auth::start();
        if ($set !== null) { $_SESSION['flash'] = $set; return null; }
        $msg = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $msg;
    }
}
