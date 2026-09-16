<?php
require_once __DIR__ . '/../core/bootstrap.php';

// Enforces Auth::requireArea('student') and loads the student profile +
// current-year enrollment. Redirects/stops automatically if not valid.
$student = require_student_profile();

// Set page variables
$pageTitle = 'Student Dashboard';
$activeMenu = 'dashboard';

// Get database connection
$db = Database::connection();

// Get student enrollment
$enr = $student['enrollment'] ?? [];
$csId = $enr['class_section_id'] ?? null;
$studentId = $student['id'] ?? 0;

// Calculate day of week (1=Sunday, 7=Saturday)
$dow = (int) date('w') + 1;

// 1. Get today's classes
$todayClasses = [];
if ($csId) {
    try {
        $stmt = $db->prepare("
            SELECT tt.*, subj.name AS subject_name, 
                   CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
            FROM timetable tt
            JOIN subjects subj ON subj.id = tt.subject_id
            JOIN teachers t ON t.id = tt.teacher_id
            WHERE tt.class_section_id = :cs AND tt.day_of_week = :d
            ORDER BY tt.start_time
        ");
        $stmt->execute(['cs' => $csId, 'd' => $dow]);
        $todayClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Timetable query error: " . $e->getMessage());
    }
}

// 2. Get today's attendance
$todayAttendance = null;
try {
    $stmt = $db->prepare("SELECT status FROM attendance WHERE student_id = :s AND attendance_date = CURDATE()");
    $stmt->execute(['s' => $studentId]);
    $todayAttendance = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Attendance query error: " . $e->getMessage());
}

// 3. Get attendance summary
$att = ['present' => 0, 'absent' => 0, 'late' => 0];
$attPct = null;

try {
    $stmt = $db->prepare("SELECT status, COUNT(*) as c FROM attendance WHERE student_id = :s GROUP BY status");
    $stmt->execute(['s' => $studentId]);
    $attRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($attRows as $r) {
        if (isset($att[$r['status']])) {
            $att[$r['status']] = (int)$r['c'];
        }
    }
    
    $attTotal = array_sum($att);
    if ($attTotal > 0) {
        $attPct = round(($att['present'] + $att['late']) / $attTotal * 100, 1);
    }
} catch (PDOException $e) {
    error_log("Attendance summary error: " . $e->getMessage());
}

// 4. Get upcoming exams
$upcomingExams = [];
if ($csId) {
    try {
        $stmt = $db->prepare("
            SELECT es.exam_date, es.start_time, subj.name AS subject_name, e.name AS exam_name
            FROM exam_schedule es
            JOIN exams e ON e.id = es.exam_id
            JOIN subjects subj ON subj.id = es.subject_id
            WHERE es.class_section_id = :cs AND es.exam_date >= CURDATE()
            ORDER BY es.exam_date, es.start_time 
            LIMIT 5
        ");
        $stmt->execute(['cs' => $csId]);
        $upcomingExams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Exams query error: " . $e->getMessage());
    }
}

// 5. Get recent homework
$recentHomework = [];
try {
    $stmt = $db->prepare("
        SELECT h.*, subj.name AS subject_name,
            (SELECT status FROM homework_submissions hs 
             WHERE hs.homework_id = h.id AND hs.student_id = :s) AS sub_status
        FROM homework h 
        JOIN subjects subj ON subj.id = h.subject_id
        WHERE h.class_section_id = :cs 
        ORDER BY h.due_date DESC 
        LIMIT 5
    ");
    $stmt->execute(['cs' => $csId ?? 0, 's' => $studentId]);
    $recentHomework = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Homework query error: " . $e->getMessage());
}

// 6. Get recent notices
$recentNotices = [];
try {
    $stmt = $db->query("
        SELECT title, notice_date, description 
        FROM notices 
        WHERE audience IN ('everyone', 'students') 
        ORDER BY notice_date DESC 
        LIMIT 5
    ");
    $recentNotices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Notices query error: " . $e->getMessage());
}

// 7. Get fee summary
$due = 0;
try {
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(sf.amount - sf.discount + sf.fine), 0) AS billed,
            COALESCE((
                SELECT SUM(p.amount) 
                FROM payments p 
                JOIN student_fees sf2 ON sf2.id = p.student_fee_id 
                WHERE sf2.student_id = sf.student_id
            ), 0) AS paid
        FROM student_fees sf 
        WHERE sf.student_id = :s
    ");
    $stmt->execute(['s' => $studentId]);
    $feeSummary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($feeSummary) {
        $due = max(0, (float)$feeSummary['billed'] - (float)$feeSummary['paid']);
    }
} catch (PDOException $e) {
    error_log("Fee summary error: " . $e->getMessage());
}

// Include layout start
include __DIR__ . '/../includes/layout_start.php';
?>

<div class="container-fluid">
    <!-- Welcome Card -->
    <div class="card mb-3 shadow-sm">
        <div class="card-body d-flex align-items-center gap-3 flex-wrap">
            <div class="avatar-lg bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:60px;height:60px;font-size:24px;font-weight:bold;">
                <?= e(strtoupper(substr($student['first_name'] ?? 'S', 0, 1))) ?>
            </div>
            <div>
                <h5 class="mb-0">Welcome back, <?= e($student['first_name'] ?? 'Student') ?>!</h5>
                <div class="text-muted small">
                    <?= e($student['admission_number'] ?? 'N/A') ?>
                    <?php if ($enr): ?> 
                        &middot; <?= e($enr['class_name'] ?? '') . ' - ' . e($enr['section_name'] ?? '') ?> 
                        &middot; Roll <?= e($enr['roll_number'] ?? '-') ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="stat-card bg-white p-3 rounded shadow-sm d-flex align-items-center gap-3">
                <div class="stat-icon rounded-circle p-2" style="background:#e6f7ee;color:#1aa260;width:48px;height:48px;display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-clipboard-check fa-lg"></i>
                </div>
                <div>
                    <div class="stat-value fw-bold h5 mb-0">
                        <?= $todayAttendance ? ucfirst($todayAttendance) : 'Not marked' ?>
                    </div>
                    <div class="stat-label text-muted small">Today's Attendance</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-white p-3 rounded shadow-sm d-flex align-items-center gap-3">
                <div class="stat-icon rounded-circle p-2" style="background:#e8ecfb;color:#2f5fdc;width:48px;height:48px;display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-percent fa-lg"></i>
                </div>
                <div>
                    <div class="stat-value fw-bold h5 mb-0">
                        <?= $attPct !== null ? $attPct . '%' : '-' ?>
                    </div>
                    <div class="stat-label text-muted small">Attendance Rate</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-white p-3 rounded shadow-sm d-flex align-items-center gap-3">
                <div class="stat-icon rounded-circle p-2" style="background:#fff2e0;color:#d9822b;width:48px;height:48px;display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-file-pen fa-lg"></i>
                </div>
                <div>
                    <div class="stat-value fw-bold h5 mb-0"><?= count($upcomingExams) ?></div>
                    <div class="stat-label text-muted small">Upcoming Exams</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-white p-3 rounded shadow-sm d-flex align-items-center gap-3">
                <div class="stat-icon rounded-circle p-2" style="background:#fdeaea;color:#d9364a;width:48px;height:48px;display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-money-bill-wave fa-lg"></i>
                </div>
                <div>
                    <div class="stat-value fw-bold h5 mb-0"><?= format_money($due) ?></div>
                    <div class="stat-label text-muted small">Fees Due</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="row g-3">
        <!-- Left Column -->
        <div class="col-lg-7">
            <!-- Today's Timetable -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fa-regular fa-calendar me-2"></i>Today's Timetable</h6>
                </div>
                <ul class="list-group list-group-flush">
                    <?php if (!empty($todayClasses)): ?>
                        <?php foreach ($todayClasses as $c): ?>
                            <li class="list-group-item">
                                <div class="fw-semibold"><?= e($c['subject_name'] ?? 'N/A') ?></div>
                                <small class="text-muted">
                                    <?= e(substr($c['start_time'] ?? '', 0, 5)) ?> - 
                                    <?= e(substr($c['end_time'] ?? '', 0, 5)) ?> 
                                    &middot; <?= e($c['teacher_name'] ?? 'TBA') ?> 
                                    &middot; <?= e($c['room'] ?? 'Room TBA') ?>
                                </small>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="list-group-item text-center text-muted py-3">
                            <i class="fa-regular fa-clock me-1"></i>No classes scheduled for today.
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Recent Homework -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fa-regular fa-book me-2"></i>Recent Homework</h6>
                    <a href="<?= base_url('student/homework.php') ?>" class="small text-primary">View all</a>
                </div>
                <ul class="list-group list-group-flush">
                    <?php if (!empty($recentHomework)): ?>
                        <?php foreach ($recentHomework as $h): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold"><?= e($h['title'] ?? 'N/A') ?></div>
                                    <small class="text-muted">
                                        <?= e($h['subject_name'] ?? 'N/A') ?> 
                                        &middot; Due <?= format_date($h['due_date'] ?? '') ?>
                                    </small>
                                </div>
                                <?= status_badge($h['sub_status'] ?? 'pending') ?>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="list-group-item text-center text-muted py-3">
                            <i class="fa-regular fa-file me-1"></i>No homework assigned yet.
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Upcoming Exams -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fa-regular fa-calendar-check me-2"></i>Upcoming Exams</h6>
                </div>
                <ul class="list-group list-group-flush">
                    <?php if (!empty($upcomingExams)): ?>
                        <?php foreach ($upcomingExams as $ex): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-semibold"><?= e($ex['subject_name'] ?? 'N/A') ?></span>
                                    <span class="text-muted small">(<?= e($ex['exam_name'] ?? 'N/A') ?>)</span>
                                </div>
                                <span class="text-muted small">
                                    <?= format_date($ex['exam_date'] ?? '') ?> 
                                    &middot; <?= e(substr($ex['start_time'] ?? '', 0, 5)) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="list-group-item text-center text-muted py-3">
                            <i class="fa-regular fa-calendar me-1"></i>No upcoming exams scheduled.
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-5">
            <!-- Recent Notices -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fa-regular fa-bell me-2"></i>Recent Notices</h6>
                    <a href="<?= base_url('student/notices.php') ?>" class="small text-primary">View all</a>
                </div>
                <ul class="list-group list-group-flush">
                    <?php if (!empty($recentNotices)): ?>
                        <?php foreach ($recentNotices as $n): ?>
                            <li class="list-group-item">
                                <div class="fw-semibold"><?= e($n['title'] ?? 'N/A') ?></div>
                                <small class="text-muted"><?= format_date($n['notice_date'] ?? '') ?></small>
                                <?php if (!empty($n['description'])): ?>
                                    <div class="small text-muted mt-1"><?= e(substr($n['description'], 0, 100)) ?>...</div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="list-group-item text-center text-muted py-3">
                            <i class="fa-regular fa-bell me-1"></i>No notices yet.
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Quick Links -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fa-regular fa-link me-2"></i>Quick Links</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= base_url('student/timetable.php') ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fa-regular fa-calendar-days me-1"></i>Timetable
                        </a>
                        <a href="<?= base_url('student/results.php') ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fa-regular fa-trophy me-1"></i>Results
                        </a>
                        <a href="<?= base_url('student/fees.php') ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fa-regular fa-money-bill me-1"></i>Fees
                        </a>
                        <a href="<?= base_url('student/leave.php') ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fa-regular fa-person-walking-arrow-right me-1"></i>Leave
                        </a>
                        <a href="<?= base_url('student/materials.php') ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fa-regular fa-book-open-reader me-1"></i>Materials
                        </a>
                        <a href="<?= base_url('student/profile.php') ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fa-regular fa-user me-1"></i>Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout_end.php'; ?>