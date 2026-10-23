<?php
/* =====================================================
   STUDENTHUB - PRACTICAL 8
   Reusable PDO database connection (XAMPP defaults).

   Include it from any PHP file inside the php/ folder:
       require_once __DIR__ . '/db.php';
       $pdo = getDbConnection();

   - Uses PDO with exceptions (no silent failures).
   - One shared connection per request (static reuse).
   - Never prints passwords or stack traces to visitors.
   - Local-development credentials only; do not commit
     real production secrets to Git.
   ===================================================== */

declare(strict_types=1);

// Local XAMPP defaults. Standard installs use user "root"
// with an EMPTY password. Change these only if your
// own MySQL setup uses different credentials.
const DB_HOST = '127.0.0.1';
const DB_NAME = 'studenthub_db';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

// Returns the shared PDO connection, creating it once.
// Throws a RuntimeException with a SAFE message on failure
// (details go to the PHP error log, never to the visitor).
function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME
        . ';charset=' . DB_CHARSET;

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Real prepared statements, not emulated ones.
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        error_log('StudentHub DB connection failed: ' . $e->getMessage());
        throw new RuntimeException(
            'Database is unavailable right now. ' .
            'Please check that MySQL is running in XAMPP and try again.'
        );
    }

    return $pdo;
}
