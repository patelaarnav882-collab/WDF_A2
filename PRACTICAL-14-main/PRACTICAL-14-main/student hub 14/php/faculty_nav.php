<?php
/* =====================================================
   STUDENTHUB - SHARED FACULTY / ADMIN NAVIGATION
   (Practical 14: dynamic menu based on user role.)

   Included by every protected faculty/admin PHP page so
   the menu never drifts apart between pages.

   Usage (after the access guard, so $user exists):
       $navPage = 'dashboard' | 'profile' | 'students'
                | 'student-manage' | 'students-ajax' | 'events'
                | 'admin-dashboard' | 'audit-log';
       $navRole = $user['role'] ?? 'faculty';
       include __DIR__ . '/../php/faculty_nav.php';

   - Admins see: Admin Dashboard, Students dropdown,
     Events, Registrations, Audit Log, Profile, Logout.
   - Faculty see the original menu (no admin-only links).
   - Hiding a link is display only: every target page
     still enforces its own server-side role guard.
   ===================================================== */

if (!defined('FACULTY_NAV_ACTIVE')) {
    define('FACULTY_NAV_ACTIVE', $navPage ?? 'dashboard');
}

if (!isset($navRole) || !is_string($navRole)) {
    $navRole = 'faculty';
    $sessionUser = current_user();
    if (is_array($sessionUser) && isset($sessionUser['role'])) {
        $navRole = (string) $sessionUser['role'];
    }
}
if (!defined('FACULTY_NAV_ROLE')) {
    define('FACULTY_NAV_ROLE', $navRole);
}

// Renders aria-current only on the item that is really open.
if (!function_exists('nav_cur')) {
    function nav_cur(string $key): string
    {
        return FACULTY_NAV_ACTIVE === $key ? ' aria-current="page"' : '';
    }
}

// Parent stays underlined when any child page is open.
if (!function_exists('nav_parent_active')) {
    function nav_parent_active(string $key): string
    {
        return FACULTY_NAV_ACTIVE === $key ? ' has-active-child' : '';
    }
}

$isAdmin = FACULTY_NAV_ROLE === 'admin';
$studentsOpen = in_array(
    FACULTY_NAV_ACTIVE,
    ['student-manage', 'students-ajax', 'students'],
    true
);
?>
<nav id="mainNav" aria-label="<?php echo $isAdmin ? 'Admin' : 'Faculty'; ?> navigation">
    <ul class="nav-list">
        <?php if ($isAdmin): ?>
        <li>
            <a href="admin-dashboard.php"<?php echo nav_cur('admin-dashboard'); ?>>
                Admin Dashboard
            </a>
        </li>
        <?php else: ?>
        <li>
            <a href="faculty-dashboard.php"<?php echo nav_cur('dashboard'); ?>>
                Dashboard
            </a>
        </li>
        <li>
            <a href="faculty-profile.html"<?php echo nav_cur('profile'); ?>>
                Profile
            </a>
        </li>
        <?php endif; ?>
        <li class="nav-item-drop<?php echo $studentsOpen ? ' has-active-child' : ''; ?>">
            <a href="students.html" class="nav-drop-link">
                Students
                <span class="nav-drop-caret" aria-hidden="true">&#9662;</span>
            </a>
            <ul class="nav-submenu">
                <li>
                    <a href="student-manage.php"<?php echo nav_cur('student-manage'); ?>>
                        Student Manage (P11)
                    </a>
                </li>
                <li>
                    <a href="students-ajax.php"<?php echo nav_cur('students-ajax'); ?>>
                        Live List (P13)
                    </a>
                </li>
                <li>
                    <a href="students.html"<?php echo nav_cur('students'); ?>>
                        Public List (P6)
                    </a>
                </li>
                <li>
                    <span class="submenu-note">Practical 13 = no page reload</span>
                </li>
            </ul>
        </li>
        <li>
            <a href="event-manage.php"<?php echo nav_cur('events'); ?>>
                Events
            </a>
        </li>
        <?php if ($isAdmin): ?>
        <li>
            <a href="admin-dashboard.php#latest-registrations"<?php echo nav_cur('registrations'); ?>>
                Registrations
            </a>
        </li>
        <li>
            <a href="audit-log.php"<?php echo nav_cur('audit-log'); ?>>
                Audit Log
            </a>
        </li>
        <li>
            <a href="faculty-profile.html"<?php echo nav_cur('profile'); ?>>
                Profile
            </a>
        </li>
        <?php endif; ?>
        <li>
            <a href="../php/logout.php">
                Logout
            </a>
        </li>
    </ul>
</nav>
