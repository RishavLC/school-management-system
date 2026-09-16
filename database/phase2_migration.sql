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
       (SELECT id FROM users WHERE username='admin'), CURDATE() - INTERVAL 10 DAY
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
