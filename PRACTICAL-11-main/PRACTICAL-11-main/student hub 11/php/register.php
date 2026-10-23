<?php
/* =====================================================
   STUDENTHUB - PRACTICAL 7
   Registration form processing: POST only, server-side
   validation + sanitization, CSV file storage.

   Run through Apache/XAMPP:
       http://localhost/<project>/pages/register.html
   Never open this file directly with file://.
   ===================================================== */

declare(strict_types=1);

// Storage location. Tests may pre-define STUDENTHUB_CSV
// to point at a temporary file before including this file.
defined('STUDENTHUB_CSV') or define(
    'STUDENTHUB_CSV',
    __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR .
    'data' . DIRECTORY_SEPARATOR . 'registrations.csv'
);

// Allowed option values. These must match the <select> and
// radio values used in pages/register.html exactly.
const ALLOWED_COURSES = ['btech', 'bca', 'bba', 'bcom', 'bsc', 'other'];
const ALLOWED_YEARS = ['1', '2', '3', '4'];
const ALLOWED_SEMESTERS = ['1', '2', '3', '4', '5', '6', '7', '8'];
const ALLOWED_GENDERS = ['male', 'female', 'other'];

// Column order for the CSV file (passwords are NEVER stored).
const CSV_HEADER = [
    'submitted_at', 'full_name', 'student_id', 'email',
    'phone', 'course', 'year', 'semester', 'gender',
];

/* ---------- small helpers ---------- */

// Read one POST value safely and trim surrounding spaces.
function post_value(array $post, string $key): string
{
    if (!isset($post[$key]) || is_array($post[$key])) {
        return '';
    }
    return trim((string) $post[$key]);
}

// Escape text for display inside HTML (output escaping).
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/* ---------- server-side validation ---------- */

// Returns [field_name => error_message]. Empty array = valid.
// Works on any input array, so it cannot be bypassed by
// disabling JavaScript in the browser.
function validate_registration(array $post): array
{
    $errors = [];

    // Full name: letters, spaces, dots, hyphens, apostrophes.
    $name = post_value($post, 'full_name');
    if ($name === '') {
        $errors['full_name'] = 'Please enter your full name.';
    } elseif (mb_strlen($name) < 3) {
        $errors['full_name'] = 'Name must be at least 3 characters long.';
    } elseif (!preg_match("/^[A-Za-z][A-Za-z\\s.'-]{1,48}[A-Za-z.]$/u", $name)) {
        $errors['full_name'] = 'Name may contain only letters, spaces, dots, hyphens and apostrophes.';
    }

    // Student ID: required, kept short and printable.
    $studentId = post_value($post, 'student_id');
    if ($studentId === '') {
        $errors['student_id'] = 'Please enter your student ID.';
    } elseif (mb_strlen($studentId) > 20) {
        $errors['student_id'] = 'Student ID must be 20 characters or fewer.';
    }

    // Email: required + PHP email format check.
    $email = post_value($post, 'email');
    if ($email === '') {
        $errors['email'] = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address (example: name@college.edu).';
    }

    // Mobile: allow spaces/dashes while typing, then require
    // an Indian 10-digit number starting with 6-9 (+91 optional).
    $phone = post_value($post, 'phone');
    $phoneDigits = str_replace([' ', '-'], '', $phone);
    if ($phone === '') {
        $errors['phone'] = 'Please enter your mobile number.';
    } elseif (!preg_match('/^(\+91|91)?[6-9]\d{9}$/', $phoneDigits)) {
        $errors['phone'] = 'Please enter a valid 10-digit mobile number starting with 6-9.';
    }

    // Selects and gender: value must be one of the real options,
    // so forged values such as course=admin are rejected.
    if (!in_array(post_value($post, 'course'), ALLOWED_COURSES, true)) {
        $errors['course'] = 'Please select your course / program.';
    }
    if (!in_array(post_value($post, 'year'), ALLOWED_YEARS, true)) {
        $errors['year'] = 'Please select your year of study.';
    }
    if (!in_array(post_value($post, 'semester'), ALLOWED_SEMESTERS, true)) {
        $errors['semester'] = 'Please select your semester.';
    }
    if (!in_array(post_value($post, 'gender'), ALLOWED_GENDERS, true)) {
        $errors['gender'] = 'Please select your gender.';
    }

    // Terms checkbox: browsers only send it when it is checked.
    if (!isset($post['terms'])) {
        $errors['terms'] = 'Please accept the terms and conditions to continue.';
    }

    // Passwords are checked for equality/strength but NEVER saved.
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

/* ---------- CSV storage ---------- */

// Appends one record with an exclusive lock. Adds the header
// row when the file is new or empty. Returns [ok, message].
function save_registration(array $post): array
{
    $file = STUDENTHUB_CSV;
    $dir = dirname($file);

    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            return [false, 'Storage directory could not be created.'];
        }
    }

    // Build the row WITHOUT passwords (privacy requirement).
    $row = [
        date('Y-m-d H:i:s'),
        post_value($post, 'full_name'),
        post_value($post, 'student_id'),
        post_value($post, 'email'),
        str_replace([' ', '-'], '', post_value($post, 'phone')),
        post_value($post, 'course'),
        post_value($post, 'year'),
        post_value($post, 'semester'),
        post_value($post, 'gender'),
    ];

    $handle = @fopen($file, 'ab');
    if ($handle === false) {
        return [false, 'Registration file could not be opened for writing.'];
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        return [false, 'Registration file is busy. Please try again.'];
    }

    // Header row only for a new or empty file. (Checked with
    // filesize() because ftell() after an append-mode open is
    // unreliable on Windows.)
    clearstatcache(true, $file);
    if (!file_exists($file) || filesize($file) === 0) {
        fputcsv($handle, CSV_HEADER);
    }

    $written = fputcsv($handle, $row);
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    if ($written === false) {
        return [false, 'Your registration could not be saved. Please try again.'];
    }

    return [true, ''];
}

