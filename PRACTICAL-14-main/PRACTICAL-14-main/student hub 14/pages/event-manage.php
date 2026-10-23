<?php
/* =====================================================
   STUDENTHUB - PRACTICAL 12
   Event Management: faculty-only CRUD with poster upload,
   file validation, search, filter and pagination.
   Uses MySQLi prepared statements.
   ===================================================== */
declare(strict_types=1);

require_once __DIR__ . '/../php/auth.php';
require_once __DIR__ . '/../php/db_mysqli.php';
require_once __DIR__ . '/../php/audit_logger.php'; // P14: audit CRUD successes

// Practical 14: faculty and admins may manage events.
$user = require_roles(['faculty', 'admin'], 'faculty-login.html');

const EVENT_CATEGORIES = ['Cultural', 'Technical', 'Sports', 'Academic', 'Community'];
const EVENT_STATUSES = ['upcoming', 'open', 'closed'];
const EVENT_PER_PAGE = 6;
const MAX_POSTER_SIZE = 2 * 1024 * 1024; // 2 MB
const ALLOWED_POSTER_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];
const POSTER_DIR = __DIR__ . '/../uploads/event_posters/';

// Accepts NULL: rows created before Practical 12 may have empty
// event_time / venue / description columns.
function ev_e(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function ev_post(array $post, string $key): string
{
    if (!isset($post[$key]) || is_array($post[$key])) {
        return '';
    }
    return trim((string) $post[$key]);
}

function ev_get(string $key): string
{
    if (!isset($_GET[$key]) || is_array($_GET[$key])) {
        return '';
    }
    return trim((string) $_GET[$key]);
}

// Validate event form data. Returns [field => message].
function ev_validate(array $post, ?int $ignoreId = null): array
{
    $errors = [];

    $title = ev_post($post, 'title');
    if ($title === '') {
        $errors['title'] = 'Please enter the event title.';
    } elseif (mb_strlen($title) > 150) {
        $errors['title'] = 'Title must be 150 characters or fewer.';
    }

    $description = ev_post($post, 'description');
    if ($description === '') {
        $errors['description'] = 'Please enter the event description.';
    }

    if (!in_array(ev_post($post, 'category'), EVENT_CATEGORIES, true)) {
        $errors['category'] = 'Please select a valid category.';
    }

    $date = ev_post($post, 'event_date');
    if ($date === '') {
        $errors['event_date'] = 'Please select the event date.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $errors['event_date'] = 'Please enter a valid date (YYYY-MM-DD).';
    }

    $time = ev_post($post, 'event_time');
    if ($time !== '' && !preg_match('/^\d{2}:\d{2}$/', $time)) {
        $errors['event_time'] = 'Please enter a valid time (HH:MM).';
    }

    $venue = ev_post($post, 'venue');
    if ($venue === '') {
        $errors['venue'] = 'Please enter the venue.';
    } elseif (mb_strlen($venue) > 150) {
        $errors['venue'] = 'Venue must be 150 characters or fewer.';
    }

    if (!in_array(ev_post($post, 'status'), EVENT_STATUSES, true)) {
        $errors['status'] = 'Please select a valid status.';
    }

    return $errors;
}

// Validate and process poster upload. Returns [filename, error].
function ev_process_poster(array $files, ?string $existingPoster = null): array
{
    // No new upload: keep existing (or null for new events).
    if (!isset($files['poster']) || $files['poster']['error'] === UPLOAD_ERR_NO_FILE) {
        return [$existingPoster, null];
    }

    $file = $files['poster'];

    // Check upload error code.
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [$existingPoster, 'Poster upload failed. Please try again.'];
    }

    // Check file size on the server.
    if ($file['size'] > MAX_POSTER_SIZE) {
        return [$existingPoster, 'Poster must be 2 MB or smaller.'];
    }

    // Validate actual file type using finfo (not browser MIME).
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!array_key_exists($mimeType, ALLOWED_POSTER_TYPES)) {
        return [$existingPoster, 'Only JPEG, PNG and WebP images are allowed.'];
    }

    // Verify it is a valid image.
    if (!getimagesize($file['tmp_name'])) {
        return [$existingPoster, 'The uploaded file is not a valid image.'];
    }

    // Generate a safe, unique filename.
    $extension = ALLOWED_POSTER_TYPES[$mimeType];
    $filename = 'event_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $destination = POSTER_DIR . $filename;

    // Move the file.
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return [$existingPoster, 'Could not save the poster. Please try again.'];
    }

    return [$filename, null];
}

