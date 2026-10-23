<?php
/* =====================================================
   STUDENTHUB - SHARED FACULTY NAVIGATION
   Included by every faculty page so the menu never drifts
   apart between pages.

   Usage:
       $navPage = 'dashboard' | 'profile' | 'students'
                | 'student-manage' | 'students-ajax' | 'events';
       include __DIR__ . '/../php/faculty_nav.php';
   ===================================================== */

if (!defined('FACULTY_NAV_ACTIVE')) {
    define('FACULTY_NAV_ACTIVE', $navPage ?? 'dashboard');
}

// Renders aria-current only on the item that is really open.
function nav_cur(string $key): string
{
    return FACULTY_NAV_ACTIVE === $key ? ' aria-current="page"' : '';
}

// Parent gets a class so it stays underlined when a child is open.
function nav_parent_active(string $key): string
{
    return FACULTY_NAV_ACTIVE === $key ? ' has-active-child' : '';
}
?>
<nav id="mainNav" aria-label="Faculty navigation">
    <ul class="nav-list">
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
        <li class="nav-item-drop<?php echo nav_parent_active('student-manage');
            echo nav_parent_active('students-ajax'); ?>">
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
        <li>
            <a href="../php/logout.php">
                Logout
            </a>
        </li>
    </ul>
</nav>
