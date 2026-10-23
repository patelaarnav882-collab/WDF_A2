<?php
/* =====================================================
   STUDENTHUB - PRACTICAL 14
   Reusable server-side audit logger.

   Usage (only AFTER the action has succeeded):
       require_once __DIR__ . '/audit_logger.php';
       audit_log('student_add', 'student', $newId, 'Added ...');

   - Reads the actor (id/name/role) from the session, never
     from form input or URL parameters.
   - Uses a MySQLi prepared statement (no string building).
   - Never throws: logging must not break the main action,
     so every failure is error_log()ed and swallowed.
   - Never logs passwords, hashes, session IDs or tokens.
   ===================================================== */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db_mysqli.php';

// Records one audit row. $action is a short machine name
// such as 'student_add'; $entity_type is 'student'/'event'/
// 'user'; $entity_id is the affected row id (null when the
// action has no single target, e.g. admin_login).
function audit_log(
    string $action,
    ?string $entity_type = null,
    ?int $entity_id = null,
    ?string $details = null
): void {
    try {
        // Keep the table tidy: short lowercase action names.
        $action = trim($action);
        if ($action === '' || !preg_match('/^[a-z0-9_]{1,60}$/', $action)) {
            return;
        }
        if ($entity_type !== null
            && !preg_match('/^[a-z0-9_]{1,30}$/', $entity_type)) {
            $entity_type = null;
        }
        if ($entity_id !== null && $entity_id <= 0) {
            $entity_id = null;
        }
        if ($details !== null) {
            $details = mb_substr(trim($details), 0, 255);
            if ($details === '') {
                $details = null;
            }
        }

        $user = current_user();
        $userId = null;
        $userName = null;
        $userRole = null;
        if (is_array($user)) {
            $userId = isset($user['id']) ? (int) $user['id'] : null;
            if ($userId !== null && $userId <= 0) {
                $userId = null;
            }
            $userName = isset($user['name'])
                ? mb_substr((string) $user['name'], 0, 100) : null;
            $userRole = isset($user['role'])
                ? mb_substr((string) $user['role'], 0, 20) : null;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $ip = null;
        }

        $db = crud_db();
        $stmt = $db->prepare(
            'INSERT INTO audit_logs
                (user_id, user_name, user_role, action,
                 entity_type, entity_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'issssiss',
            $userId,
            $userName,
            $userRole,
            $action,
            $entity_type,
            $entity_id,
            $details,
            $ip
        );
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('StudentHub audit log failed.');
    }
}