function ev_redirect(string $query): void
{
    header('Location: event-manage.php' . $query, true, 303);
    exit;
}

/* ---------- POST actions (add / update / delete) ---------- */

$formErrors = [];
$formValues = [
    'title' => '', 'description' => '', 'category' => '',
    'event_date' => '', 'event_time' => '', 'venue' => '',
    'status' => 'upcoming',
];
$editId = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = ev_post($_POST, 'action');

    if ($action === 'delete') {
        $id = (int) ev_post($_POST, 'id');
        if ($id > 0) {
            try {
                $db = crud_db();

                // Check for registration references first.
                $checkStmt = $db->prepare(
                    'SELECT COUNT(*) AS c FROM registrations WHERE event_id = ?'
                );
                $checkStmt->bind_param('i', $id);
                $checkStmt->execute();
                $regCount = (int) $checkStmt->get_result()->fetch_assoc()['c'];
                $checkStmt->close();

                if ($regCount > 0) {
                    ev_redirect('?msg=hasregs');
                }

                // Get poster filename before deleting.
                $posterStmt = $db->prepare(
                    'SELECT poster, title FROM events WHERE event_id = ?'
                );
                $posterStmt->bind_param('i', $id);
                $posterStmt->execute();
                $posterRow = $posterStmt->get_result()->fetch_assoc();
                $posterStmt->close();

                // Delete the event record.
                $delStmt = $db->prepare('DELETE FROM events WHERE event_id = ?');
                $delStmt->bind_param('i', $id);
                $delStmt->execute();
                $deleted = $delStmt->affected_rows > 0;
                $delStmt->close();

                if ($deleted) {
                    $delTitle = is_array($posterRow)
                        ? (string) ($posterRow['title'] ?? '') : '';
                    audit_log(
                        'event_delete',
                        'event',
                        $id,
                        $delTitle !== ''
                            ? 'Deleted event ' . $delTitle . ' (ID ' . $id . ').'
                            : 'Deleted event ID ' . $id . '.'
                    );
                }

                // Remove poster file only after successful DB deletion.
                if ($deleted && $posterRow && !empty($posterRow['poster'])) {
                    $posterPath = POSTER_DIR . basename($posterRow['poster']);
                    if (file_exists($posterPath) && is_file($posterPath)) {
                        unlink($posterPath);
                    }
                }

                ev_redirect($deleted ? '?msg=deleted' : '?msg=notfound');
            } catch (mysqli_sql_exception $e) {
                error_log('StudentHub event delete failed.');
                ev_redirect('?msg=dberror');
            }
        }
        ev_redirect('?msg=notfound');
    }

    if ($action === 'add' || $action === 'update') {
        $id = $action === 'update' ? (int) ev_post($_POST, 'id') : null;
        if ($action === 'update' && ($id === null || $id <= 0)) {
            ev_redirect('?msg=notfound');
        }
        foreach (array_keys($formValues) as $key) {
            $formValues[$key] = ev_post($_POST, $key);
        }
        $formErrors = ev_validate($_POST, $id);

        // For new events, a poster is required.
        if ($action === 'add' && empty($_FILES['poster']['name'])) {
            $formErrors['poster'] = 'Please upload an event poster.';
        }

        if (empty($formErrors)) {
            try {
                $db = crud_db();

                // Get existing poster for updates.
                $existingPoster = null;
                if ($id !== null) {
                    $stmt = $db->prepare('SELECT poster FROM events WHERE event_id = ?');
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    $existingPoster = $row['poster'] ?? null;
                }

                // Process poster upload.
                [$posterFile, $posterError] = ev_process_poster($_FILES, $existingPoster);

                if ($posterError !== null) {
                    $formErrors['poster'] = $posterError;
                } else {
                    // bind_param needs variables by reference.
                $eventTime = $formValues['event_time'] !== '' ? $formValues['event_time'] : null;

                    if ($action === 'add') {
                        $stmt = $db->prepare(
                            'INSERT INTO events
                                (title, description, category, event_date,
                                 event_time, venue, poster, status)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                        );
                        $stmt->bind_param(
                            'ssssssss',
                            $formValues['title'],
                            $formValues['description'],
                            $formValues['category'],
                            $formValues['event_date'],
                            $eventTime,
                            $formValues['venue'],
                            $posterFile,
                            $formValues['status']
                        );
                        $stmt->execute();
                        $newEventId = (int) $stmt->insert_id;
                        $stmt->close();
                        audit_log(
                            'event_add',
                            'event',
                            $newEventId > 0 ? $newEventId : null,
                            'Added event ' . $formValues['title'] . '.'
                        );
                        ev_redirect('?msg=added');
                    } else {
                        $stmt = $db->prepare(
                            'UPDATE events SET
                                title = ?, description = ?, category = ?,
                                event_date = ?, event_time = ?, venue = ?,
                                poster = ?, status = ?
                             WHERE event_id = ?'
                        );
                        $stmt->bind_param(
                            'ssssssssi',
                            $formValues['title'],
                            $formValues['description'],
                            $formValues['category'],
                            $formValues['event_date'],
                            $eventTime,
                            $formValues['venue'],
                            $posterFile,
                            $formValues['status'],
                            $id
                        );
                        $stmt->execute();
                        $stmt->close();
                        audit_log(
                            'event_update',
                            'event',
                            $id,
                            'Updated event ' . $formValues['title'] . '.'
                        );
                        ev_redirect('?msg=updated');
                    }
                }
            } catch (mysqli_sql_exception $e) {
                error_log('StudentHub event save failed.');
                ev_redirect('?msg=dberror');
            }
        } elseif ($action === 'update') {
            $editId = $id;
        }
    }
}

