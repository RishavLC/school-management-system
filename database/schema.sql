-- =====================================================================
-- LILLIPUT SCHOOL MANAGEMENT SYSTEM — Phase 1 Demo
-- Database Schema + Seed Data
-- Import this single file in phpMyAdmin / MySQL CLI. It creates the
-- database, every table, indexes/constraints, and realistic demo data.
-- =====================================================================

DROP DATABASE IF EXISTS lilliput_school;
CREATE DATABASE lilliput_school CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lilliput_school;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- USERS — single login table for every role
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(120) NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin','admin','principal','teacher','student','parent','accountant') NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- ACADEMIC YEAR — only one may be active (enforced in app code)
-- ---------------------------------------------------------------------
CREATE TABLE academic_years (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL UNIQUE,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- CLASSES / SECTIONS  — global catalogues.
-- CLASS_SECTIONS  — the actual "Grade 6 - A" offering for a given year;
-- this is what students actually enrol in and what attendance/exams/
-- timetable/homework hang off, so re-using a class+section combo across
-- years never mixes data between years.
-- ---------------------------------------------------------------------
CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL UNIQUE,
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(10) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE class_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    academic_year_id INT NOT NULL,
    class_id INT NOT NULL,
    section_id INT NOT NULL,
    room VARCHAR(30) NULL,
    class_teacher_id INT NULL,
    UNIQUE KEY uq_year_class_section (academic_year_id, class_id, section_id),
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- TEACHERS
-- ---------------------------------------------------------------------
CREATE TABLE teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    employee_id VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(60) NOT NULL,
    last_name VARCHAR(60) NOT NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(120) NULL,
    address VARCHAR(255) NULL,
    qualification VARCHAR(120) NULL,
    joining_date DATE NULL,
    photo VARCHAR(255) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE class_sections
    ADD CONSTRAINT fk_cs_class_teacher FOREIGN KEY (class_teacher_id) REFERENCES teachers(id) ON DELETE SET NULL;

-- which teacher teaches which subject in which class-section
CREATE TABLE teacher_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    class_section_id INT NOT NULL,
    subject_id INT NOT NULL,
    academic_year_id INT NOT NULL,
    UNIQUE KEY uq_assignment (teacher_id, class_section_id, subject_id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (class_section_id) REFERENCES class_sections(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- STUDENTS / GUARDIANS (parents)
-- ---------------------------------------------------------------------
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL UNIQUE,               -- nullable: not every student needs a login in Phase 1
    admission_number VARCHAR(30) NOT NULL UNIQUE,
    first_name VARCHAR(60) NOT NULL,
    middle_name VARCHAR(60) NULL,
    last_name VARCHAR(60) NOT NULL,
    dob DATE NULL,
    gender ENUM('male','female','other') NOT NULL,
    blood_group VARCHAR(5) NULL,
    photo VARCHAR(255) NULL,
    address VARCHAR(255) NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(120) NULL,
    emergency_contact_name VARCHAR(100) NULL,
    emergency_contact_relation VARCHAR(40) NULL,
    emergency_contact_phone VARCHAR(20) NULL,
    status ENUM('active','inactive','transferred','graduated','left') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE guardians (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL UNIQUE,               -- nullable: some guardians are records only, no portal login
    first_name VARCHAR(60) NOT NULL,
    last_name VARCHAR(60) NOT NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(120) NULL,
    address VARCHAR(255) NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- links one guardian account to one or more children, and (rarely)
-- a child to two guardians, without needing a separate login per child
CREATE TABLE student_guardians (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    guardian_id INT NOT NULL,
    relationship VARCHAR(30) NOT NULL DEFAULT 'Guardian',
    is_primary TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_student_guardian (student_id, guardian_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (guardian_id) REFERENCES guardians(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- STUDENT ENROLLMENT / ACADEMIC HISTORY
-- One row per student per academic year — never overwritten, so
-- promotion/transfer/graduation keeps full history (Master Prompt §8).
-- ---------------------------------------------------------------------
CREATE TABLE student_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    academic_year_id INT NOT NULL,
    class_section_id INT NOT NULL,
    roll_number VARCHAR(10) NULL,
    admission_date DATE NULL,
    status ENUM('active','promoted','transferred','graduated','left') NOT NULL DEFAULT 'active',
    remarks VARCHAR(255) NULL,
    UNIQUE KEY uq_student_year (student_id, academic_year_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
    FOREIGN KEY (class_section_id) REFERENCES class_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- ATTENDANCE
-- ---------------------------------------------------------------------
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    class_section_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('present','absent','late') NOT NULL,
    recorded_by INT NOT NULL,              -- users.id of the teacher who took it
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_date (student_id, attendance_date),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_section_id) REFERENCES class_sections(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    INDEX idx_att_cs_date (class_section_id, attendance_date)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- TIMETABLE — conflict rules (same teacher / same room, same day+time)
-- are enforced in application code before insert.
-- ---------------------------------------------------------------------
CREATE TABLE timetable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_section_id INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    day_of_week TINYINT NOT NULL,          -- 1=Sunday ... 6=Friday (Nepali school week, no Saturday)
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(30) NULL,
    FOREIGN KEY (class_section_id) REFERENCES class_sections(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    INDEX idx_tt_cs_day (class_section_id, day_of_week),
    INDEX idx_tt_teacher_day (teacher_id, day_of_week)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- EXAMINATIONS / SCHEDULE / GRADE SCALE / MARKS
-- ---------------------------------------------------------------------
CREATE TABLE exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    academic_year_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('upcoming','ongoing','completed','result_published') NOT NULL DEFAULT 'upcoming',
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE exam_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    class_section_id INT NOT NULL,
    subject_id INT NOT NULL,
    exam_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(30) NULL,
    full_marks DECIMAL(6,2) NOT NULL DEFAULT 100,
    pass_marks DECIMAL(6,2) NOT NULL DEFAULT 40,
    UNIQUE KEY uq_exam_cs_subject (exam_id, class_section_id, subject_id),
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (class_section_id) REFERENCES class_sections(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- configurable grading so grade logic is never hard-coded in PHP
CREATE TABLE grade_scale (
    id INT AUTO_INCREMENT PRIMARY KEY,
    min_percent DECIMAL(5,2) NOT NULL,
    max_percent DECIMAL(5,2) NOT NULL,
    grade VARCHAR(5) NOT NULL,
    grade_point DECIMAL(3,2) NULL,
    remarks VARCHAR(50) NULL
) ENGINE=InnoDB;

CREATE TABLE marks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_schedule_id INT NOT NULL,
    student_id INT NOT NULL,
    obtained_marks DECIMAL(6,2) NULL,
    is_absent TINYINT(1) NOT NULL DEFAULT 0,
    grade VARCHAR(5) NULL,
    remarks VARCHAR(120) NULL,
    entered_by INT NOT NULL,
    entered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_schedule_student (exam_schedule_id, student_id),
    FOREIGN KEY (exam_schedule_id) REFERENCES exam_schedule(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (entered_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- FEES
-- ---------------------------------------------------------------------
CREATE TABLE fee_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB;

CREATE TABLE student_fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    fee_type_id INT NOT NULL,
    academic_year_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0,
    fine DECIMAL(10,2) NOT NULL DEFAULT 0,
    due_date DATE NULL,
    status ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_type_id) REFERENCES fee_types(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
    INDEX idx_fee_student_status (student_id, status)
) ENGINE=InnoDB;

-- paid_amount/outstanding are always DERIVED from this table
-- (SUM of payments per student_fee), never stored/duplicated.
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_fee_id INT NOT NULL,
    student_id INT NOT NULL,
    receipt_number VARCHAR(30) NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash','bank','online') NOT NULL DEFAULT 'cash',
    reference_number VARCHAR(60) NULL,
    received_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_fee_id) REFERENCES student_fees(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- HOMEWORK
-- ---------------------------------------------------------------------
CREATE TABLE homework (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_section_id INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    due_date DATE NOT NULL,
    attachment VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_section_id) REFERENCES class_sections(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- HOMEWORK SUBMISSIONS — one row per student per homework, created only
-- once the student submits. Absence of a row = "Pending" (derived in
-- app code from homework.due_date, never stored redundantly).
-- ---------------------------------------------------------------------
CREATE TABLE homework_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    homework_id INT NOT NULL,
    student_id INT NOT NULL,
    submission_text TEXT NULL,
    attachment VARCHAR(255) NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('submitted','late','checked') NOT NULL DEFAULT 'submitted',
    marks_obtained DECIMAL(6,2) NULL,
    teacher_remarks VARCHAR(255) NULL,
    checked_at DATETIME NULL,
    UNIQUE KEY uq_homework_student (homework_id, student_id),
    FOREIGN KEY (homework_id) REFERENCES homework(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- LEAVE REQUESTS — student-initiated absence requests
-- ---------------------------------------------------------------------
CREATE TABLE leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    from_date DATE NOT NULL,
    to_date DATE NOT NULL,
    reason VARCHAR(255) NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reviewed_by INT NULL,
    review_remarks VARCHAR(255) NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_leave_student (student_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- LEARNING MATERIALS — teacher-uploaded notes/files per class & subject
-- ---------------------------------------------------------------------
CREATE TABLE learning_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_section_id INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    file_path VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_section_id) REFERENCES class_sections(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- SCHOOL EVENTS — programs, competitions, holidays, important dates
-- ---------------------------------------------------------------------
CREATE TABLE school_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    event_date DATE NOT NULL,
    end_date DATE NULL,
    location VARCHAR(150) NULL,
    audience ENUM('everyone','students','teachers','parents') NOT NULL DEFAULT 'everyone',
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- NOTICES
-- ---------------------------------------------------------------------
CREATE TABLE notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    notice_date DATE NOT NULL,
    audience ENUM('everyone','teachers','students','parents','class') NOT NULL DEFAULT 'everyone',
    class_section_id INT NULL,
    attachment VARCHAR(255) NULL,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_section_id) REFERENCES class_sections(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- NOTIFICATIONS — in-system now; schema leaves room for SMS/push later
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message VARCHAR(255) NOT NULL,
    type ENUM('notice','homework','result','fee','attendance','general') NOT NULL DEFAULT 'general',
    link VARCHAR(255) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notif_user_read (user_id, is_read)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- ACTIVITY LOG — "Recent Activity" widget on the admin dashboard
-- ---------------------------------------------------------------------
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- SEED / DEMO DATA
-- Password for every seeded account: Demo@123   (see README.md)
-- =====================================================================

INSERT INTO users (username, email, password_hash, role, status, must_change_password) VALUES
('superadmin', 'superadmin@lilliputschool.edu.np', '$2y$10$xcKt6sDKwjw05VmFMoXnJO/R0u.GaldU.ERuYQg/tsvEa8KEWSrU2', 'super_admin', 'active', 0),
('admin',      'admin@lilliputschool.edu.np',      '$2y$10$xcKt6sDKwjw05VmFMoXnJO/R0u.GaldU.ERuYQg/tsvEa8KEWSrU2', 'admin', 'active', 0),
('principal',  'principal@lilliputschool.edu.np',  '$2y$10$xcKt6sDKwjw05VmFMoXnJO/R0u.GaldU.ERuYQg/tsvEa8KEWSrU2', 'principal', 'active', 0),
('accountant', 'accountant@lilliputschool.edu.np', '$2y$10$xcKt6sDKwjw05VmFMoXnJO/R0u.GaldU.ERuYQg/tsvEa8KEWSrU2', 'accountant', 'active', 0),
('anita.sharma', 'anita.sharma@lilliputschool.edu.np', '$2y$10$xcKt6sDKwjw05VmFMoXnJO/R0u.GaldU.ERuYQg/tsvEa8KEWSrU2', 'teacher', 'active', 0),
('rajan.bhattarai', 'rajan.bhattarai@lilliputschool.edu.np', '$2y$10$xcKt6sDKwjw05VmFMoXnJO/R0u.GaldU.ERuYQg/tsvEa8KEWSrU2', 'teacher', 'active', 0),
('sunita.gurung', 'sunita.gurung@lilliputschool.edu.np', '$2y$10$xcKt6sDKwjw05VmFMoXnJO/R0u.GaldU.ERuYQg/tsvEa8KEWSrU2', 'teacher', 'active', 0),
('rishav.shrestha', 'rishav.shrestha@lilliputschool.edu.np', '$2y$10$xcKt6sDKwjw05VmFMoXnJO/R0u.GaldU.ERuYQg/tsvEa8KEWSrU2', 'student', 'active', 0),
('maya.karki', 'maya.karki@example.com', '$2y$10$xcKt6sDKwjw05VmFMoXnJO/R0u.GaldU.ERuYQg/tsvEa8KEWSrU2', 'parent', 'active', 0);

-- Academic year (Nepali school calendar) — active
INSERT INTO academic_years (name, start_date, end_date, is_active) VALUES
('2025-2026', '2025-04-14', '2026-04-13', 1);

-- Classes
INSERT INTO classes (name, sort_order) VALUES
('Nursery',1), ('LKG',2), ('UKG',3),
('Grade 1',4), ('Grade 2',5), ('Grade 3',6), ('Grade 4',7), ('Grade 5',8),
('Grade 6',9), ('Grade 7',10), ('Grade 8',11), ('Grade 9',12), ('Grade 10',13);

-- Sections
INSERT INTO sections (name) VALUES ('A'), ('B'), ('C');

-- Subjects
INSERT INTO subjects (name, code, description) VALUES
('English', 'ENG', 'English language and literature'),
('Nepali', 'NEP', 'Nepali language'),
('Mathematics', 'MATH', 'General mathematics'),
('Science', 'SCI', 'General science'),
('Social Studies', 'SOC', 'Social studies and civics'),
('Computer Science', 'COMP', 'Basic computer literacy');

-- Grading scale (configurable, not hard-coded in PHP)
INSERT INTO grade_scale (min_percent, max_percent, grade, grade_point, remarks) VALUES
(90, 100, 'A+', 4.0, 'Outstanding'),
(80, 89.99, 'A', 3.6, 'Excellent'),
(70, 79.99, 'B+', 3.2, 'Very Good'),
(60, 69.99, 'B', 2.8, 'Good'),
(50, 59.99, 'C+', 2.4, 'Satisfactory'),
(40, 49.99, 'C', 2.0, 'Acceptable'),
(0, 39.99, 'NG', 0.0, 'Not Graded / Fail');

-- Teachers
INSERT INTO teachers (user_id, employee_id, first_name, last_name, phone, email, address, qualification, joining_date, status) VALUES
((SELECT id FROM users WHERE username='anita.sharma'),    'EMP-1001', 'Anita',  'Sharma',    '9841000111', 'anita.sharma@lilliputschool.edu.np',    'Baneshwor, Kathmandu', 'M.Ed. English', '2021-06-01', 'active'),
((SELECT id FROM users WHERE username='rajan.bhattarai'), 'EMP-1002', 'Rajan',  'Bhattarai', '9841000222', 'rajan.bhattarai@lilliputschool.edu.np', 'Kalanki, Kathmandu',   'M.Sc. Mathematics', '2020-02-15', 'active'),
((SELECT id FROM users WHERE username='sunita.gurung'),   'EMP-1003', 'Sunita', 'Gurung',    '9841000333', 'sunita.gurung@lilliputschool.edu.np',   'Kirtipur, Kathmandu',  'B.Ed.', '2022-01-10', 'active');

-- Class-section offerings for the active year
INSERT INTO class_sections (academic_year_id, class_id, section_id, room)
SELECT ay.id, c.id, sec.id, r.room
FROM academic_years ay, (SELECT 1 x) x,
 (SELECT 'Grade 4' cname, 'A' sname, 'Room 4A' room
  UNION SELECT 'Grade 6','A','Room 6A'
  UNION SELECT 'Grade 7','A','Room 7A') r
JOIN classes c ON c.name = r.cname
JOIN sections sec ON sec.name = r.sname
WHERE ay.is_active = 1;

-- Set class teachers
UPDATE class_sections cs
JOIN classes c ON c.id = cs.class_id AND c.name = 'Grade 6'
SET cs.class_teacher_id = (SELECT id FROM teachers WHERE employee_id='EMP-1001');

UPDATE class_sections cs
JOIN classes c ON c.id = cs.class_id AND c.name = 'Grade 7'
SET cs.class_teacher_id = (SELECT id FROM teachers WHERE employee_id='EMP-1002');

UPDATE class_sections cs
JOIN classes c ON c.id = cs.class_id AND c.name = 'Grade 4'
SET cs.class_teacher_id = (SELECT id FROM teachers WHERE employee_id='EMP-1003');

-- Teacher subject assignments
INSERT INTO teacher_assignments (teacher_id, class_section_id, subject_id, academic_year_id)
SELECT t.id, cs.id, s.id, ay.id
FROM academic_years ay
JOIN class_sections cs ON cs.academic_year_id = ay.id
JOIN classes c ON c.id = cs.class_id
JOIN sections sec ON sec.id = cs.section_id
JOIN (
    SELECT 'Grade 6' cname,'A' sname,'ENG' scode,'EMP-1001' emp
    UNION SELECT 'Grade 6','A','MATH','EMP-1002'
    UNION SELECT 'Grade 6','A','SCI','EMP-1002'
    UNION SELECT 'Grade 7','A','MATH','EMP-1002'
    UNION SELECT 'Grade 7','A','ENG','EMP-1001'
    UNION SELECT 'Grade 4','A','ENG','EMP-1003'
) map ON map.cname = c.name AND map.sname = sec.name
JOIN subjects s ON s.code = map.scode
JOIN teachers t ON t.employee_id = map.emp
WHERE ay.is_active = 1;

-- Students (realistic Nepali names)
INSERT INTO students (user_id, admission_number, first_name, last_name, dob, gender, blood_group, address, phone, status) VALUES
((SELECT id FROM users WHERE username='rishav.shrestha'), 'LIL-2025-001', 'Rishav', 'Shrestha', '2014-03-12', 'male', 'B+', 'Baneshwor, Kathmandu', '9800000001', 'active'),
(NULL, 'LIL-2025-002', 'Aayusha', 'Karki',     '2014-07-20', 'female', 'O+',  'Koteshwor, Kathmandu', '9800000002', 'active'),
(NULL, 'LIL-2025-003', 'Kiran',   'Karki',     '2016-02-02', 'male',   'O+',  'Koteshwor, Kathmandu', '9800000003', 'active'),
(NULL, 'LIL-2025-004', 'Bibek',   'Rai',       '2013-11-05', 'male',   'A+',  'Baneshwor, Kathmandu', '9800000004', 'active'),
(NULL, 'LIL-2025-005', 'Prisha',  'Maharjan',  '2014-01-30', 'female', 'AB+', 'Kirtipur, Kathmandu',  '9800000005', 'active'),
(NULL, 'LIL-2025-006', 'Suyash',  'Adhikari',  '2013-09-14', 'male',   'B+',  'Lalitpur',             '9800000006', 'active'),
(NULL, 'LIL-2025-007', 'Kripa',   'Tamang',    '2012-05-22', 'female', 'O-',  'Bhaktapur',            '9800000007', 'active');

-- Guardians
INSERT INTO guardians (user_id, first_name, last_name, phone, email, address) VALUES
(NULL, 'Sujata', 'Shrestha', '9841112223', 'sujata.shrestha@example.com', 'Baneshwor, Kathmandu'),
((SELECT id FROM users WHERE username='maya.karki'), 'Maya', 'Karki', '9841998877', 'maya.karki@example.com', 'Koteshwor, Kathmandu');

INSERT INTO student_guardians (student_id, guardian_id, relationship, is_primary)
SELECT s.id, g.id, 'Mother', 1 FROM students s, guardians g WHERE s.admission_number='LIL-2025-001' AND g.first_name='Sujata';

INSERT INTO student_guardians (student_id, guardian_id, relationship, is_primary)
SELECT s.id, g.id, 'Mother', 1 FROM students s, guardians g WHERE s.admission_number='LIL-2025-002' AND g.first_name='Maya';

INSERT INTO student_guardians (student_id, guardian_id, relationship, is_primary)
SELECT s.id, g.id, 'Mother', 1 FROM students s, guardians g WHERE s.admission_number='LIL-2025-003' AND g.first_name='Maya';

-- Enrollment — current active year
INSERT INTO student_enrollments (student_id, academic_year_id, class_section_id, roll_number, admission_date, status)
SELECT s.id, ay.id, cs.id, m.roll, '2025-04-14', 'active'
FROM academic_years ay
JOIN class_sections cs ON cs.academic_year_id = ay.id
JOIN classes c ON c.id = cs.class_id
JOIN sections sec ON sec.id = cs.section_id
JOIN (
    SELECT 'LIL-2025-001' code,'Grade 6' cname,'A' sname,'1' roll
    UNION SELECT 'LIL-2025-002','Grade 6','A','2'
    UNION SELECT 'LIL-2025-004','Grade 6','A','3'
    UNION SELECT 'LIL-2025-005','Grade 6','A','4'
    UNION SELECT 'LIL-2025-006','Grade 7','A','1'
    UNION SELECT 'LIL-2025-007','Grade 7','A','2'
    UNION SELECT 'LIL-2025-003','Grade 4','A','1'
) m ON m.cname = c.name AND m.sname = sec.name
JOIN students s ON s.admission_number = m.code
WHERE ay.is_active = 1;

-- Timetable — Grade 6-A
INSERT INTO timetable (class_section_id, subject_id, teacher_id, day_of_week, start_time, end_time, room)
SELECT cs.id, subj.id, t.id, d.dow, d.st, d.et, cs.room
FROM class_sections cs
JOIN classes c ON c.id = cs.class_id AND c.name='Grade 6'
JOIN sections sec ON sec.id = cs.section_id AND sec.name='A'
JOIN academic_years ay ON ay.id = cs.academic_year_id AND ay.is_active = 1
JOIN (
    SELECT 1 dow,'ENG' code,'10:00:00' st,'10:45:00' et,'EMP-1001' emp
    UNION SELECT 1,'MATH','10:45:00','11:30:00','EMP-1002'
    UNION SELECT 2,'SCI','10:00:00','10:45:00','EMP-1002'
    UNION SELECT 3,'ENG','10:00:00','10:45:00','EMP-1001'
    UNION SELECT 4,'MATH','10:00:00','10:45:00','EMP-1002'
) d ON 1=1
JOIN subjects subj ON subj.code = d.code
JOIN teachers t ON t.employee_id = d.emp;

-- Attendance — last 4 school days for Grade 6-A
INSERT INTO attendance (student_id, class_section_id, attendance_date, status, recorded_by)
SELECT s.id, cs.id, d.dt, 'present', (SELECT id FROM users WHERE username='anita.sharma')
FROM students s
JOIN student_enrollments se ON se.student_id = s.id
JOIN class_sections cs ON cs.id = se.class_section_id
JOIN classes c ON c.id = cs.class_id AND c.name='Grade 6'
JOIN (
    SELECT CURDATE()-INTERVAL 4 DAY dt UNION SELECT CURDATE()-INTERVAL 3 DAY
    UNION SELECT CURDATE()-INTERVAL 2 DAY UNION SELECT CURDATE()-INTERVAL 1 DAY
) d ON 1=1
WHERE s.admission_number IN ('LIL-2025-001','LIL-2025-002','LIL-2025-004','LIL-2025-005');

-- Realistic variation: Rishav absent once, late once; Aayusha absent once
UPDATE attendance SET status='absent'
WHERE student_id=(SELECT id FROM students WHERE admission_number='LIL-2025-001') AND attendance_date=CURDATE()-INTERVAL 3 DAY;
UPDATE attendance SET status='late'
WHERE student_id=(SELECT id FROM students WHERE admission_number='LIL-2025-001') AND attendance_date=CURDATE()-INTERVAL 2 DAY;
UPDATE attendance SET status='absent'
WHERE student_id=(SELECT id FROM students WHERE admission_number='LIL-2025-002') AND attendance_date=CURDATE()-INTERVAL 4 DAY;

-- Examination
INSERT INTO exams (name, academic_year_id, start_date, end_date, status)
SELECT 'First Terminal Examination', ay.id, CURDATE()-INTERVAL 20 DAY, CURDATE()-INTERVAL 15 DAY, 'result_published'
FROM academic_years ay WHERE ay.is_active=1;

INSERT INTO exams (name, academic_year_id, start_date, end_date, status)
SELECT 'Second Terminal Examination', ay.id, CURDATE()+INTERVAL 30 DAY, CURDATE()+INTERVAL 35 DAY, 'upcoming'
FROM academic_years ay WHERE ay.is_active=1;

INSERT INTO exam_schedule (exam_id, class_section_id, subject_id, exam_date, start_time, end_time, room, full_marks, pass_marks)
SELECT e.id, cs.id, subj.id, CURDATE()-INTERVAL 20 DAY, '10:00:00','12:00:00', cs.room, 100, 40
FROM exams e
JOIN class_sections cs ON cs.academic_year_id = e.academic_year_id
JOIN classes c ON c.id=cs.class_id AND c.name='Grade 6'
JOIN sections sec ON sec.id=cs.section_id AND sec.name='A'
JOIN subjects subj ON subj.code='ENG'
WHERE e.name='First Terminal Examination';

INSERT INTO exam_schedule (exam_id, class_section_id, subject_id, exam_date, start_time, end_time, room, full_marks, pass_marks)
SELECT e.id, cs.id, subj.id, CURDATE()-INTERVAL 18 DAY, '10:00:00','12:00:00', cs.room, 100, 40
FROM exams e
JOIN class_sections cs ON cs.academic_year_id = e.academic_year_id
JOIN classes c ON c.id=cs.class_id AND c.name='Grade 6'
JOIN sections sec ON sec.id=cs.section_id AND sec.name='A'
JOIN subjects subj ON subj.code='MATH'
WHERE e.name='First Terminal Examination';

-- Marks — English
INSERT INTO marks (exam_schedule_id, student_id, obtained_marks, grade, entered_by)
SELECT es.id, s.id, m.marks, (SELECT grade FROM grade_scale WHERE m.marks BETWEEN min_percent AND max_percent LIMIT 1),
       (SELECT id FROM users WHERE username='anita.sharma')
FROM exam_schedule es
JOIN subjects subj ON subj.id=es.subject_id AND subj.code='ENG'
JOIN (SELECT 'LIL-2025-001' code,82 marks UNION SELECT 'LIL-2025-002',91 UNION SELECT 'LIL-2025-004',67 UNION SELECT 'LIL-2025-005',74) m
JOIN students s ON s.admission_number=m.code;

-- Marks — Mathematics
INSERT INTO marks (exam_schedule_id, student_id, obtained_marks, grade, entered_by)
SELECT es.id, s.id, m.marks, (SELECT grade FROM grade_scale WHERE m.marks BETWEEN min_percent AND max_percent LIMIT 1),
       (SELECT id FROM users WHERE username='rajan.bhattarai')
FROM exam_schedule es
JOIN subjects subj ON subj.id=es.subject_id AND subj.code='MATH'
JOIN (SELECT 'LIL-2025-001' code,88 marks UNION SELECT 'LIL-2025-002',95 UNION SELECT 'LIL-2025-004',58 UNION SELECT 'LIL-2025-005',35) m
JOIN students s ON s.admission_number=m.code;

-- Fee types
INSERT INTO fee_types (name, description) VALUES
('Admission Fee', 'One-time fee charged at admission'),
('Monthly Tuition Fee', 'Recurring monthly tuition'),
('Examination Fee', 'Per-term examination fee'),
('Transportation Fee', 'Monthly school bus fee');

-- Student fees for Rishav — a realistic mix of paid & outstanding
INSERT INTO student_fees (student_id, fee_type_id, academic_year_id, amount, discount, fine, due_date, status)
SELECT s.id, ft.id, ay.id, v.amount, 0, 0, v.due, v.status
FROM students s, academic_years ay,
 (SELECT 'Admission Fee' ft, 5000 amount, '2025-04-20' due, 'paid' status
  UNION SELECT 'Monthly Tuition Fee', 3500, '2026-08-10', 'paid'
  UNION SELECT 'Monthly Tuition Fee', 3500, '2026-09-10', 'unpaid'
  UNION SELECT 'Examination Fee', 1200, '2026-08-01', 'paid'
  UNION SELECT 'Transportation Fee', 2000, '2026-09-05', 'unpaid') v
JOIN fee_types ft ON ft.name=v.ft
WHERE s.admission_number='LIL-2025-001' AND ay.is_active=1;

-- Aayusha Karki — one paid, one partially paid (for Maya Karki's parent demo)
INSERT INTO student_fees (student_id, fee_type_id, academic_year_id, amount, discount, fine, due_date, status)
SELECT s.id, ft.id, ay.id, v.amount, 0, 0, v.due, v.status
FROM students s, academic_years ay,
 (SELECT 'Admission Fee' ft, 5000 amount, '2025-04-20' due, 'paid' status
  UNION SELECT 'Monthly Tuition Fee', 3500, '2026-09-10', 'partial') v
JOIN fee_types ft ON ft.name=v.ft
WHERE s.admission_number='LIL-2025-002' AND ay.is_active=1;

-- Kiran Karki — admission fee only, unpaid (younger sibling, just admitted)
INSERT INTO student_fees (student_id, fee_type_id, academic_year_id, amount, discount, fine, due_date, status)
SELECT s.id, ft.id, ay.id, 4500, 500, 0, '2025-05-01', 'unpaid'
FROM students s, academic_years ay, fee_types ft
WHERE s.admission_number='LIL-2025-003' AND ay.is_active=1 AND ft.name='Admission Fee';

-- Payments (fully covering every 'paid' student_fee row, partial covering the 'partial' one)
INSERT INTO payments (student_fee_id, student_id, receipt_number, amount, payment_date, payment_method, reference_number, received_by)
SELECT sf.id, sf.student_id, CONCAT('RCPT-', LPAD(sf.id,6,'0')), sf.amount - sf.discount + sf.fine,
       DATE_SUB(sf.due_date, INTERVAL 2 DAY), 'cash', NULL, (SELECT id FROM users WHERE username='accountant')
FROM student_fees sf WHERE sf.status = 'paid';

INSERT INTO payments (student_fee_id, student_id, receipt_number, amount, payment_date, payment_method, reference_number, received_by)
SELECT sf.id, sf.student_id, CONCAT('RCPT-', LPAD(sf.id,6,'0'), '-P'), 1500, CURDATE()-INTERVAL 3 DAY, 'bank', 'NIC-88213', (SELECT id FROM users WHERE username='accountant')
FROM student_fees sf WHERE sf.status = 'partial';

-- Homework
INSERT INTO homework (class_section_id, subject_id, teacher_id, title, description, due_date)
SELECT cs.id, subj.id, t.id, 'Essay: My Favourite Festival', 'Write a 200-word essay on your favourite festival and why you enjoy it.', CURDATE()+INTERVAL 4 DAY
FROM class_sections cs
JOIN classes c ON c.id=cs.class_id AND c.name='Grade 6'
JOIN sections sec ON sec.id=cs.section_id AND sec.name='A'
JOIN subjects subj ON subj.code='ENG'
JOIN teachers t ON t.employee_id='EMP-1001';

INSERT INTO homework (class_section_id, subject_id, teacher_id, title, description, due_date)
SELECT cs.id, subj.id, t.id, 'Worksheet: Fractions', 'Complete exercise 4.2 (Q1-Q10) from the textbook.', CURDATE()+INTERVAL 2 DAY
FROM class_sections cs
JOIN classes c ON c.id=cs.class_id AND c.name='Grade 6'
JOIN sections sec ON sec.id=cs.section_id AND sec.name='A'
JOIN subjects subj ON subj.code='MATH'
JOIN teachers t ON t.employee_id='EMP-1002';

-- Emergency contacts (demo)
UPDATE students SET emergency_contact_name='Sujata Shrestha', emergency_contact_relation='Mother', emergency_contact_phone='9841112223' WHERE admission_number='LIL-2025-001';
UPDATE students SET emergency_contact_name='Maya Karki', emergency_contact_relation='Mother', emergency_contact_phone='9841998877' WHERE admission_number='LIL-2025-002';

-- Homework submissions — Rishav has submitted the Math worksheet, essay still pending
INSERT INTO homework_submissions (homework_id, student_id, submission_text, status, submitted_at)
SELECT h.id, s.id, 'Completed all 10 questions from exercise 4.2.', 'submitted', NOW() - INTERVAL 1 DAY
FROM homework h JOIN subjects subj ON subj.id=h.subject_id AND subj.code='MATH'
JOIN students s ON s.admission_number='LIL-2025-001';

-- Leave requests — one approved, one pending
INSERT INTO leave_requests (student_id, from_date, to_date, reason, status, reviewed_by, review_remarks, reviewed_at)
SELECT s.id, CURDATE()-INTERVAL 10 DAY, CURDATE()-INTERVAL 9 DAY, 'Family function out of town.', 'approved',
       (SELECT id FROM users WHERE username='admin'), 'Approved. Please catch up on missed homework.', NOW()-INTERVAL 9 DAY
FROM students s WHERE s.admission_number='LIL-2025-001';

INSERT INTO leave_requests (student_id, from_date, to_date, reason, status)
SELECT s.id, CURDATE()+INTERVAL 5 DAY, CURDATE()+INTERVAL 6 DAY, 'Medical check-up appointment.', 'pending'
FROM students s WHERE s.admission_number='LIL-2025-001';

-- Learning materials — Grade 6-A
INSERT INTO learning_materials (class_section_id, subject_id, teacher_id, title, description)
SELECT cs.id, subj.id, t.id, 'Grammar Notes - Tenses', 'Summary notes covering all tense forms with examples.'
FROM class_sections cs
JOIN classes c ON c.id=cs.class_id AND c.name='Grade 6'
JOIN sections sec ON sec.id=cs.section_id AND sec.name='A'
JOIN subjects subj ON subj.code='ENG'
JOIN teachers t ON t.employee_id='EMP-1001';

INSERT INTO learning_materials (class_section_id, subject_id, teacher_id, title, description)
SELECT cs.id, subj.id, t.id, 'Fractions - Practice Sheet', 'Extra practice problems for revision before the exam.'
FROM class_sections cs
JOIN classes c ON c.id=cs.class_id AND c.name='Grade 6'
JOIN sections sec ON sec.id=cs.section_id AND sec.name='A'
JOIN subjects subj ON subj.code='MATH'
JOIN teachers t ON t.employee_id='EMP-1002';

-- School events
INSERT INTO school_events (title, description, event_date, location, audience, created_by) VALUES
('Annual Sports Day', 'Inter-house athletics and games for all grades.', CURDATE()+INTERVAL 12 DAY, 'School Playground', 'everyone', (SELECT id FROM users WHERE username='admin')),
('Science Exhibition', 'Students showcase science projects and experiments.', CURDATE()+INTERVAL 25 DAY, 'School Hall', 'students', (SELECT id FROM users WHERE username='admin')),
('Dashain Holiday Begins', 'School closed for the Dashain festival break.', CURDATE()+INTERVAL 40 DAY, NULL, 'everyone', (SELECT id FROM users WHERE username='admin'));

-- Notices
INSERT INTO notices (title, description, notice_date, audience, created_by) VALUES
('School Reopens After Dashain-Tihar Break', 'The school will reopen on the scheduled date. All students must be in proper uniform.', CURDATE()-INTERVAL 2 DAY, 'everyone', (SELECT id FROM users WHERE username='admin')),
('Parent-Teacher Meeting', 'A PTM will be held for all classes to discuss the First Terminal Examination results.', CURDATE()-INTERVAL 1 DAY, 'parents', (SELECT id FROM users WHERE username='admin'));

INSERT INTO notices (title, description, notice_date, audience, class_section_id, created_by)
SELECT 'Grade 6-A Field Visit', 'Grade 6-A students will visit the science museum next week. Permission slips required.', CURDATE(), 'class', cs.id, (SELECT id FROM users WHERE username='anita.sharma')
FROM class_sections cs JOIN classes c ON c.id=cs.class_id AND c.name='Grade 6' JOIN sections sec ON sec.id=cs.section_id AND sec.name='A';

-- Notifications
INSERT INTO notifications (user_id, title, message, type)
SELECT id, 'New Notice Published', 'Parent-Teacher Meeting notice has been published.', 'notice' FROM users WHERE username='rishav.shrestha';
INSERT INTO notifications (user_id, title, message, type)
SELECT id, 'Fee Due Reminder', 'Monthly Tuition Fee for September is due soon.', 'fee' FROM users WHERE username='maya.karki';
INSERT INTO notifications (user_id, title, message, type, is_read)
SELECT id, 'New Homework Assigned', 'Essay: My Favourite Festival has been assigned in English.', 'homework', 0 FROM users WHERE username='rishav.shrestha';
INSERT INTO notifications (user_id, title, message, type, is_read)
SELECT id, 'Result Published', 'First Terminal Examination results are now available.', 'result', 1 FROM users WHERE username='rishav.shrestha';
INSERT INTO notifications (user_id, title, message, type, is_read)
SELECT id, 'Fee Reminder', 'Monthly Tuition Fee for September is due soon.', 'fee', 0 FROM users WHERE username='rishav.shrestha';
INSERT INTO notifications (user_id, title, message, type, is_read)
SELECT id, 'Leave Request Approved', 'Your leave request has been approved by the admin.', 'general', 1 FROM users WHERE username='rishav.shrestha';

-- Activity log
INSERT INTO activity_log (user_id, action) VALUES
((SELECT id FROM users WHERE username='admin'), 'Added new student Rishav Shrestha (LIL-2025-001)'),
((SELECT id FROM users WHERE username='anita.sharma'), 'Marked attendance for Grade 6 - Section A'),
((SELECT id FROM users WHERE username='accountant'), 'Recorded fee payment for Rishav Shrestha');



-- =====================================================================
-- LILLIPUT SCHOOL MANAGEMENT SYSTEM — Phase 2 Migration
-- Run this AFTER database/schema.sql has already been imported.
-- Purely additive: no existing table is dropped or altered destructively.
-- =====================================================================
USE lilliput_school;

-- ---------------------------------------------------------------------
-- Extend activity_log into a proper audit trail (Section 20).
-- Existing rows are untouched; new columns are nullable so old
-- Auth::log() calls keep working unmodified.
-- ---------------------------------------------------------------------
ALTER TABLE activity_log
    ADD COLUMN module VARCHAR(50) NULL AFTER action,
    ADD COLUMN record_type VARCHAR(50) NULL AFTER module,
    ADD COLUMN record_id INT NULL AFTER record_type,
    ADD COLUMN details VARCHAR(500) NULL AFTER record_id,
    ADD INDEX idx_audit_module (module),
    ADD INDEX idx_audit_record (record_type, record_id);

-- ---------------------------------------------------------------------
-- Achievements & Digital Badges (Section 13)
-- ---------------------------------------------------------------------
CREATE TABLE achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    category ENUM('academic','attendance','sports','quiz','reading','coding','participation','other') NOT NULL DEFAULT 'other',
    description VARCHAR(255) NULL,
    icon VARCHAR(10) NOT NULL DEFAULT '🏆',
    awarded_by INT NOT NULL,
    awarded_date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (awarded_by) REFERENCES users(id),
    INDEX idx_ach_student (student_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Digital Certificates (Section 14) — metadata only; the certificate
-- itself is a print-ready HTML page (browser "Print to PDF"), so no
-- binary storage or PDF library dependency is required.
-- ---------------------------------------------------------------------
CREATE TABLE certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    certificate_type VARCHAR(100) NOT NULL,
    event_name VARCHAR(150) NOT NULL,
    position VARCHAR(100) NULL,
    issue_date DATE NOT NULL,
    generated_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id),
    INDEX idx_cert_student (student_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Student Progress Timeline (Section 6) — manually-added milestones.
-- Automatic milestones (admission, enrollment, exam results) are
-- derived live from existing tables and merged with these at render
-- time, so historical data never needs to be duplicated here.
-- ---------------------------------------------------------------------
CREATE TABLE timeline_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    event_date DATE NOT NULL,
    title VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    category ENUM('achievement','academic','sports','participation','other') NOT NULL DEFAULT 'other',
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_timeline_student (student_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Demo data so the new features aren't empty on first load
-- ---------------------------------------------------------------------
INSERT INTO achievements (student_id, title, category, description, icon, awarded_by, awarded_date)
SELECT s.id, 'Excellent Attendance', 'attendance', 'Maintained outstanding attendance during the first term.', '🏆',
       (SELECT id FROM users WHERE username='admin'),          CURDATE() - INTERVAL 10 DAY
FROM students s WHERE s.admission_number = 'LIL-2025-001';

INSERT INTO achievements (student_id, title, category, description, icon, awarded_by, awarded_date)
SELECT s.id, 'Mathematics Improvement', 'academic', 'Significant improvement in Mathematics between terms.', '📈',
       (SELECT id FROM users WHERE username='anita.sharma'), CURDATE() - INTERVAL 5 DAY
FROM students s WHERE s.admission_number = 'LIL-2025-001';

INSERT INTO achievements (student_id, title, category, description, icon, awarded_by, awarded_date)
SELECT s.id, 'Quiz Competition Winner', 'quiz', 'First place in the inter-class quiz competition.', '🥇',
       (SELECT id FROM users WHERE username='admin'), CURDATE() - INTERVAL 3 DAY
FROM students s WHERE s.admission_number = 'LIL-2025-002';

INSERT INTO timeline_events (student_id, event_date, title, description, category, created_by)
SELECT s.id, CURDATE() - INTERVAL 3 DAY, 'Quiz Competition', 'Represented Grade 6-A in the school quiz competition and won first place.', 'achievement',
       (SELECT id FROM users WHERE username='admin')
FROM students s WHERE s.admission_number = 'LIL-2025-002';
