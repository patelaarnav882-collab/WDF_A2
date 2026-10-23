<?php
/* =====================================================
   STUDENTHUB - PRACTICAL 9
   Secure registration: POST only, server-side validation,
   duplicate-email check, password_hash() storage, MySQL
   insert with prepared statements.

   The Practical 7 CSV handler (php/register.php) is kept
   untouched for that practical's demonstration; THIS file
   is what the registration form now posts to, because
   only a database insert satisfies Practical 9.

   Run through Apache/XAMPP:
       http://localhost/<project>/pages/register.html
   Never open this file directly with file://.
   ===================================================== */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

// Allowed option values. Must match pages/register.html.
const P9_COURSES = ['btech', 'bca', 'bba', 'bcom', 'bsc', 'other'];
const P9_YEARS = ['1', '2', '3', '4'];
const P9_SEMESTERS = ['1', '2', '3', '4', '5', '6', '7', '8'];
const P9_GENDERS = ['male', 'female', 'other'];

function p9_post(array $post, string $key): string
{
    if (!isset($post[$key]) || is_array($post[$key])) {
        return '';
    }
    return trim((string) $post[$key]);
}

function p9_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Same rules as the frontend + Practical 7 handler, so all
// three layers agree. Empty array = valid.
function p9_validate(array $post): array
{
    $errors = [];

    $name = p9_post($post, 'full_name');
    if ($name === '') {
        $errors['full_name'] = 'Please enter your full name.';
    } elseif (mb_strlen($name) < 3) {
        $errors['full_name'] = 'Name must be at least 3 characters long.';
    } elseif (!preg_match("/^[A-Za-z][A-Za-z\\s.'-]{1,48}[A-Za-z.]$/u", $name)) {
        $errors['full_name'] = 'Name may contain only letters, spaces, dots, hyphens and apostrophes.';
    }

    $email = p9_post($post, 'email');
    if ($email === '') {
        $errors['email'] = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address (example: name@college.edu).';
    }

    $phone = str_replace([' ', '-'], '', p9_post($post, 'phone'));
    if ($phone === '' && p9_post($post, 'phone') === '') {
        $errors['phone'] = 'Please enter your mobile number.';
    } elseif (!preg_match('/^(\+91|91)?[6-9]\d{9}$/', $phone)) {
        $errors['phone'] = 'Please enter a valid 10-digit mobile number starting with 6-9.';
    }

    if (!in_array(p9_post($post, 'course'), P9_COURSES, true)) {
        $errors['course'] = 'Please select your course / program.';
    }
    if (!in_array(p9_post($post, 'year'), P9_YEARS, true)) {
        $errors['year'] = 'Please select your year of study.';
    }
    if (!in_array(p9_post($post, 'semester'), P9_SEMESTERS, true)) {
        $errors['semester'] = 'Please select your semester.';
    }
    if (!in_array(p9_post($post, 'gender'), P9_GENDERS, true)) {
        $errors['gender'] = 'Please select your gender.';
    }
    if (!isset($post['terms'])) {
        $errors['terms'] = 'Please accept the terms and conditions to continue.';
    }

    // NOTE: student_id from the form is the college ID card
    // number (text like "22ITU001"), NOT the database key.
    // The database generates its own numeric primary key.

    $password = isset($post['password']) && !is_array($post['password'])
        ? (string) $post['password'] : '';
    $confirm = isset($post['confirm_password']) && !is_array($post['confirm_password'])
        ? (string) $post['confirm_password'] : '';
    if ($password === '') {
        $errors['password'] = 'Please create a password.';
    } elseif (mb_strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must contain both letters and numbers.';
    } elseif ($confirm === '') {
        $errors['confirm_password'] = 'Please confirm your password.';
    } elseif (!hash_equals($password, $confirm)) {
        $errors['confirm_password'] = 'Passwords do not match. Please try again.';
    }

    return $errors;
}

// True when this email is already registered (prepared
// statement -- the value is bound, never concatenated).
function p9_email_exists(PDO $pdo, string $email): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM students WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    return $stmt->fetch() !== false;
}

// Inserts the student. Stores ONLY the password hash --
// never the password or its confirmation. Returns the new
// numeric student_id on success, 0 on duplicate-email race.
function p9_insert(PDO $pdo, array $post): int
{
    $hash = password_hash(
        (string) $post['password'],
        PASSWORD_DEFAULT
    );

    $stmt = $pdo->prepare(
        'INSERT INTO students
            (full_name, email, mobile, course, year, semester,
             gender, password_hash)
         VALUES
            (:full_name, :email, :mobile, :course, :year,
             :semester, :gender, :password_hash)'
    );

    try {
        $stmt->execute([
            ':full_name' => p9_post($post, 'full_name'),
            ':email' => p9_post($post, 'email'),
            ':mobile' => str_replace(
                [' ', '-'], '', p9_post($post, 'phone')
            ),
            ':course' => p9_post($post, 'course'),
            ':year' => (int) p9_post($post, 'year'),
            ':semester' => (int) p9_post($post, 'semester'),
            ':gender' => p9_post($post, 'gender'),
            ':password_hash' => $hash,
        ]);
    } catch (PDOException $e) {
        // 23000 = constraint violation: almost always the
        // UNIQUE email rule firing on a simultaneous request.
        if ($e->getCode() === '23000') {
            return 0;
        }
        throw $e;
    }

    return (int) $pdo->lastInsertId();
}

