<?php
/**
 * Shared helper functions. Kept framework-free and dependency-free on purpose
 * so the whole app stays "Core PHP".
 */

function e(?string $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function base_url(string $path = ''): string
{
    static $cfg = null;
    if ($cfg === null) $cfg = require __DIR__ . '/../config/app.php';
    return rtrim($cfg['base_url'], '/') . '/' . ltrim($path, '/');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $key, ?string $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    if (!empty($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

function format_date(?string $date, string $fmt = 'd M Y'): string
{
    if (!$date) return '-';
    $d = DateTime::createFromFormat('Y-m-d', substr($date, 0, 10));
    return $d ? $d->format($fmt) : $date;
}

function format_money(float $amount): string
{
    return 'Rs. ' . number_format($amount, 2);
}

/** Human label for role codes */
function role_label(string $role): string
{
    $labels = [
        'super_admin' => 'Super Admin',
        'admin'       => 'School Admin',
        'principal'   => 'Principal',
        'teacher'     => 'Teacher',
        'student'     => 'Student',
        'parent'      => 'Parent',
        'accountant'  => 'Accountant',
    ];
    return $labels[$role] ?? ucfirst($role);
}

/**
 * Loads the students row + current-year enrollment (class/section) for the
 * logged-in student. Used at the top of every page under /student/.
 * Returns null if no student profile is linked to this account.
 */
function current_student(): ?array
{
    $db = Database::connection();
    $stmt = $db->prepare('SELECT * FROM students WHERE user_id = :u');
    $stmt->execute(['u' => Auth::id()]);
    $student = $stmt->fetch();
    if (!$student) return null;

    $enr = $db->prepare("
        SELECT se.*, cs.id AS class_section_id, c.name AS class_name, sec.name AS section_name,
               ay.name AS year_name, ay.id AS academic_year_id
        FROM student_enrollments se
        JOIN class_sections cs ON cs.id = se.class_section_id
        JOIN classes c ON c.id = cs.class_id
        JOIN sections sec ON sec.id = cs.section_id
        JOIN academic_years ay ON ay.id = se.academic_year_id
        WHERE se.student_id = :s AND ay.is_active = 1
        LIMIT 1
    ");
    $enr->execute(['s' => $student['id']]);
    $student['enrollment'] = $enr->fetch() ?: null;
    return $student;
}

/**
 * Guard used at the top of every /student/*.php page: enforces the
 * student area role AND that a student profile is actually linked.
 * Renders a friendly empty state and stops execution if not.
 */
function require_student_profile(): array
{
    Auth::requireArea('student');
    $student = current_student();
    if (!$student) {
        $pageTitle = 'Student Portal';
        include __DIR__ . '/../includes/layout_start.php';
        echo '<div class="empty-state"><i class="fa-solid fa-user-slash"></i><br>No student profile is linked to your account. Contact the school admin.</div>';
        include __DIR__ . '/../includes/layout_end.php';
        exit;
    }
    return $student;
}

/**
 * Loads the guardians row for the logged-in parent account.
 * Returns null if no guardian profile is linked.
 */
function current_guardian(): ?array
{
    $db = Database::connection();
    $stmt = $db->prepare('SELECT * FROM guardians WHERE user_id = :u');
    $stmt->execute(['u' => Auth::id()]);
    $g = $stmt->fetch();
    return $g ?: null;
}

/** Guard used at the top of every /parent/*.php page. */
function require_guardian_profile(): array
{
    Auth::requireArea('parent');
    $g = current_guardian();
    if (!$g) {
        $pageTitle = 'Parent Portal';
        include __DIR__ . '/../includes/layout_start.php';
        echo '<div class="empty-state"><i class="fa-solid fa-user-slash"></i><br>No guardian profile is linked to your account. Contact the school admin.</div>';
        include __DIR__ . '/../includes/layout_end.php';
        exit;
    }
    return $g;
}

/**
 * All children (students) linked to a guardian, with their current-year
 * class/section. Used to build the "My Children" selector.
 */
function guardian_children(int $guardianId): array
{
    $db = Database::connection();
    $stmt = $db->prepare("
        SELECT s.*, cs.id AS class_section_id, c.name AS class_name, sec.name AS section_name,
               se.roll_number
        FROM student_guardians sg
        JOIN students s ON s.id = sg.student_id
        LEFT JOIN student_enrollments se ON se.student_id = s.id
            AND se.academic_year_id = (SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1)
        LEFT JOIN class_sections cs ON cs.id = se.class_section_id
        LEFT JOIN classes c ON c.id = cs.class_id
        LEFT JOIN sections sec ON sec.id = cs.section_id
        WHERE sg.guardian_id = :g
        ORDER BY s.first_name
    ");
    $stmt->execute(['g' => $guardianId]);
    return $stmt->fetchAll();
}

/**
 * Resolves which child a parent is currently viewing. Reads ?student_id=
 * from the query string if present and it belongs to this guardian,
 * otherwise falls back to the first child. Stores the choice in session
 * so it persists as the parent navigates between pages.
 */
function selected_child(array $children): ?array
{
    if (empty($children)) return null;
    $requested = (int) ($_GET['student_id'] ?? 0);
    foreach ($children as $c) {
        if ($c['id'] === $requested) {
            $_SESSION['active_child_id'] = $c['id'];
            return $c;
        }
    }
    $active = $_SESSION['active_child_id'] ?? null;
    foreach ($children as $c) {
        if ($c['id'] === $active) return $c;
    }
    $_SESSION['active_child_id'] = $children[0]['id'];
    return $children[0];
}

/**
 * EARLY WARNING SYSTEM — rule-based, no AI. Returns a list of flags for
 * one student, each with a professional (non-judgemental) label. Used by
 * admin/attention.php, teacher/dashboard.php, and the student complete
 * profile. Thresholds are intentionally simple and easy for a school
 * admin to reason about.
 */
function student_attention_flags(int $studentId): array
{
    $db = Database::connection();
    $flags = [];

    // 1. Attendance below 75%
    $att = $db->prepare("SELECT status, COUNT(*) c FROM attendance WHERE student_id = :s GROUP BY status");
    $att->execute(['s' => $studentId]);
    $counts = ['present' => 0, 'absent' => 0, 'late' => 0];
    foreach ($att->fetchAll() as $r) { $counts[$r['status']] = (int) $r['c']; }
    $total = array_sum($counts);
    if ($total >= 5) {
        $pct = ($counts['present'] + $counts['late']) / $total * 100;
        if ($pct < 75) {
            $flags[] = ['type' => 'attendance', 'label' => 'Attendance Below Required Level', 'detail' => round($pct, 1) . '% attendance'];
        }
    }

    // 2. Marks trending down: compare the two most recent exams' average %
    $examAverages = $db->prepare("
        SELECT e.id AS exam_id, e.name, e.start_date,
               AVG(m.obtained_marks / es.full_marks * 100) AS avg_pct
        FROM marks m
        JOIN exam_schedule es ON es.id = m.exam_schedule_id
        JOIN exams e ON e.id = es.exam_id
        WHERE m.student_id = :s AND m.is_absent = 0
        GROUP BY e.id, e.name, e.start_date
        ORDER BY e.start_date DESC
        LIMIT 2
    ");
    $examAverages->execute(['s' => $studentId]);
    $rows = $examAverages->fetchAll();
    if (count($rows) === 2) {
        $latest = (float) $rows[0]['avg_pct'];
        $previous = (float) $rows[1]['avg_pct'];
        if ($previous > 0 && ($previous - $latest) >= 10) {
            $flags[] = ['type' => 'academic', 'label' => 'Academic Performance Declining', 'detail' => round($previous, 1) . '% → ' . round($latest, 1) . '%'];
        }
    }

    // 3. Multiple missing homework (past due date, no submission)
    $missing = $db->prepare("
        SELECT COUNT(*) FROM homework h
        JOIN student_enrollments se ON se.class_section_id = h.class_section_id
        WHERE se.student_id = :s
          AND se.academic_year_id = (SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1)
          AND h.due_date < CURDATE()
          AND NOT EXISTS (SELECT 1 FROM homework_submissions hs WHERE hs.homework_id = h.id AND hs.student_id = :s2)
    ");
    $missing->execute(['s' => $studentId, 's2' => $studentId]);
    $missingCount = (int) $missing->fetchColumn();
    if ($missingCount >= 3) {
        $flags[] = ['type' => 'homework', 'label' => 'Multiple Homework Assignments Missing', 'detail' => $missingCount . ' overdue, not submitted'];
    }

    // 4. Long-term unpaid fees (overdue more than 30 days)
    $unpaid = $db->prepare("
        SELECT COUNT(*) FROM student_fees
        WHERE student_id = :s AND status != 'paid' AND due_date < CURDATE() - INTERVAL 30 DAY
    ");
    $unpaid->execute(['s' => $studentId]);
    if ((int) $unpaid->fetchColumn() > 0) {
        $flags[] = ['type' => 'fee', 'label' => 'Fee Payment Overdue', 'detail' => 'Outstanding for over 30 days'];
    }

    return $flags;
}

/** Bootstrap color + icon per attention flag type, used consistently across attention widgets. */
function attention_flag_style(string $type): array
{
    return match ($type) {
        'attendance' => ['color' => 'warning', 'icon' => 'fa-user-clock'],
        'academic'   => ['color' => 'danger',  'icon' => 'fa-chart-line'],
        'homework'   => ['color' => 'info',    'icon' => 'fa-book'],
        'fee'        => ['color' => 'secondary', 'icon' => 'fa-money-bill'],
        default      => ['color' => 'secondary', 'icon' => 'fa-circle-exclamation'],
    };
}

/**
 * STUDENT PERFORMANCE PROFILE — combines attendance, exam results, and
 * homework completion into one summary, plus a subject-by-subject trend
 * comparing the two most recent published exams. Used by
 * student/performance.php, parent view, and the admin complete profile.
 */
function compute_student_performance(int $studentId): array
{
    $db = Database::connection();

    // Attendance %
    $att = $db->prepare("SELECT status, COUNT(*) c FROM attendance WHERE student_id = :s GROUP BY status");
    $att->execute(['s' => $studentId]);
    $counts = ['present' => 0, 'absent' => 0, 'late' => 0];
    foreach ($att->fetchAll() as $r) { $counts[$r['status']] = (int) $r['c']; }
    $attTotal = array_sum($counts);
    $attendancePct = $attTotal > 0 ? round(($counts['present'] + $counts['late']) / $attTotal * 100, 1) : null;

    // Average result % across all published exams
    $avgResult = $db->prepare("
        SELECT AVG(m.obtained_marks / es.full_marks * 100) AS avg_pct
        FROM marks m
        JOIN exam_schedule es ON es.id = m.exam_schedule_id
        JOIN exams e ON e.id = es.exam_id
        WHERE m.student_id = :s AND m.is_absent = 0 AND e.status = 'result_published'
    ");
    $avgResult->execute(['s' => $studentId]);
    $avgResultPct = $avgResult->fetchColumn();
    $avgResultPct = $avgResultPct !== null ? round((float) $avgResultPct, 1) : null;

    // Homework completion % (submitted vs assigned, current academic year)
    $hwTotal = $db->prepare("
        SELECT COUNT(*) FROM homework h
        JOIN student_enrollments se ON se.class_section_id = h.class_section_id
        WHERE se.student_id = :s AND se.academic_year_id = (SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1)
    ");
    $hwTotal->execute(['s' => $studentId]);
    $hwTotalCount = (int) $hwTotal->fetchColumn();

    $hwDone = $db->prepare("
        SELECT COUNT(*) FROM homework h
        JOIN student_enrollments se ON se.class_section_id = h.class_section_id
        JOIN homework_submissions hs ON hs.homework_id = h.id AND hs.student_id = se.student_id
        WHERE se.student_id = :s AND se.academic_year_id = (SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1)
    ");
    $hwDone->execute(['s' => $studentId]);
    $hwDoneCount = (int) $hwDone->fetchColumn();
    $homeworkPct = $hwTotalCount > 0 ? round($hwDoneCount / $hwTotalCount * 100, 1) : null;

    // Subject-wise trend: latest published exam vs the one before it, per subject
    $rows = $db->prepare("
        SELECT subj.id AS subject_id, subj.name AS subject_name, e.id AS exam_id, e.start_date,
               (m.obtained_marks / es.full_marks * 100) AS pct
        FROM marks m
        JOIN exam_schedule es ON es.id = m.exam_schedule_id
        JOIN exams e ON e.id = es.exam_id
        JOIN subjects subj ON subj.id = es.subject_id
        WHERE m.student_id = :s AND m.is_absent = 0 AND e.status = 'result_published'
        ORDER BY subj.name, e.start_date DESC
    ");
    $rows->execute(['s' => $studentId]);

    $bySubject = [];
    foreach ($rows->fetchAll() as $r) {
        $bySubject[$r['subject_name']][] = round((float) $r['pct'], 1);
    }
    $subjectPerformance = [];
    foreach ($bySubject as $name => $pcts) {
        $latest = $pcts[0];
        $previous = $pcts[1] ?? null;
        $trend = 'flat';
        if ($previous !== null) {
            $trend = $latest > $previous ? 'up' : ($latest < $previous ? 'down' : 'flat');
        }
        $subjectPerformance[] = ['subject' => $name, 'latest' => $latest, 'previous' => $previous, 'trend' => $trend];
    }

    return [
        'attendance_pct' => $attendancePct,
        'avg_result_pct' => $avgResultPct,
        'homework_pct'   => $homeworkPct,
        'subjects'       => $subjectPerformance,
    ];
}

/**
 * STUDENT PROGRESS TIMELINE — merges automatic milestones derived from
 * existing records (enrollment per year, published exam results) with
 * manually-added timeline_events (achievements, participation, etc.),
 * sorted chronologically. Nothing here is deleted or overwritten across
 * academic years, so history is preserved automatically.
 */
function student_timeline(int $studentId): array
{
    $db = Database::connection();
    $events = [];

    $enrollments = $db->prepare("
        SELECT se.admission_date, ay.name AS year_name, c.name AS class_name, sec.name AS section_name
        FROM student_enrollments se
        JOIN academic_years ay ON ay.id = se.academic_year_id
        JOIN class_sections cs ON cs.id = se.class_section_id
        JOIN classes c ON c.id = cs.class_id
        JOIN sections sec ON sec.id = cs.section_id
        WHERE se.student_id = :s
        ORDER BY ay.start_date
    ");
    $enrollments->execute(['s' => $studentId]);
    foreach ($enrollments->fetchAll() as $e) {
        $events[] = [
            'date' => $e['admission_date'],
            'title' => $e['class_name'] . ' - ' . $e['section_name'] . ' Enrollment',
            'description' => 'Academic year ' . $e['year_name'],
            'category' => 'academic',
        ];
    }

    $exams = $db->prepare("
        SELECT DISTINCT e.name, e.end_date
        FROM marks m
        JOIN exam_schedule es ON es.id = m.exam_schedule_id
        JOIN exams e ON e.id = es.exam_id
        WHERE m.student_id = :s AND e.status = 'result_published'
        ORDER BY e.end_date
    ");
    $exams->execute(['s' => $studentId]);
    foreach ($exams->fetchAll() as $e) {
        $events[] = [
            'date' => $e['end_date'],
            'title' => $e['name'],
            'description' => 'Results published',
            'category' => 'academic',
        ];
    }

    $manual = $db->prepare("SELECT event_date AS date, title, description, category FROM timeline_events WHERE student_id = :s");
    $manual->execute(['s' => $studentId]);
    foreach ($manual->fetchAll() as $m) { $events[] = $m; }

    usort($events, fn($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));
    return $events;
}

/**
 * AUDIT TRAIL — records who did what, when, to which record. Safe to
 * call anywhere; failures are logged but never break the request.
 * Use this (rather than the older Auth::log) for any Phase 2+ action
 * you want to be filterable/searchable in admin/audit_log.php.
 */
function record_audit(string $module, string $action, ?string $recordType = null, ?int $recordId = null, ?string $details = null): void
{
    try {
        Database::connection()->prepare("
            INSERT INTO activity_log (user_id, action, module, record_type, record_id, details)
            VALUES (:u, :a, :m, :rt, :rid, :d)
        ")->execute([
            'u' => Auth::id(), 'a' => $action, 'm' => $module,
            'rt' => $recordType, 'rid' => $recordId, 'd' => $details,
        ]);
    } catch (Throwable $e) {
        error_log('record_audit failed: ' . $e->getMessage());
    }
}

function status_badge(string $status): string
{
    $map = [
        'active' => 'success', 'present' => 'success', 'paid' => 'success', 'pass' => 'success',
        'inactive' => 'secondary', 'left' => 'secondary',
        'absent' => 'danger', 'unpaid' => 'danger', 'fail' => 'danger',
        'late' => 'warning', 'partial' => 'warning', 'pending' => 'warning',
        'transferred' => 'info', 'graduated' => 'primary',
        'submitted' => 'info', 'checked' => 'primary',
        'approved' => 'success', 'rejected' => 'danger',
        'upcoming' => 'info', 'ongoing' => 'warning', 'completed' => 'secondary', 'result_published' => 'success',
    ];
    $color = $map[$status] ?? 'secondary';
    return '<span class="badge text-bg-' . $color . '">' . e(ucfirst($status)) . '</span>';
}
