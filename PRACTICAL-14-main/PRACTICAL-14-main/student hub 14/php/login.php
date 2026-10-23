<?php
/* =====================================================
   STUDENTHUB - PRACTICAL 10
   Login handler: POST only -> validate -> prepared
   SELECT -> password_verify() -> new session ID ->
   role-based redirect. Optional remember-me token.

   Both login pages post here with a hidden "login_page"
   field so errors return to the page they came from.
   ===================================================== */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function login_e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

if (!defined('STUDENTHUB_P10_TEST')) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo 'Method Not Allowed. Please <a href="../pages/login.html">log in here</a>.';
        exit;
    }

    $loginPage = ($_POST['login_page'] ?? '') === 'faculty'
        ? 'faculty-login.html'
        : 'student-login.html';

    $fail = function (string $code) use ($loginPage): void {
        header('Location: ../pages/' . $loginPage . '?error=' . $code, true, 303);
        exit;
    };

    $email = isset($_POST['email']) && !is_array($_POST['email'])
        ? trim((string) $_POST['email']) : '';
    $password = isset($_POST['password']) && !is_array($_POST['password'])
        ? (string) $_POST['password'] : '';

    // Same generic message for every failure: never reveal
    // whether the email or the password was the problem.
    if ($email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fail('invalid');
    }

    try {
        $pdo = getDbConnection();
    } catch (RuntimeException $e) {
        $fail('unavailable');
    }

    $stmt = $pdo->prepare(
        'SELECT student_id, full_name, email, password_hash, role
         FROM students
         WHERE email = :email
         LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch();

    $hash = is_array($row) ? (string) ($row['password_hash'] ?? '') : '';
    if (!is_array($row) || $hash === '' || !password_verify($password, $hash)) {
        $fail('invalid');
    }

    // Each login page only admits its own role: a student using
    // the faculty form (or vice versa) is sent back with a clear
    // message instead of being waved through to any dashboard.
    // Practical 14: admins sign in through the existing faculty
    // form (no duplicate login page), so the faculty form admits
    // 'faculty' and 'admin'.
    $role = (string) $row['role'];
    if (($_POST['login_page'] ?? '') === 'faculty') {
        if ($role !== 'faculty' && $role !== 'admin') {
            $fail('role');
        }
    } elseif ($role !== 'student') {
        $fail('role');
    }

    // Success: fresh session ID first (stops fixation attacks).
    start_secure_session();
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $row['student_id'],
        'name' => (string) $row['full_name'],
        'email' => (string) $row['email'],
        'role' => (string) $row['role'],
    ];
    touch_activity();

    // Intermediate extension: last-login timestamp.
    try {
        $pdo->prepare(
            'UPDATE students SET last_login = NOW() WHERE student_id = :id'
        )->execute([':id' => (int) $row['student_id']]);
    } catch (Throwable $e) {
        error_log('StudentHub last_login update failed.');
    }

    // Optional remember-me (checkbox ticked only).
    if (isset($_POST['remember'])) {
        remember_issue($pdo, (int) $row['student_id']);
    }

    // Practical 14: record successful admin logins. Runs after
    // the session is set (so the logger sees the real user)
    // and never blocks login if logging itself fails.
    if ($role === 'admin') {
        require_once __DIR__ . '/audit_logger.php';
        audit_log('admin_login', 'user', (int) $row['student_id'], 'Admin signed in.');
    }

    $home = user_home($role);
    header('Location: ../pages/' . $home, true, 303);
    exit;
}
