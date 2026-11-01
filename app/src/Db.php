<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s',
                env('DB_HOST', 'localhost'), env('DB_PORT', '5432'), env('POSTGRES_DB', 'jobportal'));
            self::$pdo = new PDO($dsn, env('POSTGRES_USER'), env('POSTGRES_PASSWORD'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$pdo;
    }

    /** Run a query with parameters and return the statement. */
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /** Create tables if missing (safe to call on every run). */
    public static function migrate(): void
    {
        self::pdo()->exec(file_get_contents(dirname(__DIR__, 2) . '/db/schema.sql'));
    }

    /** Wait until the database accepts connections (used by the scheduler on start). */
    public static function waitUntilReady(int $seconds = 60): void
    {
        $end = time() + $seconds;
        while (true) {
            try { self::pdo(); return; }
            catch (\PDOException $e) {
                if (time() > $end) throw $e;
                sleep(2);
            }
        }
    }
}
