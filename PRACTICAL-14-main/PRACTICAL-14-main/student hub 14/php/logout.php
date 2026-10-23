<?php
/* =====================================================
   STUDENTHUB - PRACTICAL 10
   Logout: clears the remember-me token, wipes session
   data, expires both cookies, destroys the session and
   sends the user back to the login options page.
   Use the site "Logout" links (they point here).
   ===================================================== */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (!defined('STUDENTHUB_P10_TEST')) {
    try {
        $pdo = getDbConnection();
    } catch (RuntimeException $e) {
        $pdo = null;
    }

    logout_user($pdo ?? null);

    header('Location: ../pages/login.html?message=loggedout', true, 303);
    exit;
}
