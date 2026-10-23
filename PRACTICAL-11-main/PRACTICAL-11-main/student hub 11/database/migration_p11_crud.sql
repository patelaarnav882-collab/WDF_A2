-- =====================================================
-- STUDENTHUB - PRACTICAL 11 MIGRATION + SEED DATA
-- 1. Adds enrollment_id (college ID card number) to the
--    existing students table. Existing rows are kept;
--    their enrollment_id simply stays NULL.
-- 2. Inserts 20 fictional sample students for CRUD,
--    search and filter testing (clearly fake data).
--
-- Run ONCE in phpMyAdmin (SQL tab, Go). INSERT IGNORE
-- makes re-running safe: existing emails/enrollment IDs
-- are skipped instead of duplicated.
-- =====================================================

ALTER TABLE students
    ADD COLUMN enrollment_id VARCHAR(20) NULL DEFAULT NULL
    AFTER student_id;

ALTER TABLE students
    ADD UNIQUE KEY uq_students_enrollment (enrollment_id);

INSERT IGNORE INTO students
    (enrollment_id, full_name, email, mobile, course, year,
     semester, gender, role)
VALUES
    ('STU2026001', 'Aarav Patel',   'aarav.p.sample@studenthub.example',   '9810000001', 'B.Tech', 3, 5, 'male',   'student'),
    ('STU2026002', 'Diya Shah',     'diya.s.sample@studenthub.example',    '9810000002', 'BCA',    2, 3, 'female', 'student'),
    ('STU2026003', 'Kabir Mehta',   'kabir.m.sample@studenthub.example',   '9810000003', 'BBA',    1, 1, 'male',   'student'),
    ('STU2026004', 'Ananya Iyer',   'ananya.i.sample@studenthub.example',  '9810000004', 'B.Sc',   3, 5, 'female', 'student'),
    ('STU2026005', 'Vivaan Rao',    'vivaan.r.sample@studenthub.example',  '9810000005', 'B.Tech', 4, 7, 'male',   'student'),
    ('STU2026006', 'Ishita Nair',   'ishita.n.sample@studenthub.example',  '9810000006', 'B.Com',  2, 4, 'female', 'student'),
    ('STU2026007', 'Arjun Desai',   'arjun.d.sample@studenthub.example',   '9810000007', 'BCA',    3, 5, 'male',   'student'),
    ('STU2026008', 'Meera Joshi',   'meera.j.sample@studenthub.example',   '9810000008', 'BBA',    2, 4, 'female', 'student'),
    ('STU2026009', 'Rohan Kulkarni','rohan.k.sample@studenthub.example',   '9810000009', 'B.Tech', 1, 1, 'male',   'student'),
    ('STU2026010', 'Sanya Kapoor',  'sanya.k.sample@studenthub.example',   '9810000010', 'B.Sc',   1, 2, 'female', 'student'),
    ('STU2026011', 'Aditya Menon',  'aditya.m.sample@studenthub.example',  '9810000011', 'B.Tech', 2, 3, 'male',   'student'),
    ('STU2026012', 'Navya Reddy',   'navya.r.sample@studenthub.example',   '9810000012', 'B.Com',  3, 6, 'female', 'student'),
    ('STU2026013', 'Krishna Pillai','krishna.p.sample@studenthub.example', '9810000013', 'BCA',    1, 2, 'male',   'student'),
    ('STU2026014', 'Pooja Sharma',  'pooja.s.sample@studenthub.example',   '9810000014', 'BBA',    3, 5, 'female', 'student'),
    ('STU2026015', 'Yash Thakur',   'yash.t.sample@studenthub.example',    '9810000015', 'B.Tech', 3, 6, 'male',   'student'),
    ('STU2026016', 'Riya Verma',    'riya.v.sample@studenthub.example',    '9810000016', 'B.Sc',   4, 7, 'female', 'student'),
    ('STU2026017', 'Dev Gandhi',    'dev.g.sample@studenthub.example',     '9810000017', 'BCA',    4, 7, 'male',   'student'),
    ('STU2026018', 'Sara Khan',     'sara.k.sample@studenthub.example',    '9810000018', 'B.Com',  1, 1, 'female', 'student'),
    ('STU2026019', 'Om Trivedi',    'om.t.sample@studenthub.example',      '9810000019', 'BBA',    4, 8, 'male',   'student'),
    ('STU2026020', 'Tara Bhatt',    'tara.b.sample@studenthub.example',    '9810000020', 'B.Tech', 2, 4, 'female', 'student');