/* ---------- result page (reuses Practical 7 styling) ---------- */

function p9_page(string $title, string $bodyHtml): void
{
    echo '<!DOCTYPE html>' . "\n"
        . '<html lang="en"><head>' . "\n"
        . '    <meta charset="UTF-8">' . "\n"
        . '    <meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n"
        . '    <title>' . p9_e($title) . ' | StudentHub</title>' . "\n"
        . '    <link rel="stylesheet" href="../css/style.css">' . "\n"
        . '</head><body>' . "\n"
        . '    <script>try{var s=localStorage.getItem("studentHubTheme");'
        . 'if(s==="dark"){document.body.classList.add("dark-mode");}}catch(e){}</script>' . "\n"
        . '    <a href="#main" class="skip-link">Skip to main content</a>' . "\n"
        . '    <header class="site-header"><div class="container">' . "\n"
        . '        <div class="brand"><img src="../images/logo.png" alt="StudentHub logo">' . "\n"
        . '        <h1>StudentHub</h1></div>' . "\n"
        . '        <nav id="mainNav" aria-label="Main navigation"><ul class="nav-list">' . "\n"
        . '            <li><a href="../pages/index.html">Home</a></li>' . "\n"
        . '            <li><a href="../pages/register.html" aria-current="page">Register</a></li>' . "\n"
        . '            <li><a href="../pages/login.html">Login</a></li>' . "\n"
        . '        </ul></nav>' . "\n"
        . '    </div></header>' . "\n"
        . '    <main id="main"><section class="section"><div class="container">' . "\n"
        . $bodyHtml . "\n"
        . '    </div></section></main>' . "\n"
        . '    <footer class="site-footer"><div class="footer-bottom">'
        . '<p>&copy; 2026 StudentHub</p></div></footer>' . "\n"
        . '    <script src="../js/script.js"></script>' . "\n"
        . '</body></html>' . "\n";
}

function p9_show_errors(array $errors): void
{
    $labels = [
        'full_name' => 'Full Name', 'email' => 'Email Address',
        'phone' => 'Mobile Number', 'course' => 'Course / Program',
        'year' => 'Year of Study', 'semester' => 'Semester',
        'gender' => 'Gender', 'terms' => 'Terms and Conditions',
        'password' => 'Password', 'confirm_password' => 'Confirm Password',
    ];
    $items = '';
    foreach ($errors as $field => $message) {
        $items .= '                <li><strong>' . p9_e($labels[$field] ?? $field)
            . ':</strong> ' . p9_e($message) . "</li>\n";
    }
    p9_page(
        'Registration Errors',
        '        <div class="register-form-card" role="alert">' . "\n"
        . '            <div class="register-form-heading">' . "\n"
        . '                <h2>Please fix ' . count($errors) . ' field(s)</h2>' . "\n"
        . '                <p>Your registration was <strong>not</strong> saved.'
        . ' Correct the fields and submit again.</p>' . "\n"
        . '            </div>' . "\n"
        . '            <ul>' . "\n" . $items . '            </ul>' . "\n"
        . '            <button type="button" class="btn" onclick="history.back()">'
        . 'Go Back and Correct</button>' . "\n"
        . '        </div>'
    );
}

/* ---------- request handling (web flow only) ---------- */

if (!defined('STUDENTHUB_P9_TEST')) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        p9_page(
            'Method Not Allowed',
            '        <div class="register-form-card" role="alert">' . "\n"
            . '            <h2>Method Not Allowed</h2>' . "\n"
            . '            <p>Please <a href="../pages/register.html">register here</a>.</p>' . "\n"
            . '        </div>'
        );
        exit;
    }

    $errors = p9_validate($_POST);
    if (!empty($errors)) {
        p9_show_errors($errors);
        exit;
    }

    try {
        $pdo = getDbConnection();
    } catch (RuntimeException $e) {
        p9_show_errors(['storage' => $e->getMessage()]);
        exit;
    }

    // Friendly duplicate check first...
    if (p9_email_exists($pdo, p9_post($_POST, 'email'))) {
        p9_show_errors(['email' => 'This email address is already registered. Please log in instead.']);
        exit;
    }

    try {
        $newId = p9_insert($pdo, $_POST);
    } catch (Throwable $e) {
        error_log('StudentHub registration insert failed: ' . $e->getMessage());
        p9_show_errors(['storage' => 'Your registration could not be saved. Please try again.']);
        exit;
    }

    // ...with the UNIQUE constraint as the backstop for races.
    if ($newId === 0) {
        p9_show_errors(['email' => 'This email address is already registered. Please log in instead.']);
        exit;
    }

    // No auto-login (Practical 10). PRG stops refresh duplicates.
    header('Location: ../pages/register.html?registered=1', true, 303);
    exit;
}
