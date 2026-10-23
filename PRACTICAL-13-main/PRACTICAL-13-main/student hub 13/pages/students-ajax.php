<?php
/* Practical 13: student list without page reloads.
   Same data and guards as student-manage.php (P11);
   all reads/writes go through api/students.php via Fetch. */
declare(strict_types=1);
require_once __DIR__ . '/../php/auth.php';
$user = require_role('faculty', 'faculty-login.html');
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="StudentHub AJAX student management">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Student Management (Live) | StudentHub</title>
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
                            <li><a href="student-manage.php">Student Manage (P11)</a></li>
                            <li><a href="students-ajax.php" aria-current="page">Live List (P13)</a></li>
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
                <p class="eyebrow">FACULTY PORTAL &middot; PRACTICAL 13</p>
                <h2>Student Management (Live)</h2>
                <p>
                    Same records as Student Management, updated
                    without reloading the page. Signed in as
                    <?php echo htmlspecialchars($user['name'] ?? 'Faculty', ENT_QUOTES, 'UTF-8'); ?>.
                </p>
            </div>
        </section>

        <section class="students-list-section">
            <div class="container">

                <p class="data-status" id="ajaxStatus" role="status" hidden></p>

                <div class="data-controls">
                    <div class="data-control">
                        <label for="ajaxSearch">Search students</label>
                        <input type="search" id="ajaxSearch" autocomplete="off"
                            placeholder="Name, email, course or enrollment ID">
                    </div>
                    <div class="data-control">
                        <button type="button" class="btn" id="ajaxAdd">Add Student</button>
                    </div>
                    <div class="data-control">
                        <a href="student-manage.php" class="btn btn-outline">Classic version</a>
                    </div>
                </div>

                <p class="data-count" id="ajaxCount" aria-live="polite"></p>

                <div class="student-table-wrapper">
                    <table class="student-table">
                        <caption>Student records (loaded via API)</caption>
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
                        <tbody id="ajaxRows"></tbody>
                    </table>
                </div>

                <p class="data-empty" id="ajaxEmpty" role="status" hidden>
                    No students found. Try a different search.
                </p>

                <div class="pagination">
                    <button type="button" class="btn btn-outline" id="ajaxPrev">Previous</button>
                    <span class="page-info" id="ajaxPageInfo" aria-live="polite">Page 1 of 1</span>
                    <button type="button" class="btn btn-outline" id="ajaxNext">Next</button>
                </div>

                <p class="section-link">
                    <a href="faculty-dashboard.php" class="btn btn-outline">Back to Dashboard</a>
                </p>

            </div>
        </section>
    </main>

    <!-- Add / Edit dialog (no page reload) -->
    <div class="modal" id="studentModal" hidden>
        <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="studentModalTitle">
            <button class="modal-close modal-close-icon" type="button" id="studentModalX" aria-label="Close student form">&times;</button>
            <h2 id="studentModalTitle">Add Student</h2>
            <p class="data-status" id="ajaxFormStatus" role="alert" hidden></p>
            <form id="studentForm" novalidate>
                <input type="hidden" id="f-id">
                <div class="form-group">
                    <label for="f-enrollment">Enrollment ID <span class="required" aria-hidden="true">*</span></label>
                    <input type="text" id="f-enrollment" maxlength="20" required>
                    <span class="field-error" id="e-enrollment_id" role="alert" hidden></span>
                </div>
                <div class="form-group">
                    <label for="f-name">Full Name <span class="required" aria-hidden="true">*</span></label>
                    <input type="text" id="f-name" maxlength="100" required>
                    <span class="field-error" id="e-full_name" role="alert" hidden></span>
                </div>
                <div class="form-group">
                    <label for="f-email">Email <span class="required" aria-hidden="true">*</span></label>
                    <input type="email" id="f-email" maxlength="150" required>
                    <span class="field-error" id="e-email" role="alert" hidden></span>
                </div>
                <div class="form-group">
                    <label for="f-phone">Mobile <span class="required" aria-hidden="true">*</span></label>
                    <input type="tel" id="f-phone" maxlength="15" inputmode="numeric" required>
                    <span class="field-error" id="e-phone" role="alert" hidden></span>
                </div>
                <div class="form-group">
                    <label for="f-course">Course <span class="required" aria-hidden="true">*</span></label>
                    <select id="f-course" required>
                        <option value="">Select course</option>
                        <option value="btech">B.Tech</option>
                        <option value="bca">BCA</option>
                        <option value="bba">BBA</option>
                        <option value="bcom">B.Com</option>
                        <option value="bsc">B.Sc</option>
                        <option value="other">Other</option>
                    </select>
                    <span class="field-error" id="e-course" role="alert" hidden></span>
                </div>
                <div class="form-group">
                    <label for="f-year">Academic Year <span class="required" aria-hidden="true">*</span></label>
                    <select id="f-year" required>
                        <option value="">Select year</option>
                        <option value="1">Year 1</option>
                        <option value="2">Year 2</option>
                        <option value="3">Year 3</option>
                        <option value="4">Year 4</option>
                    </select>
                    <span class="field-error" id="e-year" role="alert" hidden></span>
                </div>
                <fieldset class="form-group gender-group">
                    <legend>Gender <span class="required" aria-hidden="true">*</span></legend>
                    <div class="gender-options">
                        <label><input type="radio" name="f-gender" value="male"> Male</label>
                        <label><input type="radio" name="f-gender" value="female"> Female</label>
                        <label><input type="radio" name="f-gender" value="other"> Other</label>
                    </div>
                    <span class="field-error" id="e-gender" role="alert" hidden></span>
                </fieldset>
                <button type="submit" class="btn register-submit" id="studentSubmit">Add Student</button>
            </form>
        </div>
    </div>

    <footer class="site-footer">
        <div class="container footer-content">
            <div class="footer-column">
                <h3>StudentHub</h3>
                <p>Your campus. Your community. Your hub.</p>
            </div>
            <div class="footer-column">
                <h3>Faculty</h3>
                <a href="faculty-dashboard.php">Dashboard</a>
                <a href="student-manage.php">Manage Students</a>
                <a href="students-ajax.php" aria-current="page">Live List</a>
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
    <script src="../js/students.js"></script>
</body>
</html>
