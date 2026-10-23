<?php
/* =====================================================
   STUDENTHUB - PRACTICAL 11
   MySQLi database connection for the Student Management
   module (Practical 11 uses MySQLi prepared statements).

   Same local XAMPP settings as php/db.php (PDO), which
   is still used by the login/session code. Only the
   student-management queries use this connection.
   ===================================================== */

declare(strict_types=1);

// Local XAMPP defaults: user "root" with an EMPTY password.
const CRUD_DB_HOST = '127.0.0.1';
const CRUD_DB_NAME = 'studenthub_db';
const CRUD_DB_USER = 'root';
const CRUD_DB_PASS = '';

// Returns a shared MySQLi connection with exceptions enabled
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

    $db->set_charset('utf8mb4');

    return $db;
}
