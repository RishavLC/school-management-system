<?php
/**
 * Database
 * Thin singleton wrapper around PDO. All data access in the app goes
 * through here so that connection settings and error handling live
 * in exactly one place.
 */
class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $cfg = require __DIR__ . '/../config/database.php';
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['host'],
                $cfg['port'],
                $cfg['dbname'],
                $cfg['charset']
            );

            try {
                self::$instance = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                // Never leak connection strings/credentials to the browser.
                error_log('DB connection failed: ' . $e->getMessage());
                http_response_code(500);
                die('Database connection failed. Please check config/database.php and ensure MySQL is running.');
            }
        }
        return self::$instance;
    }
}
