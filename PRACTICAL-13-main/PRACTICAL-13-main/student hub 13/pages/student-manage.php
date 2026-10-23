<?php
/* =====================================================
   STUDENTHUB - PRACTICAL 11
   Student Management: faculty-only CRUD with search,
   course/year filters and pagination (MySQLi prepared
   statements). Add/update/delete use POST; search and
   filters use GET so list pages stay shareable.
   ===================================================== */
declare(strict_types=1);

require_once __DIR__ . '/../php/auth.php';
require_once __DIR__ . '/../php/db_mysqli.php';

$user = require_role('faculty', 'faculty-login.html');

const MANAGE_COURSES = [
    'btech' => 'B.Tech', 'bca' => 'BCA', 'bba' => 'BBA',
    'bcom' => 'B.Com', 'bsc' => 'B.Sc', 'other' => 'Other',
];
const MANAGE_YEARS = ['1', '2', '3', '4'];
const MANAGE_GENDERS = ['male', 'female', 'other'];
const MANAGE_PER_PAGE = 8;

// Accepts NULL: enrollment_id and year are nullable columns.
function m_e(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function m_post(array $post, string $key): string
{
    if (!isset($post[$key]) || is_array($post[$key])) {
        return '';
    }
    return trim((string) $post[$key]);
}

function m_get(string $key): string
{
    if (!isset($_GET[$key]) || is_array($_GET[$key])) {
        return '';
    }
    return trim((string) $_GET[$key]);
}

// Duplicate checks (prepared; $ignoreId skips the row being edited).
function m_value_taken(string $column, string $value, ?int $ignoreId): bool
{
    $allowed = ['enrollment_id', 'email'];
    if (!in_array($column, $allowed, true) || $value === '') {
        return false;
    }
    $db = crud_db();
    if ($ignoreId !== null) {
        $stmt = $db->prepare(
            "SELECT 1 FROM students WHERE $column = ? AND student_id <> ? LIMIT 1"
        );
        $stmt->bind_param('si', $value, $ignoreId);
    } else {
        $stmt = $db->prepare(
            "SELECT 1 FROM students WHERE $column = ? LIMIT 1"
        );
        $stmt->bind_param('s', $value);
    }
    $stmt->execute();
    $found = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $found;
}

// Server-side validation. Returns [field => message].
function m_validate(array $post, ?int $ignoreId = null): array
{
    $errors = [];

    $enroll = m_post($post, 'enrollment_id');
    if ($enroll === '') {
        $errors['enrollment_id'] = 'Please enter the enrollment ID.';
    } elseif (mb_strlen($enroll) > 20) {
        $errors['enrollment_id'] = 'Enrollment ID must be 20 characters or fewer.';
    } elseif (m_value_taken('enrollment_id', $enroll, $ignoreId)) {
        $errors['enrollment_id'] = 'This enrollment ID is already registered.';
    }

    $name = m_post($post, 'full_name');
    if ($name === '') {
        $errors['full_name'] = 'Please enter the full name.';
    } elseif (mb_strlen($name) < 3) {
        $errors['full_name'] = 'Name must be at least 3 characters long.';
    } elseif (!preg_match("/^[A-Za-z][A-Za-z\\s.'-]{1,48}[A-Za-z.]$/u", $name)) {
        $errors['full_name'] = 'Name may contain only letters, spaces, dots, hyphens and apostrophes.';
    }

    $email = m_post($post, 'email');
    if ($email === '') {
        $errors['email'] = 'Please enter the email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } elseif (m_value_taken('email', $email, $ignoreId)) {
        $errors['email'] = 'This email address is already registered.';
    }

    $phoneRaw = m_post($post, 'phone');
    $phone = str_replace([' ', '-'], '', $phoneRaw);
    if ($phoneRaw === '') {
        $errors['phone'] = 'Please enter the mobile number.';
    } elseif (!preg_match('/^(\+91|91)?[6-9]\d{9}$/', $phone)) {
        $errors['phone'] = 'Please enter a valid 10-digit mobile number starting with 6-9.';
    }

    if (!array_key_exists(m_post($post, 'course'), MANAGE_COURSES)) {
        $errors['course'] = 'Please select a course.';
    }
    if (!in_array(m_post($post, 'year'), MANAGE_YEARS, true)) {
        $errors['year'] = 'Please select an academic year.';
    }
    if (!in_array(m_post($post, 'gender'), MANAGE_GENDERS, true)) {
        $errors['gender'] = 'Please select a gender.';
    }

    return $errors;
}

function m_redirect(string $query): void
{
    header('Location: student-manage.php' . $query, true, 303);
    exit;
}

/* ---------- POST actions (add / update / delete) ---------- */

$formErrors = [];
$formValues = [
    'enrollment_id' => '', 'full_name' => '', 'email' => '',
    'phone' => '', 'course' => '', 'year' => '', 'gender' => '',
];
$editId = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = m_post($_POST, 'action');

    if ($action === 'delete') {
        $id = (int) m_post($_POST, 'id');
        if ($id > 0) {
            try {
                $stmt = crud_db()->prepare('DELETE FROM students WHERE student_id = ?');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $deleted = $stmt->affected_rows > 0;
                $stmt->close();
                m_redirect($deleted ? '?msg=deleted' : '?msg=notfound');
            } catch (mysqli_sql_exception $e) {
                error_log('StudentHub student delete failed.');
                m_redirect('?msg=dberror');
            }
        }
        m_redirect('?msg=notfound');
    }

    if ($action === 'add' || $action === 'update') {
        $id = $action === 'update' ? (int) m_post($_POST, 'id') : null;
        if ($action === 'update' && ($id === null || $id <= 0)) {
            m_redirect('?msg=notfound');
        }
        foreach (array_keys($formValues) as $key) {
            $formValues[$key] = m_post($_POST, $key);
        }
        $formErrors = m_validate($_POST, $id);

        if (empty($formErrors)) {
            try {
                $db = crud_db();
                $yearInt = (int) $formValues['year'];
                if ($action === 'add') {
                    $roleStudent = 'student';
                    $stmt = $db->prepare(
                        'INSERT INTO students
                            (enrollment_id, full_name, email, mobile,
                             course, year, gender, role)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->bind_param(
                        'sssssiss',
                        $formValues['enrollment_id'],
                        $formValues['full_name'],
                        $formValues['email'],
                        $formValues['phone'],
                        $formValues['course'],
                        $yearInt,
                        $formValues['gender'],
                        $roleStudent
                    );
                    $stmt->execute();
                    $stmt->close();
                    m_redirect('?msg=added');
                } else {
                    $stmt = $db->prepare(
                        'UPDATE students SET
                            enrollment_id = ?, full_name = ?, email = ?,
                            mobile = ?, course = ?, year = ?, gender = ?
                         WHERE student_id = ?'
                    );
                    // Types: 5 strings (enrollment, name, email,
                    // phone, course), year = int, gender = string,
                    // student_id = int.
                    $stmt->bind_param(
                        'sssssisi',
                        $formValues['enrollment_id'],
                        $formValues['full_name'],
                        $formValues['email'],
                        $formValues['phone'],
                        $formValues['course'],
                        $yearInt,
                        $formValues['gender'],
                        $id
                    );
                    $stmt->execute();
                    $stmt->close();
                    m_redirect('?msg=updated');
                }
            } catch (mysqli_sql_exception $e) {
                // 1062 = duplicate key: the backstop behind our
                // friendly pre-checks (e.g. simultaneous requests).
                if ((int) $e->getCode() === 1062) {
                    $formErrors['email'] = 'This email or enrollment ID was just registered by someone else.';
                } else {
                    error_log('StudentHub student save failed.');
                    m_redirect('?msg=dberror');
                }
                if ($action === 'update') {
                    $editId = $id;
                }
            }
        } elseif ($action === 'update') {
            $editId = $id;
        }
    }
}

/* ---------- edit mode: load the row into the form ---------- */

if ($editId === null && m_get('edit') !== '') {
    $editId = (int) m_get('edit');
    if ($editId > 0) {
        $stmt = crud_db()->prepare(
            'SELECT enrollment_id, full_name, email, mobile,
                    course, year, gender
             FROM students WHERE student_id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (is_array($row)) {
            foreach (array_keys($formValues) as $key) {
                $formValues[$key] = (string) ($row[$key] ?? '');
            }
        } else {
            $editId = null;
        }
    } else {
        $editId = null;
    }
}

/* ---------- list: search + filters + pagination (GET) ---------- */

$search = m_get('q');
$filterCourse = m_get('course');
$filterYear = m_get('year');
$page = max(1, (int) m_get('page'));

$where = [];
$types = '';
$params = [];

if ($search !== '') {
    $where[] = '(full_name LIKE ? OR email LIKE ? OR course LIKE ? OR enrollment_id LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}
if ($filterCourse !== '' && array_key_exists($filterCourse, MANAGE_COURSES)) {
    $where[] = 'course = ?';
    $types .= 's';
    $params[] = $filterCourse;
} else {
    $filterCourse = '';
}
if ($filterYear !== '' && in_array($filterYear, MANAGE_YEARS, true)) {
    $where[] = 'year = ?';
    $types .= 'i';
    $params[] = (int) $filterYear;
} else {
    $filterYear = '';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

function m_bind_all(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '') {
        return;
    }
    // bind_param needs references; build them dynamically.
    $refs = [];
    foreach ($params as $i => $value) {
        $refs[$i] = &$params[$i];
    }
    $stmt->bind_param($types, ...$refs);
}

try {
    $db = crud_db();

    $countStmt = $db->prepare("SELECT COUNT(*) AS c FROM students $whereSql");
    m_bind_all($countStmt, $types, $params);
    $countStmt->execute();
    $total = (int) $countStmt->get_result()->fetch_assoc()['c'];
    $countStmt->close();

    $totalPages = max(1, (int) ceil($total / MANAGE_PER_PAGE));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * MANAGE_PER_PAGE;

    $listTypes = $types . 'ii';
    $listParams = array_merge($params, [$offset, MANAGE_PER_PAGE]);
    $listStmt = $db->prepare(
        "SELECT student_id, enrollment_id, full_name, email,
                mobile, course, year, gender
         FROM students $whereSql
         ORDER BY full_name ASC
         LIMIT ?, ?"
    );
    m_bind_all($listStmt, $listTypes, $listParams);
    $listStmt->execute();
    $students = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $listStmt->close();
    $listError = false;
} catch (mysqli_sql_exception $e) {
    error_log('StudentHub student list failed.');
    $students = [];
    $total = 0;
    $totalPages = 1;
    $listError = true;
}

// Keep filters while paging.
function m_page_url(int $page, string $search, string $course, string $year): string
{
    $q = ['page' => $page];
    if ($search !== '') {
        $q['q'] = $search;
    }
    if ($course !== '') {
        $q['course'] = $course;
    }
    if ($year !== '') {
        $q['year'] = $year;
    }
    return 'student-manage.php?' . http_build_query($q);
}

$messages = [
    'added' => 'Student added successfully.',
    'updated' => 'Student details updated successfully.',
    'deleted' => 'Student record deleted successfully.',
    'notfound' => 'Student record not found. It may already have been removed.',
    'dberror' => 'Something went wrong while saving. Please try again.',
];
$flash = m_get('msg');
$flashText = $messages[$flash] ?? '';
$flashOk = in_array($flash, ['added', 'updated', 'deleted'], true);

$from = $total === 0 ? 0 : $offset + 1;
$to = min($offset + MANAGE_PER_PAGE, $total);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="StudentHub student management">
    <title>Student Management | StudentHub</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <script>try{var s=localStorage.getItem("studentHubTheme");if(s==="dark"){document.body.classList.add("dark-mode");}}catch(e){}</script>
    <a href="#main" class="skip-link">Skip to main content</a>
    <header class="site-header">
        <div class="container">
            <div class="brand">
                <img src="../images/logo.png" alt="StudentHub logo">
                <h1>StudentHub</h1>
            </div>
            <button type="button" class="menu-toggle" id="menuToggle" aria-label="Open navigation menu" aria-expanded="false" aria-controls="mainNav">☰</button>
            <button type="button" class="theme-toggle" id="themeToggle" aria-label="Switch to dark mode" title="Switch to dark mode"><span class="theme-icon" aria-hidden="true">🌙</span><span class="theme-label visually-hidden">Dark mode</span></button>
            <nav id="mainNav" aria-label="Faculty navigation">
                <ul class="nav-list">
                    <li><a href="faculty-dashboard.php">Dashboard</a></li>
                    <li><a href="faculty-profile.html">Profile</a></li>
                    <li class="nav-item-drop has-active-child">
                        <a href="students.html" class="nav-drop-link">
                            Students
                            <span class="nav-drop-caret" aria-hidden="true">&#9662;</span>
                        </a>
                        <ul class="nav-submenu">
                            <li><a href="student-manage.php" aria-current="page">Student Manage (P11)</a></li>
                            <li><a href="students-ajax.php">Live List (P13)</a></li>
                            <li><a href="students.html">Public List (P6)</a></li>
                            <li><span class="submenu-note">Practical 13 = no page reload</span></li>
                        </ul>
                    </li>
                    <li><a href="event-manage.php">Events</a></li>
                    <li><a href="../php/logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>
    <main id="main">
        <section class="students-hero">
            <div class="container">
                <p class="eyebrow">FACULTY PORTAL</p>
                <h2>Student Management</h2>
                <p>
                    Add, view, edit, delete and search student records.
                    Signed in as <?php echo m_e($user['name'] ?? 'Faculty'); ?>.
                </p>
            </div>
        </section>

        <section class="students-list-section">
            <div class="container">

                <?php if ($flashText !== ''): ?>
                    <p class="data-status<?php echo $flashOk ? '' : ' data-error'; ?>" role="status">
                        <?php echo m_e($flashText); ?>
                    </p>
                <?php endif; ?>

                <?php if (!empty($formErrors)): ?>
                    <div class="register-form-card" role="alert">
                        <h2>Please fix <?php echo count($formErrors); ?> field(s)</h2>
                        <ul>
                            <?php foreach ($formErrors as $message): ?>
                                <li><?php echo m_e($message); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Add / Edit form -->
                <div class="register-form-card">
                    <div class="register-form-heading">
                        <h2><?php echo $editId !== null ? 'Edit Student' : 'Add Student'; ?></h2>
                        <p><?php echo $editId !== null
                            ? 'Update the details below and save.'
                            : 'Fill in the details of the new student.'; ?></p>
                    </div>
                    <form method="post" action="student-manage.php" novalidate>
                        <input type="hidden" name="action" value="<?php echo $editId !== null ? 'update' : 'add'; ?>">
                        <?php if ($editId !== null): ?>
                            <input type="hidden" name="id" value="<?php echo (int) $editId; ?>">
                        <?php endif; ?>

                        <div class="form-group<?php echo isset($formErrors['enrollment_id']) ? ' invalid' : ''; ?>">
                            <label for="m-enrollment">Enrollment ID <span class="required" aria-hidden="true">*</span></label>
                            <input type="text" id="m-enrollment" name="enrollment_id" maxlength="20"
                                placeholder="e.g. STU2026001" value="<?php echo m_e($formValues['enrollment_id']); ?>" required>
                            <?php if (isset($formErrors['enrollment_id'])): ?>
                                <span class="field-error" role="alert"><?php echo m_e($formErrors['enrollment_id']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group<?php echo isset($formErrors['full_name']) ? ' invalid' : ''; ?>">
                            <label for="m-name">Full Name <span class="required" aria-hidden="true">*</span></label>
                            <input type="text" id="m-name" name="full_name" maxlength="100"
                                placeholder="Student full name" value="<?php echo m_e($formValues['full_name']); ?>" required>
                            <?php if (isset($formErrors['full_name'])): ?>
                                <span class="field-error" role="alert"><?php echo m_e($formErrors['full_name']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group<?php echo isset($formErrors['email']) ? ' invalid' : ''; ?>">
                            <label for="m-email">Email <span class="required" aria-hidden="true">*</span></label>
                            <input type="email" id="m-email" name="email" maxlength="150"
                                placeholder="student@example.com" value="<?php echo m_e($formValues['email']); ?>" required>
                            <?php if (isset($formErrors['email'])): ?>
                                <span class="field-error" role="alert"><?php echo m_e($formErrors['email']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group<?php echo isset($formErrors['phone']) ? ' invalid' : ''; ?>">
                            <label for="m-phone">Mobile <span class="required" aria-hidden="true">*</span></label>
                            <input type="tel" id="m-phone" name="phone" maxlength="15" inputmode="numeric"
                                placeholder="10-digit mobile number" value="<?php echo m_e($formValues['phone']); ?>" required>
                            <?php if (isset($formErrors['phone'])): ?>
                                <span class="field-error" role="alert"><?php echo m_e($formErrors['phone']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group<?php echo isset($formErrors['course']) ? ' invalid' : ''; ?>">
                            <label for="m-course">Course <span class="required" aria-hidden="true">*</span></label>
                            <select id="m-course" name="course" required>
                                <option value="">Select course</option>
                                <?php foreach (MANAGE_COURSES as $value => $label): ?>
                                    <option value="<?php echo m_e($value); ?>"<?php echo $formValues['course'] === $value ? ' selected' : ''; ?>>
                                        <?php echo m_e($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($formErrors['course'])): ?>
                                <span class="field-error" role="alert"><?php echo m_e($formErrors['course']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group<?php echo isset($formErrors['year']) ? ' invalid' : ''; ?>">
                            <label for="m-year">Academic Year <span class="required" aria-hidden="true">*</span></label>
                            <select id="m-year" name="year" required>
                                <option value="">Select year</option>
                                <?php foreach (MANAGE_YEARS as $value): ?>
                                    <option value="<?php echo m_e($value); ?>"<?php echo $formValues['year'] === $value ? ' selected' : ''; ?>>
                                        Year <?php echo m_e($value); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($formErrors['year'])): ?>
                                <span class="field-error" role="alert"><?php echo m_e($formErrors['year']); ?></span>
                            <?php endif; ?>
                        </div>

                        <fieldset class="form-group gender-group<?php echo isset($formErrors['gender']) ? ' invalid' : ''; ?>">
                            <legend>Gender <span class="required" aria-hidden="true">*</span></legend>
                            <div class="gender-options">
                                <?php foreach (MANAGE_GENDERS as $value): ?>
                                    <label>
                                        <input type="radio" name="gender" value="<?php echo m_e($value); ?>"<?php echo $formValues['gender'] === $value ? ' checked' : ''; ?>>
                                        <?php echo m_e(ucfirst($value)); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <?php if (isset($formErrors['gender'])): ?>
                                <span class="field-error" role="alert"><?php echo m_e($formErrors['gender']); ?></span>
                            <?php endif; ?>
                        </fieldset>

                        <button type="submit" class="btn register-submit">
                            <?php echo $editId !== null ? 'Save Changes' : 'Add Student'; ?>
                        </button>
                        <?php if ($editId !== null): ?>
                            <p class="register-login">
                                <a href="student-manage.php">Cancel editing</a>
                            </p>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Search + filters -->
                <form method="get" action="student-manage.php" class="data-controls">
                    <div class="data-control">
                        <label for="q">Search students</label>
                        <input type="search" id="q" name="q" autocomplete="off"
                            placeholder="Name, email or course" value="<?php echo m_e($search); ?>">
                    </div>
                    <div class="data-control">
                        <label for="f-course">Course</label>
                        <select id="f-course" name="course">
                            <option value="">All courses</option>
                            <?php foreach (MANAGE_COURSES as $value => $label): ?>
                                <option value="<?php echo m_e($value); ?>"<?php echo $filterCourse === $value ? ' selected' : ''; ?>>
                                    <?php echo m_e($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="data-control">
                        <label for="f-year">Academic Year</label>
                        <select id="f-year" name="year">
                            <option value="">All years</option>
                            <?php foreach (MANAGE_YEARS as $value): ?>
                                <option value="<?php echo m_e($value); ?>"<?php echo $filterYear === $value ? ' selected' : ''; ?>>
                                    Year <?php echo m_e($value); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="data-control">
                        <button type="submit" class="btn">Search</button>
                    </div>
                    <div class="data-control">
                        <a href="student-manage.php" class="btn btn-outline">Reset</a>
                    </div>
                </form>

                <p class="data-count" aria-live="polite">
                    <?php if ($listError): ?>
                        Student list is unavailable right now. Please try again later.
                    <?php elseif ($total === 0): ?>
                        No student records found. Try different search or filter values.
                    <?php else: ?>
                        Total students: <?php echo $total; ?>.
                        Showing <?php echo $from; ?>&ndash;<?php echo $to; ?>.
                    <?php endif; ?>
                </p>

                <!-- Records table -->
                <div class="student-table-wrapper">
                    <table class="student-table">
                        <caption>Student records</caption>
                        <thead>
                            <tr>
                                <th scope="col">Enrollment ID</th>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Mobile</th>
                                <th scope="col">Course</th>
                                <th scope="col">Year</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $row): ?>
                                <tr>
                                    <td><?php echo m_e((string) ($row['enrollment_id'] ?? '—')); ?></td>
                                    <td><?php echo m_e($row['full_name']); ?></td>
                                    <td><?php echo m_e($row['email']); ?></td>
                                    <td><?php echo m_e($row['mobile']); ?></td>
                                    <td><?php echo m_e($row['course']); ?></td>
                                    <td><?php echo m_e((string) ($row['year'] ?? '—')); ?></td>
                                    <td>
                                        <a class="btn btn-outline btn-small"
                                           href="student-manage.php?edit=<?php echo (int) $row['student_id']; ?>">Edit</a>
                                        <form method="post" action="student-manage.php" class="inline-form"
                                            onsubmit="return confirm('Delete this student record? This cannot be undone.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo (int) $row['student_id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($students) && !$listError): ?>
                                <tr>
                                    <td colspan="7">No records to display.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination (keeps search + filters) -->
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a class="btn btn-outline"
                           href="<?php echo m_e(m_page_url($page - 1, $search, $filterCourse, $filterYear)); ?>">Previous</a>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline" disabled>Previous</button>
                    <?php endif; ?>
                    <span class="page-info" aria-live="polite">
                        Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                    </span>
                    <?php if ($page < $totalPages): ?>
                        <a class="btn btn-outline"
                           href="<?php echo m_e(m_page_url($page + 1, $search, $filterCourse, $filterYear)); ?>">Next</a>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline" disabled>Next</button>
                    <?php endif; ?>
                </div>

                <p class="section-link">
                    <a href="students-ajax.php" class="btn">Try the Live (No-Reload) Version</a>
                    <a href="faculty-dashboard.php" class="btn btn-outline">Back to Dashboard</a>
                </p>

            </div>
        </section>
    </main>
    <footer class="site-footer">
        <div class="container footer-content">
            <div class="footer-column">
                <h3>StudentHub</h3>
                <p>Your campus. Your community. Your hub.</p>
            </div>
            <div class="footer-column">
                <h3>Faculty</h3>
                <a href="faculty-dashboard.php">Dashboard</a>
                <a href="student-manage.php" aria-current="page">Manage Students</a>
                <a href="event-manage.php">Events</a>
            </div>
            <div class="footer-column">
                <h3>Support</h3>
                <a href="contact.html">Contact</a>
                <a href="faq.html">FAQ</a>
            </div>
        </div>
        <div class="footer-bottom"><p>&copy; 2026 StudentHub</p></div>
    </footer>
    <script src="../js/script.js"></script>
</body>
</html>