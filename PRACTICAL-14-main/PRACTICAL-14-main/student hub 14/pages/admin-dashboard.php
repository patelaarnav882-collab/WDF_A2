<?php
/* =====================================================
   STUDENTHUB - PRACTICAL 14
   Admin Dashboard: live counts (users, students, events,
   registrations), latest registrations and recent audit
   activity. Admin-only: students and faculty are bounced
   to their own dashboards by require_admin().

   NOTE: this project has no separate "users" table. The
   students table IS the users table (it stores the login,
   password_hash and role), so "total users" counts every
   row while "total students" counts role = 'student'.
   Nothing here is hardcoded: every number comes from
   COUNT() queries run on each page load.
   ===================================================== */
declare(strict_types=1);

require_once __DIR__ . '/../php/auth.php';
require_once __DIR__ . '/../php/db_mysqli.php';

$user = require_admin('faculty-login.html');

function adm_e(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

// Defaults shown only when the database cannot be reached.
// Cards render "—" instead of a misleading zero.
$dbError = null;
$stats = [
    'users' => null, 'students' => null,
    'events' => null, 'registrations' => null,
];
$roleBreakdown = [];
$latestRegs = [];
$recentAudit = [];

try {
    $db = crud_db();

    // One COUNT() per card. No user input, so no parameters.
    $stats['users'] = (int) $db->query(
        'SELECT COUNT(*) AS c FROM students'
    )->fetch_assoc()['c'];
    $res = $db->query(
        "SELECT COUNT(*) AS c FROM students WHERE role = 'student'"
    );
    $stats['students'] = (int) $res->fetch_assoc()['c'];
    $stats['events'] = (int) $db->query(
        'SELECT COUNT(*) AS c FROM events'
    )->fetch_assoc()['c'];
    $stats['registrations'] = (int) $db->query(
        'SELECT COUNT(*) AS c FROM registrations'
    )->fetch_assoc()['c'];

    // Honest breakdown of the "users" total by role.
    $break = $db->query(
        'SELECT role, COUNT(*) AS c FROM students GROUP BY role'
    );
    while ($row = $break->fetch_assoc()) {
        $roleBreakdown[(string) $row['role']] = (int) $row['c'];
    }

    // Latest registrations: JOINs bring the student name and
    // the event title in one query, newest first.
    $regs = $db->query(
        'SELECT r.registration_id, r.registration_date, r.status,
                s.full_name AS student_name, s.email AS student_email,
                e.title AS event_title
         FROM registrations AS r
         JOIN students AS s ON s.student_id = r.student_id
         JOIN events AS e ON e.event_id = r.event_id
         ORDER BY r.registration_date DESC, r.registration_id DESC
         LIMIT 8'
    );
    $latestRegs = $regs->fetch_all(MYSQLI_ASSOC);

    // Short preview; the full viewer lives in audit-log.php.
    $audit = $db->query(
        'SELECT user_name, action, entity_type, entity_id,
                details, created_at
         FROM audit_logs
         ORDER BY created_at DESC, id DESC
         LIMIT 5'
    );
    $recentAudit = $audit->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $e) {
    error_log('StudentHub admin dashboard failed.');
    $dbError = 'Database is unavailable right now. Numbers are hidden '
        . 'instead of guessed. Please check MySQL in XAMPP and reload.';
}

function adm_stat($v): string
{
    return $v === null ? '—' : (string) $v;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="StudentHub admin dashboard">
    <title>Admin Dashboard | StudentHub</title>
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
            $navPage = 'admin-dashboard';
            $navRole = $user['role'] ?? 'admin';
            include __DIR__ . '/../php/faculty_nav.php';
            ?>
        </div>
    </header>
    <main id="main">
        <section class="dashboard-hero faculty-dashboard-hero">
            <div class="container">
                <p class="eyebrow">ADMIN PORTAL &middot; PRACTICAL 14</p>
                <h2>Admin Dashboard</h2>
                <p>
                    Welcome Back, <?php echo adm_e($user['name'] ?? 'Admin'); ?>!
                    Live totals from MySQL, newest registrations and
                    recent admin activity.
                </p>
            </div>
        </section>

        <?php if ($dbError !== null): ?>
        <div class="container">
            <p class="data-status data-error" role="alert"><?php echo adm_e($dbError); ?></p>
        </div>
        <?php endif; ?>

        <section class="dashboard-section dashboard-section-alt">
            <div class="container">
                <div class="section-heading">
                    <p class="eyebrow">OVERVIEW</p>
                    <h2>Dashboard Statistics</h2>
                    <p>Every number is a live <code>COUNT()</code> query.</p>
                </div>
                <div class="faculty-stat-grid">
                    <article class="faculty-stat-card">
                        <span class="faculty-stat-icon" aria-hidden="true">👥</span>
                        <strong><?php echo adm_stat($stats['users']); ?></strong>
                        <h3>Total Users</h3>
                        <p>
                            <?php if (!empty($roleBreakdown)): ?>
                                <?php echo adm_e(
                                    (string) ($roleBreakdown['student'] ?? 0)
                                ); ?> students &middot;
                                <?php echo adm_e(
                                    (string) ($roleBreakdown['faculty'] ?? 0)
                                ); ?> faculty &middot;
                                <?php echo adm_e(
                                    (string) ($roleBreakdown['admin'] ?? 0)
                                ); ?> admins
                            <?php else: ?>
                                Every account in the students table
                            <?php endif; ?>
                        </p>
                    </article>
                    <article class="faculty-stat-card">
                        <span class="faculty-stat-icon" aria-hidden="true">👨‍🎓</span>
                        <strong><?php echo adm_stat($stats['students']); ?></strong>
                        <h3>Total Students</h3>
                        <p>Accounts with role = student</p>
                    </article>
                    <article class="faculty-stat-card">
                        <span class="faculty-stat-icon" aria-hidden="true">📅</span>
                        <strong><?php echo adm_stat($stats['events']); ?></strong>
                        <h3>Total Events</h3>
                        <p>Rows in the events table</p>
                    </article>
                    <article class="faculty-stat-card">
                        <span class="faculty-stat-icon" aria-hidden="true">📝</span>
                        <strong><?php echo adm_stat($stats['registrations']); ?></strong>
                        <h3>Total Registrations</h3>
                        <p>Student-to-event signups</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="dashboard-section" id="latest-registrations">
            <div class="container">
                <div class="section-heading">
                    <p class="eyebrow">SIGNUPS</p>
                    <h2>Latest Registrations</h2>
                    <p>Newest first, with student and event names joined in.</p>
                </div>
                <?php if ($dbError !== null): ?>
                    <p class="data-status" role="status">Registrations are hidden while the database is unreachable.</p>
                <?php elseif (empty($latestRegs)): ?>
                    <p class="data-status" role="status">No registrations yet. New event signups will appear here.</p>
                <?php else: ?>
                <div class="student-table-wrapper">
                    <table class="student-table">
                        <caption>Most recent event registrations</caption>
                        <thead>
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Student</th>
                                <th scope="col">Event</th>
                                <th scope="col">Date</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($latestRegs as $reg): ?>
                            <tr>
                                <td><?php echo (int) $reg['registration_id']; ?></td>
                                <td>
                                    <?php echo adm_e($reg['student_name']); ?><br>
                                    <small><?php echo adm_e($reg['student_email']); ?></small>
                                </td>
                                <td><?php echo adm_e($reg['event_title']); ?></td>
                                <td><?php echo adm_e($reg['registration_date']); ?></td>
                                <td><?php echo adm_e($reg['status']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="dashboard-section dashboard-section-alt">
            <div class="container">
                <div class="section-heading">
                    <p class="eyebrow">ACCOUNTABILITY</p>
                    <h2>Recent Admin Activity</h2>
                    <p>Newest audit entries. <a href="audit-log.php" class="text-link">Open the full Audit Log →</a></p>
                </div>
                <?php if ($dbError !== null): ?>
                    <p class="data-status" role="status">Audit preview is hidden while the database is unreachable.</p>
                <?php elseif (empty($recentAudit)): ?>
                    <p class="data-status" role="status">No audit records yet. Add, edit or delete a student or event and it will be logged here.</p>
                <?php else: ?>
                <div class="student-table-wrapper">
                    <table class="student-table">
                        <caption>Five most recent audit log entries</caption>
                        <thead>
                            <tr>
                                <th scope="col">When</th>
                                <th scope="col">Who</th>
                                <th scope="col">Action</th>
                                <th scope="col">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentAudit as $row): ?>
                            <tr>
                                <td><?php echo adm_e($row['created_at']); ?></td>
                                <td><?php echo adm_e($row['user_name'] ?? '—'); ?></td>
                                <td><?php echo adm_e($row['action']); ?></td>
                                <td><?php echo adm_e($row['details'] ?? '—'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="dashboard-section">
            <div class="container">
                <div class="section-heading">
                    <p class="eyebrow">QUICK ACCESS</p>
                    <h2>Admin Quick Links</h2>
                </div>
                <div class="quick-links">
                    <a href="student-manage.php" class="quick-link">
                        <span>📝</span>
                        <strong>Student Management</strong>
                        <small>Add, edit and remove records</small>
                    </a>
                    <a href="students-ajax.php" class="quick-link">
                        <span>⚡</span>
                        <strong>Live Student List</strong>
                        <small>No-reload AJAX version</small>
                    </a>
                    <a href="event-manage.php" class="quick-link">
                        <span>📅</span>
                        <strong>Event Management</strong>
                        <small>Create and manage events</small>
                    </a>
                    <a href="audit-log.php" class="quick-link">
                        <span>🧾</span>
                        <strong>Audit Log</strong>
                        <small>Who changed what, and when</small>
                    </a>
                </div>
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
                <a href="admin-dashboard.php" aria-current="page">Admin Dashboard</a>
                <a href="audit-log.php">Audit Log</a>
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
