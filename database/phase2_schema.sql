-- =====================================================================
-- LILLIPUT SCHOOL MANAGEMENT SYSTEM — Phase 2 Migration
-- Run this AFTER database/schema.sql has already been imported.
-- Adds: achievements, certificates, simple messaging, student goals,
-- event participants/results. Nothing here touches existing tables
-- except adding two harmless columns to activity_log for audit filtering.
-- =====================================================================

USE lilliput_school;

-- ---------------------------------------------------------------------
-- ACHIEVEMENTS & DIGITAL BADGES
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    title VARCHAR(120) NOT NULL,
    category ENUM('attendance','academic','sports','quiz','reading','coding','participation','other') NOT NULL DEFAULT 'other',
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
-- DIGITAL CERTIFICATES — generated as printable HTML (browser "Save as
-- PDF"), record kept here so it shows in the student's history.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    certificate_type VARCHAR(80) NOT NULL,
    event_name VARCHAR(150) NOT NULL,
    position_text VARCHAR(80) NULL,
    issue_date DATE NOT NULL,
    generated_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- PARENT-TEACHER MESSAGING — simple flat messages tied to a student,
-- not an open chat: only a parent of the student and a teacher who
-- teaches that student's class may message each other about them.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    sender_user_id INT NOT NULL,
    recipient_user_id INT NOT NULL,
    subject VARCHAR(150) NOT NULL,
    body TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_msg_recipient (recipient_user_id, is_read),
    INDEX idx_msg_student (student_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- STUDENT GOALS — set by a teacher, tracked against actual exam marks
-- for that subject (progress is computed, not stored, from `marks`).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS student_goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    starting_percent DECIMAL(5,2) NOT NULL,
    target_percent DECIMAL(5,2) NOT NULL,
    description VARCHAR(255) NULL,
    review_date DATE NOT NULL,
    status ENUM('in_progress','achieved','missed') NOT NULL DEFAULT 'in_progress',
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_goal_student (student_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- EVENT PARTICIPANTS — links students to school_events (which already
-- exists) with an optional result/position for winners.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS event_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    student_id INT NOT NULL,
    result VARCHAR(80) NULL,
    position_text VARCHAR(40) NULL,
    UNIQUE KEY uq_event_student (event_id, student_id),
    FOREIGN KEY (event_id) REFERENCES school_events(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- AUDIT TRAIL — activity_log already exists and is already written to
-- (Auth::log). Add two nullable columns so entries can be filtered by
-- module/record without breaking any existing insert that omits them.
-- ---------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activity_log' AND COLUMN_NAME = 'module'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE activity_log ADD COLUMN module VARCHAR(40) NULL AFTER action, ADD COLUMN record_id INT NULL AFTER module',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- DEMO DATA for the new Phase 2 tables (safe to re-run: uses INSERT IGNORE
-- keyed off natural uniqueness where possible)
-- ---------------------------------------------------------------------
INSERT INTO achievements (student_id, title, category, description, icon, awarded_by, awarded_date)
SELECT s.id, 'Excellent Attendance', 'attendance', 'Maintained over 90% attendance this term.', '🏆',
       (SELECT id FROM users WHERE username='admin'), CURDATE() - INTERVAL 10 DAY
FROM students s WHERE s.admission_number = 'LIL-2025-001'
AND NOT EXISTS (SELECT 1 FROM achievements a WHERE a.student_id = s.id AND a.title = 'Excellent Attendance');

INSERT INTO achievements (student_id, title, category, description, icon, awarded_by, awarded_date)
SELECT s.id, 'Mathematics Improvement', 'academic', 'Significant improvement in Mathematics scores.', '📈',
       (SELECT id FROM users WHERE username='anita.sharma'), CURDATE() - INTERVAL 5 DAY
FROM students s WHERE s.admission_number = 'LIL-2025-001'
AND NOT EXISTS (SELECT 1 FROM achievements a WHERE a.student_id = s.id AND a.title = 'Mathematics Improvement');

INSERT INTO certificates (student_id, certificate_type, event_name, position_text, issue_date, generated_by)
SELECT s.id, 'Merit Certificate', 'First Terminal Examination', 'Class Topper', CURDATE() - INTERVAL 8 DAY,
       (SELECT id FROM users WHERE username='admin')
FROM students s WHERE s.admission_number = 'LIL-2025-001'
AND NOT EXISTS (SELECT 1 FROM certificates c WHERE c.student_id = s.id AND c.event_name = 'First Terminal Examination');

INSERT INTO student_goals (student_id, subject_id, starting_percent, target_percent, description, review_date, created_by)
SELECT s.id, subj.id, 58, 75, 'Improve Mathematics performance through extra practice.', CURDATE() + INTERVAL 25 DAY,
       (SELECT id FROM users WHERE username='anita.sharma')
FROM students s, subjects subj
WHERE s.admission_number = 'LIL-2025-004' AND subj.code = 'MATH'
AND NOT EXISTS (SELECT 1 FROM student_goals g WHERE g.student_id = s.id AND g.subject_id = subj.id);