/* ---------- result pages (StudentHub style) ---------- */

function page_head(string $title): void
{
    echo '<!DOCTYPE html>' . "\n"
        . '<html lang="en">' . "\n"
        . '<head>' . "\n"
        . '    <meta charset="UTF-8">' . "\n"
        . '    <meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n"
        . '    <title>' . e($title) . ' | StudentHub</title>' . "\n"
        . '    <link rel="stylesheet" href="../css/style.css">' . "\n"
        . '</head>' . "\n"
        . '<body>' . "\n"
        . '    <script>try{var s=localStorage.getItem("studentHubTheme");'
        . 'if(s==="dark"){document.body.classList.add("dark-mode");}}catch(e){}</script>' . "\n"
        . '    <a href="#main" class="skip-link">Skip to main content</a>' . "\n"
        . '    <header class="site-header"><div class="container">' . "\n"
        . '        <div class="brand"><img src="../images/logo.png" alt="StudentHub logo">' . "\n"
        . '        <h1>StudentHub</h1></div>' . "\n"
        . '        <button type="button" class="menu-toggle" id="menuToggle"'
        . ' aria-label="Open navigation menu" aria-expanded="false"'
        . ' aria-controls="mainNav">☰</button>' . "\n"
        . '        <button type="button" class="theme-toggle" id="themeToggle"'
        . ' aria-label="Switch to dark mode" title="Switch to dark mode">'
        . '<span class="theme-icon" aria-hidden="true">🌙</span>'
        . '<span class="theme-label visually-hidden">Dark mode</span></button>' . "\n"
        . '        <nav id="mainNav" aria-label="Main navigation"><ul class="nav-list">' . "\n"
        . '            <li><a href="../pages/index.html">Home</a></li>' . "\n"
        . '            <li><a href="../pages/about.html">About</a></li>' . "\n"
        . '            <li><a href="../pages/events.html">Events</a></li>' . "\n"
        . '            <li><a href="../pages/faq.html">FAQ</a></li>' . "\n"
        . '            <li><a href="../pages/contact.html">Contact</a></li>' . "\n"
        . '            <li><a href="../pages/login.html">Login</a></li>' . "\n"
        . '            <li><a href="../pages/register.html" aria-current="page">Register</a></li>' . "\n"
        . '        </ul></nav>' . "\n"
        . '    </div></header>' . "\n"
        . '    <main id="main"><section class="section"><div class="container">' . "\n";
}

