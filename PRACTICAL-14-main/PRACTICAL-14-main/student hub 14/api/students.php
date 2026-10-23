<?php
/* =====================================================
   STUDENTHUB - PRACTICAL 13
   JSON API for students: list, add, update, delete.
   All responses are JSON: { success, message, data }.

   Endpoints (all under this one file):
     GET    api/students.php?q=&page=&per_page=  list (+search)
     GET    api/students.php?id=12               single student
     POST   api/students.php                     add (JSON body)
     PUT    api/students.php                     update (JSON body, id+fields)
     DELETE api/students.php                     delete (JSON body {id})

   POST with {"_method":"PUT"} or {"_method":"DELETE"}
   is accepted as a documented fallback for hosts that
   block PUT/DELETE.

   Faculty or admin login required (401/403 otherwise). Mutating
   requests must carry the session CSRF token in the
   X-CSRF-Token header (or _csrf body field).
   ===================================================== */
declare(strict_types=1);

require_once __DIR__ . '/../php/auth.php';
require_once __DIR__ . '/../php/db_mysqli.php';
require_once __DIR__ . '/../php/audit_logger.php'; // P14: audit CRUD successes

const API_COURSES = [
    'btech' => 'B.Tech', 'bca' => 'BCA', 'bba' => 'BBA',
    'bcom' => 'B.Com', 'bsc' => 'B.Sc', 'other' => 'Other',
];
const API_YEARS = ['1', '2', '3', '4'];
const API_GENDERS = ['male', 'female', 'other'];
const API_PER_PAGE = 8;
const API_MAX_PER_PAGE = 50;

// Send one JSON response and stop. Nothing else may print.
function api_send(bool $ok, string $message, $data, int $status): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        ['success' => $ok, 'message' => $message, 'data' => $data],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

function api_input(array $in, string $key): string
{
    if (!isset($in[$key]) || is_array($in[$key])) {
        return '';
    }
    return trim((string) $in[$key]);
}

// Same validation rules as the Practical 11 page.
function api_validate(array $in, ?int $ignoreId = null): array
{
    $errors = [];

    $enroll = api_input($in, 'enrollment_id');
    if ($enroll === '') {
        $errors['enrollment_id'] = 'Please enter the enrollment ID.';
    } elseif (mb_strlen($enroll) > 20) {
        $errors['enrollment_id'] = 'Enrollment ID must be 20 characters or fewer.';
    } elseif (api_taken('enrollment_id', $enroll, $ignoreId)) {
        $errors['enrollment_id'] = 'This enrollment ID is already registered.';
    }

    $name = api_input($in, 'full_name');
    if ($name === '') {
        $errors['full_name'] = 'Please enter the full name.';
    } elseif (mb_strlen($name) < 3) {
        $errors['full_name'] = 'Name must be at least 3 characters long.';
    } elseif (!preg_match("/^[A-Za-z][A-Za-z\\s.'-]{1,48}[A-Za-z.]$/u", $name)) {
        $errors['full_name'] = 'Name may contain only letters, spaces, dots, hyphens and apostrophes.';
    }

    $email = api_input($in, 'email');
    if ($email === '') {
        $errors['email'] = 'Please enter the email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } elseif (api_taken('email', $email, $ignoreId)) {
        $errors['email'] = 'This email address is already registered.';
    }

    $phoneRaw = api_input($in, 'phone');
    $phone = str_replace([' ', '-'], '', $phoneRaw);
    if ($phoneRaw === '') {
        $errors['phone'] = 'Please enter the mobile number.';
    } elseif (!preg_match('/^(\+91|91)?[6-9]\d{9}$/', $phone)) {
        $errors['phone'] = 'Please enter a valid 10-digit mobile number starting with 6-9.';
    }

    if (!array_key_exists(api_input($in, 'course'), API_COURSES)) {
        $errors['course'] = 'Please select a course.';
    }
    if (!in_array(api_input($in, 'year'), API_YEARS, true)) {
        $errors['year'] = 'Please select an academic year.';
    }
    if (!in_array(api_input($in, 'gender'), API_GENDERS, true)) {
        $errors['gender'] = 'Please select a gender.';
    }

    return $errors;
}

