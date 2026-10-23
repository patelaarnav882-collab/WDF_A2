<?php
/* =====================================================
   STUDENTHUB - PRACTICAL 8
   Database connection demonstration page.

   Open through XAMPP (never file://), for example:
       http://localhost/studenthub/php/database_test.php
   (folder name depends on where you copied the project
   inside C:\xampp\htdocs -- adjust as needed.)

   Shows a clear success message, counts the rows in
   each table, and runs one small prepared-statement
   lookup. Failures show a safe message only.
   ===================================================== */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

$connected = false;
$problem = '';
$counts = ['students' => 0, 'events' => 0, 'registrations' => 0];
$demoCategory = 'Technical';
$demoEvents = [];

try {
    $pdo = getDbConnection();

    // Harmless check that queries actually run.
    $pdo->query('SELECT 1');
    $connected = true;

    foreach (array_keys($counts) as $table) {
        // Table names are fixed by us, never user input.
        $counts[$table] = (int) $pdo->query(
            "SELECT COUNT(*) AS c FROM `$table`"
        )->fetch()['c'];
    }

    // ---- Prepared-statement example ----
    // The category value is BOUND, never concatenated into
    // the SQL string, so hostile input cannot change the
    // query (this is what stops SQL injection).
    $stmt = $pdo->prepare(
        'SELECT title, event_date, location
         FROM events
         WHERE category = :category
         ORDER BY event_date ASC
         LIMIT 5'
    );
    $stmt->execute([':category' => $demoCategory]);
    $demoEvents = $stmt->fetchAll();
} catch (Throwable $e) {
    // $e->getMessage() from db.php is already visitor-safe.
    $problem = $e->getMessage();
    $connected = false;
}

function e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Test | StudentHub</title>
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
            <nav id="mainNav" aria-label="Main navigation">
                <ul class="nav-list">
                    <li><a href="../pages/index.html">Home</a></li>
                    <li><a href="../pages/about.html">About</a></li>
                    <li><a href="../pages/events.html">Events</a></li>
                    <li><a href="../pages/faq.html">FAQ</a></li>
                    <li><a href="../pages/contact.html">Contact</a></li>
                    <li><a href="../pages/login.html">Login</a></li>
                    <li><a href="../pages/register.html">Register</a></li>
                </ul>
            </nav>
        </div>
    </header>
    <main id="main">
        <section class="section">
            <div class="container">
                <p class="eyebrow">PRACTICAL 8</p>
                <h2>Database Connection Test</h2>
                <?php if ($connected): ?>
                    <div class="form-success" role="status">
                        Connected to the <strong>studenthub_db</strong>
                        database successfully, and test queries ran.
                    </div>
                    <div class="card-grid">
                        <article class="card">
                            <h3>Students</h3>
                            <p><strong><?php echo $counts['students']; ?></strong> records</p>
                        </article>
                        <article class="card">
                            <h3>Events</h3>
                            <p><strong><?php echo $counts['events']; ?></strong> records</p>
                        </article>
                        <article class="card">
                            <h3>Registrations</h3>
                            <p><strong><?php echo $counts['registrations']; ?></strong> records</p>
                        </article>
                    </div>
                    <div class="register-form-card" style="margin-top: 28px;">
                        <h2>Prepared-statement demo</h2>
                        <p>
                            Safely listing up to 5 events in category
                            <strong><?php echo e($demoCategory); ?></strong>
                            (value passed by binding, not by joining strings):
                        </p>
                        <?php if ($demoEvents): ?>
                            <ul>
                                <?php foreach ($demoEvents as $row): ?>
                                    <li>
                                        <strong><?php echo e($row['title']); ?></strong>
                                        &mdash; <?php echo e($row['event_date']); ?>,
                                        <?php echo e($row['location']); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p>No events found in this category.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="register-form-card" role="alert">
                        <h2>Connection failed</h2>
                        <p><?php echo e($problem); ?></p>
                        <p>
                            Checklist: start <strong>MySQL</strong> in the XAMPP
                            Control Panel, then import
                            <strong>database/studenthub_db.sql</strong> in
                            phpMyAdmin (<strong>http://localhost/phpmyadmin/</strong>).
                        </p>
                    </div>
                <?php endif; ?>
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
                <h3>Quick Links</h3>
                <a href="../pages/about.html">About</a>
                <a href="../pages/events.html">Events</a>
                <a href="../pages/faq.html">FAQ</a>
            </div>
            <div class="footer-column">
                <h3>Student Support</h3>
                <a href="../pages/contact.html">Contact</a>
                <a href="../pages/feedback.html">Feedback</a>
            </div>
        </div>
        <div class="footer-bottom"><p>&copy; 2026 StudentHub</p></div>
    </footer>
    <script src="../js/script.js"></script>
</body>
</html>
