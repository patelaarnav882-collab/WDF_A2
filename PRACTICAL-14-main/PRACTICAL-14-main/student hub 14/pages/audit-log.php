<?php
/* =====================================================
   STUDENTHUB - PRACTICAL 14
   Audit Log viewer: every admin/CRUD action recorded by
   php/audit_logger.php, newest first. Admin-only.

   Filters (all optional, all GET):
     action = one of the known action names below
     from   = YYYY-MM-DD (inclusive start)
     to     = YYYY-MM-DD (inclusive end)
     page   = page number
   Every filter is validated, then bound with a prepared
   statement. All output is escaped with htmlspecialchars.
   ===================================================== */
declare(strict_types=1);

require_once __DIR__ . '/../php/auth.php';
require_once __DIR__ . '/../php/db_mysqli.php';

$user = require_admin('faculty-login.html');

function al_e(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

// The only action names the logger ever writes. Anything
// else in ?action= is ignored (never put in SQL raw).
const AUDIT_ACTIONS = [
    'admin_login',
    'student_add', 'student_update', 'student_delete',
    'event_add', 'event_update', 'event_delete',
];
const AUDIT_PER_PAGE = 15;

function al_get(string $key): string
{
    if (!isset($_GET[$key]) || is_array($_GET[$key])) {
        return '';
    }
    return trim((string) $_GET[$key]);
}

function al_valid_date(string $v): bool
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
        return false;
    }
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

$filterAction = al_get('action');
if (!in_array($filterAction, AUDIT_ACTIONS, true)) {
    $filterAction = '';
}
$filterFrom = al_get('from');
if (!al_valid_date($filterFrom)) {
    $filterFrom = '';
}
$filterTo = al_get('to');
if (!al_valid_date($filterTo)) {
    $filterTo = '';
}
$page = max(1, (int) al_get('page'));

$dbError = null;
$total = 0;
$totalPages = 1;
$rows = [];

