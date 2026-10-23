<?php
/* =====================================================
   STUDENTHUB - PRACTICAL 12
   MySQLi database connection for Event Management module.
   Uses MySQLi prepared statements as required by Practical 12.
   ===================================================== */

declare(strict_types=1);

// Local XAMPP defaults. Standard installs use user "root"
// with an EMPTY password. Change these only if your
// own MySQL setup uses different credentials.
const CRUD_DB_HOST = '127.0.0.1';
const CRUD_DB_NAME = 'studenthub_db';
const CRUD_DB_USER = 'root';
const CRUD_DB_PASS = '';
const CRUD_DB_CHARSET = 'utf8mb4';

// Returns the shared MySQLi connection with exceptions enabled
// (failures throw instead of failing silently).
function crud_db(): mysqli
{
    static $db = null;

    if ($db instanceof mysqli) {
        return $db;
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $db = new mysqli(CRUD_DB_HOST, CRUD_DB_USER, CRUD_DB_PASS, CRUD_DB_NAME);
    } catch (mysqli_sql_exception $e) {
        error_log('StudentHub CRUD DB connection failed: ' . $e->getMessage());
        throw new RuntimeException(
            'Database is unavailable right now. ' .
            'Please check that MySQL is running in XAMPP and try again.'
        );
    }

    $db->set_charset(CRUD_DB_CHARSET);

    return $db;
}