function api_taken(string $column, string $value, ?int $ignoreId): bool
{
    if (!in_array($column, ['enrollment_id', 'email'], true) || $value === '') {
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

function api_row(array $row): array
{
    return [
        'id' => (int) $row['student_id'],
        'enrollment_id' => (string) ($row['enrollment_id'] ?? ''),
        'name' => (string) $row['full_name'],
        'email' => (string) $row['email'],
        'mobile' => (string) $row['mobile'],
        'course' => (string) $row['course'],
        'academic_year' => $row['year'] === null ? null : (int) $row['year'],
        'gender' => (string) ($row['gender'] ?? ''),
    ];
}

/* ---------- authentication: faculty session required ---------- */

start_secure_session();
$user = current_user();
if ($user === null) {
    if (session_expired()) {
        logout_user();
    }
    api_send(false, 'Please log in to use this API.', null, 401);
}
if (session_expired()) {
    try {
        logout_user(require_once_db());
    } catch (Throwable $e) {
        logout_user();
    }
    api_send(false, 'Session expired. Please log in again.', null, 401);
}
touch_activity();
// Practical 14: faculty and admins may use the API.
if (!in_array(($user['role'] ?? ''), ['faculty', 'admin'], true)) {
    api_send(false, 'Only faculty or admin accounts may manage students.', null, 403);
}

function require_once_db(): ?PDO
{
    try {
        return getDbConnection();
    } catch (Throwable $e) {
        return null;
    }
}

/* ---------- read request ---------- */

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$rawBody = file_get_contents('php://input');
$body = [];
if ($rawBody !== '' && $rawBody !== false) {
    $decoded = json_decode($rawBody, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        api_send(false, 'Request body is not valid JSON.', null, 400);
    }
    $body = is_array($decoded) ? $decoded : [];
}

// Documented fallback: POST carrying {"_method":"PUT"|"DELETE"}.
if ($method === 'POST' && isset($body['_method'])
    && in_array(strtoupper((string) $body['_method']), ['PUT', 'DELETE'], true)) {
    $method = strtoupper((string) $body['_method']);
}

// Mutations need a valid CSRF token (header preferred).
if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token']
        ?? ($body['_csrf'] ?? null);
    if (!csrf_check(is_string($token) ? $token : null)) {
        api_send(false, 'Invalid or missing request token. Reload the page and try again.', null, 403);
    }
}

try {
    $db = crud_db();
} catch (RuntimeException $e) {
    api_send(false, $e->getMessage(), null, 500);
}

/* ---------- GET: single or list ---------- */

if ($method === 'GET') {
    $idRaw = $_GET['id'] ?? '';
    if ($idRaw !== '') {
        if (!ctype_digit((string) $idRaw) || (int) $idRaw <= 0) {
            api_send(false, 'Invalid student ID.', null, 400);
        }
        $id = (int) $idRaw;
        $stmt = $db->prepare(
            'SELECT student_id, enrollment_id, full_name, email,
                    mobile, course, year, gender
             FROM students WHERE student_id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!is_array($row)) {
            api_send(false, 'Student not found.', null, 404);
        }
        api_send(true, 'Student fetched successfully.', api_row($row), 200);
    }

    $q = isset($_GET['q']) && !is_array($_GET['q']) ? trim((string) $_GET['q']) : '';
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = (int) ($_GET['per_page'] ?? API_PER_PAGE);
    $perPage = min(max(1, $perPage), API_MAX_PER_PAGE);

    $where = '';
    $types = '';
    $params = [];
    if ($q !== '') {
        $where = 'WHERE (full_name LIKE ? OR email LIKE ? OR course LIKE ? OR enrollment_id LIKE ?)';
        $like = '%' . $q . '%';
        $types = 'ssss';
        $params = [$like, $like, $like, $like];
    }

    $countStmt = $db->prepare("SELECT COUNT(*) AS c FROM students $where");
    if ($types !== '') {
        $refs = [];
        foreach ($params as $i => $v) {
            $refs[$i] = &$params[$i];
        }
        $countStmt->bind_param($types, ...$refs);
    }
    $countStmt->execute();
    $total = (int) $countStmt->get_result()->fetch_assoc()['c'];
    $countStmt->close();

    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $listTypes = $types . 'ii';
    $listParams = array_merge($params, [$offset, $perPage]);
    $listStmt = $db->prepare(
        "SELECT student_id, enrollment_id, full_name, email,
                mobile, course, year, gender
         FROM students $where
         ORDER BY full_name ASC
         LIMIT ?, ?"
    );
    $refs = [];
    foreach ($listParams as $i => $v) {
        $refs[$i] = &$listParams[$i];
    }
    $listStmt->bind_param($listTypes, ...$refs);
    $listStmt->execute();
    $rows = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $listStmt->close();

    api_send(true, 'Students fetched successfully.', [
        'students' => array_map('api_row', $rows),
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
    ], 200);
}

/* ---------- POST: add ---------- */

if ($method === 'POST') {
    $errors = api_validate($body);
    if (!empty($errors)) {
        api_send(false, 'Please fix the highlighted fields.', ['errors' => $errors], 400);
    }

    $role = 'student';
    $year = (int) $body['year'];
    $enrollment = trim((string) $body['enrollment_id']);
    $name = trim((string) $body['full_name']);
    $email = trim((string) $body['email']);
    $phone = trim((string) $body['phone']);
    $course = trim((string) $body['course']);
    $gender = trim((string) $body['gender']);

    try {
        $stmt = $db->prepare(
            'INSERT INTO students
                (enrollment_id, full_name, email, mobile,
                 course, year, gender, role)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'sssssiss',
            $enrollment, $name, $email, $phone,
            $course, $year, $gender, $role
        );
        $stmt->execute();
        $newId = (int) $stmt->insert_id;
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        if ((int) $e->getCode() === 1062) {
            api_send(false, 'This email or enrollment ID was just registered by someone else.', null, 409);
        }
        error_log('StudentHub API insert failed.');
        api_send(false, 'Could not save the student. Please try again.', null, 500);
    }

    audit_log(
        'student_add',
        'student',
        $newId > 0 ? $newId : null,
        'Added student ' . $name . ' (' . $email . ') via API.'
    );
    api_send(true, 'Student added successfully.', ['id' => $newId], 201);
}

/* ---------- PUT: update ---------- */

if ($method === 'PUT') {
    $id = isset($body['id']) && ctype_digit((string) $body['id'])
        ? (int) $body['id'] : 0;
    if ($id <= 0) {
        api_send(false, 'Invalid student ID.', null, 400);
    }

    $check = $db->prepare('SELECT 1 FROM students WHERE student_id = ? LIMIT 1');
    $check->bind_param('i', $id);
    $check->execute();
    $exists = (bool) $check->get_result()->fetch_assoc();
    $check->close();
    if (!$exists) {
        api_send(false, 'Student not found.', null, 404);
    }

    $errors = api_validate($body, $id);
    if (!empty($errors)) {
        api_send(false, 'Please fix the highlighted fields.', ['errors' => $errors], 400);
    }

    $year = (int) $body['year'];
    $enrollment = trim((string) $body['enrollment_id']);
    $name = trim((string) $body['full_name']);
    $email = trim((string) $body['email']);
    $phone = trim((string) $body['phone']);
    $course = trim((string) $body['course']);
    $gender = trim((string) $body['gender']);

    try {
        $stmt = $db->prepare(
            'UPDATE students SET
                enrollment_id = ?, full_name = ?, email = ?,
                mobile = ?, course = ?, year = ?, gender = ?
             WHERE student_id = ?'
        );
        $stmt->bind_param(
            'sssssssi',
            $enrollment, $name, $email, $phone,
            $course, $year, $gender, $id
        );
        $stmt->execute();
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        if ((int) $e->getCode() === 1062) {
            api_send(false, 'This email or enrollment ID was just registered by someone else.', null, 409);
        }
        error_log('StudentHub API update failed.');
        api_send(false, 'Could not save the student. Please try again.', null, 500);
    }

    audit_log(
        'student_update',
        'student',
        $id,
        'Updated student ' . $name . ' (' . $email . ') via API.'
    );
    api_send(true, 'Student updated successfully.', ['id' => $id], 200);
}

/* ---------- DELETE ---------- */

if ($method === 'DELETE') {
    $id = isset($body['id']) && ctype_digit((string) $body['id'])
        ? (int) $body['id'] : 0;
    if ($id <= 0) {
        api_send(false, 'Invalid student ID.', null, 400);
    }

    $stmt = $db->prepare('DELETE FROM students WHERE student_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$deleted) {
        api_send(false, 'Student not found.', null, 404);
    }

    audit_log(
        'student_delete',
        'student',
        $id,
        'Deleted student ID ' . $id . ' via API.'
    );
    api_send(true, 'Student deleted successfully.', ['id' => $id], 200);
}

api_send(false, 'Unsupported request method.', null, 405);
