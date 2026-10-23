-- =====================================================
-- STUDENTHUB - PRACTICAL 14 MIGRATION
-- 1. Adds 'admin' to the students.role ENUM. The students
--    table IS the users table in this project (it holds
--    password_hash, role, remember-me columns), so
--    "total users" = COUNT(*) FROM students.
-- 2. Creates audit_logs for admin-action tracking.
--    user_id points at students(student_id): SET NULL on
--    delete so log rows survive account removal.
--    user_name/user_role are snapshots for the same reason.
--
-- Run ONCE in phpMyAdmin (SQL tab, Go). The CREATE TABLE
-- uses IF NOT EXISTS so re-running is harmless. Check
-- first with: SHOW COLUMNS FROM students LIKE 'role';
-- =====================================================

ALTER TABLE students
    MODIFY COLUMN role ENUM('student', 'faculty', 'admin')
        NOT NULL DEFAULT 'student';

CREATE TABLE IF NOT EXISTS audit_logs (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NULL,
    user_name   VARCHAR(100) NULL,
    user_role   VARCHAR(20) NULL,
    action      VARCHAR(60) NOT NULL,
    entity_type VARCHAR(30) NULL,
    entity_id   INT UNSIGNED NULL,
    details     VARCHAR(255) NULL,
    ip_address  VARCHAR(45) NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_created (created_at),
    KEY idx_audit_action (action),
    CONSTRAINT fk_audit_user
        FOREIGN KEY (user_id)
        REFERENCES students (student_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