/* ---------- edit mode: load the row into the form ---------- */

if ($editId === null && ev_get('edit') !== '') {
    $editId = (int) ev_get('edit');
    if ($editId > 0) {
        $stmt = crud_db()->prepare(
            'SELECT title, description, category, event_date,
                    event_time, venue, poster, status
             FROM events WHERE event_id = ? LIMIT 1'
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

$search = ev_get('q');
$filterCategory = ev_get('category');
$filterStatus = ev_get('status');
$page = max(1, (int) ev_get('page'));

$where = [];
$types = '';
$params = [];

if ($search !== '') {
    $where[] = '(title LIKE ? OR description LIKE ? OR venue LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}
if ($filterCategory !== '' && in_array($filterCategory, EVENT_CATEGORIES, true)) {
    $where[] = 'category = ?';
    $types .= 's';
    $params[] = $filterCategory;
} else {
    $filterCategory = '';
}
if ($filterStatus !== '' && in_array($filterStatus, EVENT_STATUSES, true)) {
    $where[] = 'status = ?';
    $types .= 's';
    $params[] = $filterStatus;
} else {
    $filterStatus = '';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

function ev_bind_all(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '') {
        return;
    }
    $refs = [];
    foreach ($params as $i => $value) {
        $refs[$i] = &$params[$i];
    }
    $stmt->bind_param($types, ...$refs);
}

try {
    $db = crud_db();

    $countStmt = $db->prepare("SELECT COUNT(*) AS c FROM events $whereSql");
    ev_bind_all($countStmt, $types, $params);
    $countStmt->execute();
    $total = (int) $countStmt->get_result()->fetch_assoc()['c'];
    $countStmt->close();

    $totalPages = max(1, (int) ceil($total / EVENT_PER_PAGE));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * EVENT_PER_PAGE;

    $listTypes = $types . 'ii';
    $listParams = array_merge($params, [$offset, EVENT_PER_PAGE]);
    $listStmt = $db->prepare(
        "SELECT event_id, title, description, category, event_date,
                event_time, venue, poster, status
         FROM events $whereSql
         ORDER BY event_date ASC, event_id ASC
         LIMIT ?, ?"
    );
    ev_bind_all($listStmt, $listTypes, $listParams);
    $listStmt->execute();
    $events = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $listStmt->close();
    $listError = false;
} catch (mysqli_sql_exception $e) {
    error_log('StudentHub event list failed.');
    $events = [];
    $total = 0;
    $totalPages = 1;
    $listError = true;
}

function ev_page_url(int $page, string $search, string $category, string $status): string
{
    $q = ['page' => $page];
    if ($search !== '') {
        $q['q'] = $search;
    }
    if ($category !== '') {
        $q['category'] = $category;
    }
    if ($status !== '') {
        $q['status'] = $status;
    }
    return 'event-manage.php?' . http_build_query($q);
}

$messages = [
    'added' => 'Event added successfully.',
    'updated' => 'Event updated successfully.',
    'deleted' => 'Event deleted successfully.',
    'notfound' => 'Event not found. It may already have been removed.',
    'dberror' => 'Something went wrong while saving. Please try again.',
    'hasregs' => 'Cannot delete: students are registered for this event.',
];
$flash = ev_get('msg');
$flashText = $messages[$flash] ?? '';
$flashOk = in_array($flash, ['added', 'updated', 'deleted'], true);

$from = $total === 0 ? 0 : $offset + 1;
$to = min($offset + EVENT_PER_PAGE, $total);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="StudentHub event management">
    <title>Event Management | StudentHub</title>
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
            <?php
            // Practical 14: shared dynamic menu (admin sees extra links).
            $navPage = 'events';
            $navRole = $user['role'] ?? 'faculty';
            include __DIR__ . '/../php/faculty_nav.php';
            ?>
        </div>
    </header>
    <main id="main">
        <section class="faculty-events-hero">
            <div class="container">
                <p class="eyebrow">FACULTY PORTAL</p>
                <h2>Event Management</h2>
                <p>
                    Create, view, edit, delete and search events.
                    Signed in as <?php echo ev_e($user['name'] ?? 'Faculty'); ?>.
                </p>
            </div>
        </section>

        <section class="section">
            <div class="container">

                <?php if ($flashText !== ''): ?>
                    <p class="data-status<?php echo $flashOk ? '' : ' data-error'; ?>" role="status">
                        <?php echo ev_e($flashText); ?>
                    </p>
                <?php endif; ?>

                <?php if (!empty($formErrors)): ?>
                    <div class="register-form-card" role="alert">
                        <h2>Please fix <?php echo count($formErrors); ?> field(s)</h2>
                        <ul>
                            <?php foreach ($formErrors as $message): ?>
                                <li><?php echo ev_e($message); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Add / Edit form -->
                <div class="register-form-card">
                    <div class="register-form-heading">
                        <h2><?php echo $editId !== null ? 'Edit Event' : 'Add New Event'; ?></h2>
                        <p><?php echo $editId !== null
                            ? 'Update the details below and save.'
                            : 'Fill in the details of the new event.'; ?></p>
                    </div>
                    <form method="post" action="event-manage.php" enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="action" value="<?php echo $editId !== null ? 'update' : 'add'; ?>">
                        <?php if ($editId !== null): ?>
                            <input type="hidden" name="id" value="<?php echo (int) $editId; ?>">
                        <?php endif; ?>

                        <div class="form-group<?php echo isset($formErrors['title']) ? ' invalid' : ''; ?>">
                            <label for="ev-title">Event Title <span class="required" aria-hidden="true">*</span></label>
                            <input type="text" id="ev-title" name="title" maxlength="150"
                                placeholder="Event title" value="<?php echo ev_e($formValues['title']); ?>" required>
                            <?php if (isset($formErrors['title'])): ?>
                                <span class="field-error" role="alert"><?php echo ev_e($formErrors['title']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group<?php echo isset($formErrors['description']) ? ' invalid' : ''; ?>">
                            <label for="ev-desc">Description <span class="required" aria-hidden="true">*</span></label>
                            <textarea id="ev-desc" name="description" rows="4"
                                placeholder="Event description" required><?php echo ev_e($formValues['description']); ?></textarea>
                            <?php if (isset($formErrors['description'])): ?>
                                <span class="field-error" role="alert"><?php echo ev_e($formErrors['description']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group<?php echo isset($formErrors['category']) ? ' invalid' : ''; ?>">
                            <label for="ev-category">Category <span class="required" aria-hidden="true">*</span></label>
                            <select id="ev-category" name="category" required>
                                <option value="">Select category</option>
                                <?php foreach (EVENT_CATEGORIES as $cat): ?>
                                    <option value="<?php echo ev_e($cat); ?>"<?php echo $formValues['category'] === $cat ? ' selected' : ''; ?>>
                                        <?php echo ev_e($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($formErrors['category'])): ?>
                                <span class="field-error" role="alert"><?php echo ev_e($formErrors['category']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group<?php echo isset($formErrors['event_date']) ? ' invalid' : ''; ?>">
                            <label for="ev-date">Event Date <span class="required" aria-hidden="true">*</span></label>
                            <input type="date" id="ev-date" name="event_date"
                                value="<?php echo ev_e($formValues['event_date']); ?>" required>
                            <?php if (isset($formErrors['event_date'])): ?>
                                <span class="field-error" role="alert"><?php echo ev_e($formErrors['event_date']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group<?php echo isset($formErrors['event_time']) ? ' invalid' : ''; ?>">
                            <label for="ev-time">Event Time</label>
                            <input type="time" id="ev-time" name="event_time"
                                value="<?php echo ev_e($formValues['event_time']); ?>">
                            <?php if (isset($formErrors['event_time'])): ?>
                                <span class="field-error" role="alert"><?php echo ev_e($formErrors['event_time']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group<?php echo isset($formErrors['venue']) ? ' invalid' : ''; ?>">
                            <label for="ev-venue">Venue <span class="required" aria-hidden="true">*</span></label>
                            <input type="text" id="ev-venue" name="venue" maxlength="150"
                                placeholder="Event venue" value="<?php echo ev_e($formValues['venue']); ?>" required>
                            <?php if (isset($formErrors['venue'])): ?>
                                <span class="field-error" role="alert"><?php echo ev_e($formErrors['venue']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group<?php echo isset($formErrors['status']) ? ' invalid' : ''; ?>">
                            <label for="ev-status">Status <span class="required" aria-hidden="true">*</span></label>
                            <select id="ev-status" name="status" required>
                                <?php foreach (EVENT_STATUSES as $st): ?>
                                    <option value="<?php echo ev_e($st); ?>"<?php echo $formValues['status'] === $st ? ' selected' : ''; ?>>
                                        <?php echo ev_e(ucfirst($st)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($formErrors['status'])): ?>
                                <span class="field-error" role="alert"><?php echo ev_e($formErrors['status']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group<?php echo isset($formErrors['poster']) ? ' invalid' : ''; ?>">
                            <label for="ev-poster">
                                Event Poster
                                <?php if ($editId !== null): ?>
                                    <small>(leave empty to keep current poster)</small>
                                <?php else: ?>
                                    <span class="required" aria-hidden="true">*</span>
                                <?php endif; ?>
                            </label>
                            <input type="file" id="ev-poster" name="poster"
                                accept="image/jpeg,image/png,image/webp">
                            <p class="field-hint">JPEG, PNG or WebP. Maximum 2 MB.</p>
                            <?php if ($editId !== null && !empty($formValues['poster'])): ?>
                                <p class="field-hint">
                                    Current: <code><?php echo ev_e(basename($formValues['poster'])); ?></code>
                                </p>
                            <?php endif; ?>
                            <?php if (isset($formErrors['poster'])): ?>
                                <span class="field-error" role="alert"><?php echo ev_e($formErrors['poster']); ?></span>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn register-submit">
                            <?php echo $editId !== null ? 'Save Changes' : 'Add Event'; ?>
                        </button>
                        <?php if ($editId !== null): ?>
                            <p class="register-login">
                                <a href="event-manage.php">Cancel editing</a>
                            </p>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Search + filters -->
                <form method="get" action="event-manage.php" class="data-controls">
                    <div class="data-control">
                        <label for="ev-q">Search events</label>
                        <input type="search" id="ev-q" name="q" autocomplete="off"
                            placeholder="Title, description or venue" value="<?php echo ev_e($search); ?>">
                    </div>
                    <div class="data-control">
                        <label for="ev-fcat">Category</label>
                        <select id="ev-fcat" name="category">
                            <option value="">All categories</option>
                            <?php foreach (EVENT_CATEGORIES as $cat): ?>
                                <option value="<?php echo ev_e($cat); ?>"<?php echo $filterCategory === $cat ? ' selected' : ''; ?>>
                                    <?php echo ev_e($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="data-control">
                        <label for="ev-fstatus">Status</label>
                        <select id="ev-fstatus" name="status">
                            <option value="">All statuses</option>
                            <?php foreach (EVENT_STATUSES as $st): ?>
                                <option value="<?php echo ev_e($st); ?>"<?php echo $filterStatus === $st ? ' selected' : ''; ?>>
                                    <?php echo ev_e(ucfirst($st)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="data-control">
                        <button type="submit" class="btn">Search</button>
                    </div>
                    <div class="data-control">
                        <a href="event-manage.php" class="btn btn-outline">Reset</a>
                    </div>
                </form>

                <p class="data-count" aria-live="polite">
                    <?php if ($listError): ?>
                        Event list is unavailable right now. Please try again later.
                    <?php elseif ($total === 0): ?>
                        No events found. Try different search or filter values.
                    <?php else: ?>
                        Total events: <?php echo $total; ?>.
                        Showing <?php echo $from; ?>&ndash;<?php echo $to; ?>.
                    <?php endif; ?>
                </p>

                <!-- Events grid -->
                <div class="faculty-event-grid">
                    <?php foreach ($events as $row): ?>
                        <article class="faculty-event-card">
                            <div class="event-card-header">
                                <span class="event-category"><?php echo ev_e($row['category']); ?></span>
                                <span class="event-status event-status-<?php echo ev_e($row['status']); ?>">
                                    <?php echo ev_e(ucfirst($row['status'])); ?>
                                </span>
                            </div>
                            <?php if (!empty($row['poster'])): ?>
                                <img class="event-poster"
                                    src="../uploads/event_posters/<?php echo ev_e(basename($row['poster'])); ?>"
                                    alt="<?php echo ev_e($row['title']); ?> poster"
                                    loading="lazy"
                                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                <div class="event-poster-placeholder" style="display:none;">
                                    <span>No poster</span>
                                </div>
                            <?php else: ?>
                                <div class="event-poster-placeholder">
                                    <span>No poster</span>
                                </div>
                            <?php endif; ?>
                            <h3><?php echo ev_e($row['title']); ?></h3>
                            <p><?php echo ev_e(mb_strimwidth((string) $row['description'], 0, 120, '…')); ?></p>
                            <div class="event-details">
                                <p><strong>Date:</strong> <?php echo ev_e($row['event_date']); ?></p>
                                <?php if (!empty($row['event_time'])): ?>
                                    <p><strong>Time:</strong> <?php echo ev_e($row['event_time']); ?></p>
                                <?php endif; ?>
                                <p><strong>Venue:</strong> <?php echo ev_e($row['venue']); ?></p>
                            </div>
                            <div class="event-actions">
                                <a class="btn btn-outline btn-small"
                                   href="event-manage.php?edit=<?php echo (int) $row['event_id']; ?>">Edit</a>
                                <form method="post" action="event-manage.php" class="inline-form"
                                    onsubmit="return confirm('Delete this event? This cannot be undone.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int) $row['event_id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    <?php if (empty($events) && !$listError): ?>
                        <p class="data-empty" role="status">No events to display.</p>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a class="btn btn-outline"
                           href="<?php echo ev_e(ev_page_url($page - 1, $search, $filterCategory, $filterStatus)); ?>">Previous</a>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline" disabled>Previous</button>
                    <?php endif; ?>
                    <span class="page-info" aria-live="polite">
                        Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                    </span>
                    <?php if ($page < $totalPages): ?>
                        <a class="btn btn-outline"
                           href="<?php echo ev_e(ev_page_url($page + 1, $search, $filterCategory, $filterStatus)); ?>">Next</a>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline" disabled>Next</button>
                    <?php endif; ?>
                </div>

                <p class="section-link">
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
                <a href="event-manage.php" aria-current="page">Manage Events</a>
                <a href="students.html">Students</a>
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