try {
    $db = crud_db();

    $where = [];
    $types = '';
    $params = [];
    if ($filterAction !== '') {
        $where[] = 'action = ?';
        $types .= 's';
        $params[] = $filterAction;
    }
    if ($filterFrom !== '') {
        $where[] = 'created_at >= ?';
        $types .= 's';
        $params[] = $filterFrom . ' 00:00:00';
    }
    if ($filterTo !== '') {
        $where[] = 'created_at <= ?';
        $types .= 's';
        $params[] = $filterTo . ' 23:59:59';
    }
    $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $db->prepare("SELECT COUNT(*) AS c FROM audit_logs $whereSql");
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

    $totalPages = max(1, (int) ceil($total / AUDIT_PER_PAGE));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * AUDIT_PER_PAGE;

    $listTypes = $types . 'ii';
    $listParams = array_merge($params, [$offset, AUDIT_PER_PAGE]);
    $listStmt = $db->prepare(
        "SELECT id, user_id, user_name, user_role, action,
                entity_type, entity_id, details, ip_address, created_at
         FROM audit_logs $whereSql
         ORDER BY created_at DESC, id DESC
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
} catch (Throwable $e) {
    error_log('StudentHub audit viewer failed.');
    $dbError = 'Database is unavailable right now. Please check MySQL in XAMPP and reload.';
}

// Keeps the current filters while turning pages.
function al_page_url(int $page, string $action, string $from, string $to): string
{
    $q = ['page' => $page];
    if ($action !== '') {
        $q['action'] = $action;
    }
    if ($from !== '') {
        $q['from'] = $from;
    }
    if ($to !== '') {
        $q['to'] = $to;
    }
    return 'audit-log.php?' . http_build_query($q);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="StudentHub audit log">
    <title>Audit Log | StudentHub</title>
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
            $navPage = 'audit-log';
            $navRole = $user['role'] ?? 'admin';
            include __DIR__ . '/../php/faculty_nav.php';
            ?>
        </div>
    </header>
    <main id="main">
        <section class="students-hero">
            <div class="container">
                <p class="eyebrow">ADMIN PORTAL &middot; PRACTICAL 14</p>
                <h2>Audit Log</h2>
                <p>
                    Who did what, and when. Newest first.
                    Signed in as <?php echo al_e($user['name'] ?? 'Admin'); ?>.
                </p>
            </div>
        </section>

        <section class="students-list-section">
            <div class="container">
                <?php if ($dbError !== null): ?>
                    <p class="data-status data-error" role="alert"><?php echo al_e($dbError); ?></p>
                <?php else: ?>
                <form method="get" action="audit-log.php" class="data-controls">
                    <div class="data-control">
                        <label for="al-action">Action</label>
                        <select id="al-action" name="action">
                            <option value="">All actions</option>
                            <?php foreach (AUDIT_ACTIONS as $a): ?>
                            <option value="<?php echo $a; ?>"<?php echo $filterAction === $a ? ' selected' : ''; ?>>
                                <?php echo $a; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="data-control">
                        <label for="al-from">From</label>
                        <input type="date" id="al-from" name="from" value="<?php echo al_e($filterFrom); ?>">
                    </div>
                    <div class="data-control">
                        <label for="al-to">To</label>
                        <input type="date" id="al-to" name="to" value="<?php echo al_e($filterTo); ?>">
                    </div>
                    <div class="data-control">
                        <button type="submit" class="btn">Filter</button>
                    </div>
                    <div class="data-control">
                        <a href="audit-log.php" class="btn btn-outline">Clear</a>
                    </div>
                </form>

                <p class="data-count" aria-live="polite">
                    <?php if ($total === 0): ?>
                        No audit records found. Try clearing the filters.
                    <?php else: ?>
                        Total entries: <?php echo $total; ?>.
                        Showing page <?php echo $page; ?> of <?php echo $totalPages; ?>.
                    <?php endif; ?>
                </p>

                <?php if (!empty($rows)): ?>
                <div class="student-table-wrapper">
                    <table class="student-table">
                        <caption>Audit log entries, newest first</caption>
                        <thead>
                            <tr>
                                <th scope="col">Timestamp</th>
                                <th scope="col">User</th>
                                <th scope="col">Action</th>
                                <th scope="col">Entity</th>
                                <th scope="col">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?php echo al_e($row['created_at']); ?></td>
                                <td>
                                    <?php echo al_e($row['user_name'] ?? '—'); ?>
                                    <?php if (!empty($row['user_role'])): ?>
                                        <br><small><?php echo al_e($row['user_role']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo al_e($row['action']); ?></td>
                                <td>
                                    <?php
                                    echo $row['entity_type'] !== null && $row['entity_type'] !== ''
                                        ? al_e($row['entity_type']) . ' #' . (int) $row['entity_id']
                                        : '—';
                                    ?>
                                </td>
                                <td>
                                    <?php echo al_e($row['details'] ?? '—'); ?>
                                    <?php if (!empty($row['ip_address'])): ?>
                                        <br><small><?php echo al_e($row['ip_address']); ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a class="btn btn-outline" href="<?php echo al_e(al_page_url($page - 1, $filterAction, $filterFrom, $filterTo)); ?>">Previous</a>
                    <?php else: ?>
                    <button type="button" class="btn btn-outline" disabled>Previous</button>
                    <?php endif; ?>
                    <span class="page-info" aria-live="polite">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                    <?php if ($page < $totalPages): ?>
                    <a class="btn btn-outline" href="<?php echo al_e(al_page_url($page + 1, $filterAction, $filterFrom, $filterTo)); ?>">Next</a>
                    <?php else: ?>
                    <button type="button" class="btn btn-outline" disabled>Next</button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <p class="section-link">
                    <a href="admin-dashboard.php" class="btn btn-outline">Back to Admin Dashboard</a>
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
                <h3>Admin</h3>
                <a href="admin-dashboard.php">Admin Dashboard</a>
                <a href="audit-log.php" aria-current="page">Audit Log</a>
                <a href="student-manage.php">Manage Students</a>
                <a href="event-manage.php">Manage Events</a>
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
