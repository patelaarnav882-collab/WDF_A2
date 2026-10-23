-- =====================================================
-- STUDENTHUB - PRACTICAL 8
-- Database: studenthub_db (MariaDB/MySQL via XAMPP)
--
-- What this file does:
--   1. Creates the database (if it does not exist).
--   2. Recreates the 3 tables (drops them first, in an
--      order that respects foreign keys).
--   3. Inserts small sample data: 5 students, 6 events
--      (taken from the Practical 6 JSON datasets) and
--      6 registrations linking them.
--
-- WARNING: the DROP TABLE statements delete existing rows
-- in these 3 tables. Import into a FRESH database, or
-- export anything important first.
--
-- How to import (phpMyAdmin):
--   1. Open http://localhost/phpmyadmin/
--   2. Click "Import" -> Choose this file -> Go.
-- How to import (command line):
--   C:\xampp\mysql\bin\mysql.exe -u root < studenthub_db.sql
-- =====================================================

CREATE DATABASE IF NOT EXISTS studenthub_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE studenthub_db;

-- Drop in child-to-parent order (registrations first).
DROP TABLE IF EXISTS registrations;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS events;

-- -----------------------------------------------------
-- students: one row per student.
-- -----------------------------------------------------
CREATE TABLE students (
    student_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name  VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL,
    mobile     VARCHAR(15)  NOT NULL,
    course     VARCHAR(50)  NOT NULL DEFAULT '',
    year       TINYINT UNSIGNED NULL,
    semester   TINYINT UNSIGNED NULL,
    gender     ENUM('male', 'female', 'other') NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id),
    UNIQUE KEY uq_students_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- events: one row per campus event.
-- -----------------------------------------------------
CREATE TABLE events (
    event_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title       VARCHAR(150) NOT NULL,
    description TEXT NULL,
    event_date  DATE NOT NULL,
    location    VARCHAR(150) NOT NULL DEFAULT '',
    category    VARCHAR(50)  NOT NULL DEFAULT '',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id),
    KEY idx_events_date (event_date),
    KEY idx_events_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- registrations: links students to events (many-to-many).
-- One student -> many registrations.
-- One event   -> many registrations.
-- The UNIQUE key stops the same student being registered
-- for the same event twice.
-- -----------------------------------------------------
CREATE TABLE registrations (
    registration_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id        INT UNSIGNED NOT NULL,
    event_id          INT UNSIGNED NOT NULL,
    registration_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status            VARCHAR(20) NOT NULL DEFAULT 'Confirmed',
    PRIMARY KEY (registration_id),
    UNIQUE KEY uq_student_event (student_id, event_id),
    CONSTRAINT fk_reg_student
        FOREIGN KEY (student_id)
        REFERENCES students (student_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_reg_event
        FOREIGN KEY (event_id)
        REFERENCES events (event_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Sample data (clearly sample data, no real people).
-- Insert parents before children (FK order).
-- -----------------------------------------------------
INSERT INTO students
    (full_name, email, mobile, course, year, semester, gender)
VALUES
    ('Aarav Patel',  'aarav.p@studenthub.example',  '9876543210', 'B.Tech', 3, 5, 'male'),
    ('Diya Shah',    'diya.s@studenthub.example',   '9876543211', 'BCA',    2, 3, 'female'),
    ('Kabir Mehta',  'kabir.m@studenthub.example',  '9876543212', 'BBA',    1, 1, 'male'),
    ('Ananya Iyer',  'ananya.i@studenthub.example', '9876543213', 'B.Sc',   3, 5, 'female'),
    ('Vivaan Rao',   'vivaan.r@studenthub.example', '9876543214', 'B.Tech', 4, 7, 'male');

INSERT INTO events
    (title, description, event_date, location, category)
VALUES
    ('Cultural Festival',   'Music, dance and cultural activities.',          '2026-11-15', 'College Auditorium',      'Cultural'),
    ('Technical Workshop',  'Hands-on session on new technologies.',          '2026-11-20', 'Computer Lab',            'Technical'),
    ('Sports Day',          'Athletics and team sports for all students.',    '2026-12-05', 'College Ground',          'Sports'),
    ('Student Community Meet', 'Meet fellow students and join clubs.',        '2026-12-12', 'Student Activity Centre', 'Community'),
    ('Coding Hackathon',    'A 12-hour team coding challenge.',               '2027-01-09', 'Innovation Lab',          'Technical'),
    ('Career Guidance Seminar', 'Internships, placements and higher studies.','2027-01-23', 'Seminar Hall',           'Academic');

-- IDs 1-5 are the students above; IDs 1-6 are the events above.
INSERT INTO registrations (student_id, event_id, status)
VALUES
    (1, 1, 'Confirmed'),
    (1, 2, 'Confirmed'),
    (2, 2, 'Confirmed'),
    (2, 5, 'Confirmed'),
    (3, 4, 'Confirmed'),
    (4, 1, 'Confirmed');
