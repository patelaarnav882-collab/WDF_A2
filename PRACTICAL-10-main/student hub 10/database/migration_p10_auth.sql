-- =====================================================
-- STUDENTHUB - PRACTICAL 10 MIGRATION
-- Adds login/session support columns to students.
-- Run ONCE in phpMyAdmin (SQL tab, Go). Existing rows
-- are preserved; new columns are NULL by default.
--
--   role             'student' or 'faculty' (default student)
--   last_login       updated at each successful login
--   remember_hash    SHA-256 hash of the remember-me token
--                    (the raw token is NEVER stored)
--   remember_expiry  when the remember-me token expires
--
-- Make a faculty demo account AFTER registering it
-- through the form, e.g.:
--   UPDATE students SET role = 'faculty'
--   WHERE email = 'faculty.demo@example.com';
-- =====================================================

ALTER TABLE students
    ADD COLUMN role ENUM('student', 'faculty')
        NOT NULL DEFAULT 'student' AFTER gender,
    ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL
        AFTER role,
    ADD COLUMN remember_hash VARCHAR(64) NULL DEFAULT NULL
        AFTER last_login,
    ADD COLUMN remember_expiry DATETIME NULL DEFAULT NULL
        AFTER remember_hash;
