<?php
/* =====================================================
   STUDENTHUB - PRACTICAL 10
   Shared session / authentication helper.

   Every protected page starts with, for example:
       require_once __DIR__ . '/../php/auth.php';
       require_role('student', 'student-login.html');

   - Secure session cookies (HttpOnly, SameSite=Lax,
     Secure when HTTPS is used).
   - 15-minute inactivity timeout (see session_timeout()).
   - Database-backed remember-me tokens (raw token only
     ever lives in the cookie, hash in the database).
   - Only id, name, email and role stay in $_SESSION.
   ===================================================== */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

// Inactivity limit in seconds (15 minutes). Tests may set
// the STUDENTHUB_TIMEOUT environment variable to a small
// number to verify expiry without waiting.
function session_timeout(): int
{
    $env = getenv('STUDENTHUB_TIMEOUT');
    if ($env !== false && (int) $env > 0) {
        return (int) $env;
    }
    return 900;
}

const REMEMBER_COOKIE = 'studenthub_remember';
const REMEMBER_DAYS = 30;

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0, // session cookie: gone when browser closes
        'path' => '/',
        'httponly' => true, // JavaScript cannot read it (XSS protection)
        'samesite' => 'Lax', // sent on top-level navigation only
        'secure' => $isHttps, // HTTPS-only when available
    ]);

    session_start();
}

function current_user(): ?array
{
    start_secure_session();
    return isset($_SESSION['user']) && is_array($_SESSION['user'])
        ? $_SESSION['user']
        : null;
}

// True when the session went quiet for longer than allowed.
function session_expired(): bool
{
    $user = current_user();
    if ($user === null) {
        return false;
    }
    $last = (int) ($_SESSION['last_activity'] ?? 0);
    return $last > 0 && (time() - $last) > session_timeout();
}

function touch_activity(): void
{
    start_secure_session();
    $_SESSION['last_activity'] = time();
}

// Wipes the remember-me token (database + cookie) and then
// destroys the whole session. Safe to call when logged out.
function logout_user(?PDO $pdo = null): void
{
    start_secure_session();

    $userId = isset($_SESSION['user']['id'])
        ? (int) $_SESSION['user']['id'] : 0;

    if ($pdo !== null && $userId > 0) {
        try {
            $stmt = $pdo->prepare(
                'UPDATE students
                 SET remember_hash = NULL, remember_expiry = NULL
                 WHERE student_id = :id'
            );
            $stmt->execute([':id' => $userId]);
        } catch (Throwable $e) {
            error_log('StudentHub remember-me cleanup failed.');
        }
    }

    if (isset($_COOKIE[REMEMBER_COOKIE])) {
        setcookie(
            REMEMBER_COOKIE, '', time() - 3600, '/',
            '', false, true
        );
        unset($_COOKIE[REMEMBER_COOKIE]);
    }

    $_SESSION = [];

    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 3600,
        $params['path'] ?? '/', '', false,
        $params['httponly'] ?? true
    );

    session_destroy();
}

// Restores a session from a valid remember-me cookie.
// Returns true on success (with the token ROTATED: a fresh
// token replaces the used one, so a stolen cookie quickly
// stops working). Returns false for missing, unknown or
// expired tokens.
function try_remember_login(PDO $pdo): bool
{
    if (current_user() !== null) {
        return true;
    }

    if (empty($_COOKIE[REMEMBER_COOKIE])) {
        return false;
    }

    $token = (string) $_COOKIE[REMEMBER_COOKIE];
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT student_id, full_name, email, role
         FROM students
         WHERE remember_hash = :hash
           AND remember_expiry IS NOT NULL
           AND remember_expiry > NOW()
         LIMIT 1'
    );
    $stmt->execute([':hash' => hash('sha256', $token)]);
    $row = $stmt->fetch();

    if ($row === false) {
        return false;
    }

    // Rotate: issue a brand-new token immediately.
    remember_issue($pdo, (int) $row['student_id']);

    start_secure_session();
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $row['student_id'],
        'name' => (string) $row['full_name'],
        'email' => (string) $row['email'],
        'role' => (string) $row['role'],
    ];
    touch_activity();

    return true;
}

// Creates a fresh remember-me token for a user: random
// bytes from the OS, hash stored in DB, raw token in the
// cookie. Call only right after a verified password login.
function remember_issue(PDO $pdo, int $userId): void
{
    $token = bin2hex(random_bytes(32)); // 64 hex chars
    $expiry = date(
        'Y-m-d H:i:s',
        time() + REMEMBER_DAYS * 86400
    );

    $stmt = $pdo->prepare(
        'UPDATE students
         SET remember_hash = :hash, remember_expiry = :expiry
         WHERE student_id = :id'
    );
    $stmt->execute([
        ':hash' => hash('sha256', $token),
        ':expiry' => $expiry,
        ':id' => $userId,
    ]);

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie(
        REMEMBER_COOKIE, $token, time() + REMEMBER_DAYS * 86400,
        '/', '', $isHttps, true
    );
    $_COOKIE[REMEMBER_COOKIE] = $token;
}

// Guard for protected pages. Redirects (never renders) when:
//   - nobody is logged in (not even via remember-me), or
//   - the session timed out, or
//   - the role does not match (sent back to their own home).
// $loginPage is e.g. 'student-login.html' (page lives in pages/).
// Returns the logged-in user array when access is allowed.
function require_role(string $role, string $loginPage): array
{
    try {
        $pdo = getDbConnection();
    } catch (RuntimeException $e) {
        http_response_code(503);
        echo 'Database is unavailable right now. Please try again later.';
        exit;
    }

    try_remember_login($pdo);

    $user = current_user();

    if ($user === null) {
        header('Location: ../pages/' . $loginPage . '?error=login', true, 303);
        exit;
    }

    if (session_expired()) {
        logout_user($pdo);
        header('Location: ../pages/' . $loginPage . '?error=expired', true, 303);
        exit;
    }

    touch_activity();

    if (($user['role'] ?? '') !== $role) {
        $own = ($user['role'] ?? '') === 'faculty'
            ? 'faculty-dashboard.php'
            : 'student-dashboard.php';
        header('Location: ../pages/' . $own . '?denied=' . $role, true, 303);
        exit;
    }

    return $user;
}

// ---- Practical 13: CSRF helpers (shared by the JSON API) ----

// Returns the per-session CSRF token, creating it once.
function csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// True when the supplied token matches the session token.
function csrf_check(?string $supplied): bool
{
    start_secure_session();
    $expected = $_SESSION['csrf_token'] ?? '';
    return is_string($expected) && $expected !== ''
        && is_string($supplied)
        && hash_equals($expected, $supplied);
}
