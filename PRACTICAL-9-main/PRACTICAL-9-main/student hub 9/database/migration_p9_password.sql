-- =====================================================
-- STUDENTHUB - PRACTICAL 9 MIGRATION
-- Adds password-hash storage to the existing students
-- table WITHOUT deleting any existing rows.
--
-- Run once, AFTER database/studenthub_db.sql:
--   phpMyAdmin: select studenthub_db -> SQL tab ->
--   paste this file's contents -> Go.
--   Or: mysql.exe -u root studenthub_db < migration file
--
-- Existing sample rows simply get password_hash = NULL
-- (they were profile-only rows, not account logins).
-- Do NOT re-run blindly: ADD COLUMN fails if the column
-- already exists. Check Structure tab first.
-- =====================================================

ALTER TABLE students
    ADD COLUMN password_hash VARCHAR(255) NULL
    AFTER gender;