function page_foot(): void
{
    echo '    </div></section></main>' . "\n"
        . '    <footer class="site-footer"><div class="container footer-content">' . "\n"
        . '        <div class="footer-column"><h3>StudentHub</h3>'
        . '<p>Your campus. Your community. Your hub.</p></div>' . "\n"
        . '        <div class="footer-column"><h3>Quick Links</h3>'
        . '<a href="../pages/about.html">About</a>'
        . '<a href="../pages/events.html">Events</a>'
        . '<a href="../pages/faq.html">FAQ</a></div>' . "\n"
        . '        <div class="footer-column"><h3>Student Support</h3>'
        . '<a href="../pages/contact.html">Contact</a>'
        . '<a href="../pages/feedback.html">Feedback</a></div>' . "\n"
        . '    </div><div class="footer-bottom"><p>&copy; 2026 StudentHub</p></div></footer>' . "\n"
        . '    <script src="../js/script.js"></script>' . "\n"
        . '</body></html>' . "\n";
}

function show_errors(array $errors): void
{
    $labels = [
        'full_name' => 'Full Name', 'student_id' => 'Student ID',
        'email' => 'Email Address', 'phone' => 'Mobile Number',
        'course' => 'Course / Program', 'year' => 'Year of Study',
        'semester' => 'Semester', 'gender' => 'Gender',
        'terms' => 'Terms and Conditions', 'password' => 'Password',
        'confirm_password' => 'Confirm Password',
    ];
    page_head('Registration Errors');
    echo '        <div class="register-form-card" role="alert">' . "\n"
        . '            <div class="register-form-heading">' . "\n"
        . '                <h2>Please fix ' . count($errors) . ' field(s)</h2>' . "\n"
        . '                <p>Your registration was <strong>not</strong> saved.'
        . ' Correct the fields below and submit again.</p>' . "\n"
        . '            </div>' . "\n"
        . '            <ul>' . "\n";
    foreach ($errors as $field => $message) {
        $label = $labels[$field] ?? $field;
        echo '                <li><strong>' . e($label) . ':</strong> '
            . e($message) . "</li>\n";
    }
    echo '            </ul>' . "\n"
        . '            <button type="button" class="btn" onclick="history.back()">'
        . 'Go Back and Correct</button>' . "\n"
        . '        </div>' . "\n";
    page_foot();
}

/* ---------- request handling ---------- */

// Test harnesses define STUDENTHUB_UNDER_TEST and call the
// functions above directly; the web flow runs only for real
// HTTP requests.
if (!defined('STUDENTHUB_UNDER_TEST')) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        page_head('Method Not Allowed');
        echo '        <div class="register-form-card" role="alert">' . "\n"
            . '            <h2>Method Not Allowed</h2>' . "\n"
            . '            <p>This page accepts registration data only through'
            . ' the registration form (HTTP POST). Please'
            . ' <a href="../pages/register.html">register here</a>.</p>' . "\n"
            . '        </div>' . "\n";
        page_foot();
        exit;
    }

    $errors = validate_registration($_POST);

    if (!empty($errors)) {
        show_errors($errors);
        exit;
    }

    [$ok, $message] = save_registration($_POST);

    if (!$ok) {
        show_errors(['storage' => $message]);
        exit;
    }

    // POST/Redirect/GET: a refresh now reloads a plain page,
    // it can no longer submit the form a second time.
    header('Location: ../pages/register.html?registered=1', true, 303);
    exit;
}
