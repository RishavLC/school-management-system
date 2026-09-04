<?php
require_once __DIR__ . '/../core/bootstrap.php';
$student = require_student_profile();

$pageTitle = 'Student Dashboard';
$activeMenu = 'dashboard';
$db = Database::connection();
$enr = $student['enrollment'];
$csId = $enr['class_section_id'] ?? null;

$dow = (int) date('w') + 1; // 1=Sun..7=Sat (matches our 1=Sun..6=Fri school week)

$todayClasses = [];
if ($csId) {
    $stmt = $db->prepare("
        SELECT tt.*, subj.name AS subject_name, CONCAT(t.first_name,' ',t.last_name) AS teacher_name
        FROM timetable tt
        JOIN subjects subj ON subj.id = tt.subject_id
        JOIN teachers t ON t.id = tt.teacher_id
        WHERE tt.class_section_id = :cs AND tt.day_of_week = :d
        ORDER BY tt.start_time
    ");
    $stmt->execute(['cs' => $csId, 'd' => $dow]);
    $todayClasses = $stmt->fetchAll();
}

$todayAttendance = $db->prepare('SELECT status FROM attendance WHERE student_id = :s AND attendance_date = CURDATE()');
$todayAttendance->execute(['s' => $student['id']]);
$todayAttendance = $todayAttendance->fetchColumn();

$attRows = $db->prepare('SELECT status, COUNT(*) c FROM attendance WHERE student_id = :s GROUP BY status');
$attRows->execute(['s' => $student['id']]);
$att = ['present' => 0, 'absent' => 0, 'late' => 0];
foreach ($attRows->fetchAll() as $r) { $att[$r['status']] = (int)$r['c']; }
$attTotal = array_sum($att);
$attPct = $attTotal > 0 ? round(($att['present'] + $att['late']) / $attTotal * 100, 1) : null;

$upcomingExams = $db->prepare("
    SELECT es.exam_date, es.start_time, subj.name AS subject_name, e.name AS exam_name
    FROM exam_schedule es
    JOIN exams e ON e.id = es.exam_id
    JOIN subjects subj ON subj.id = es.subject_id
    WHERE es.class_section_id = :cs AND es.exam_date >= CURDATE()
    ORDER BY es.exam_date, es.start_time LIMIT 5
");
$upcomingExams->execute(['cs' => $csId ?? 0]);
$upcomingExams = $upcomingExams->fetchAll();

$recentHomework = $db->prepare("
    SELECT h.*, subj.name AS subject_name,
        (SELECT status FROM homework_submissions hs WHERE hs.homework_id = h.id AND hs.student_id = :s) AS sub_status
    FROM homework h JOIN subjects subj ON subj.id = h.subject_id
    WHERE h.class_section_id = :cs ORDER BY h.due_date DESC LIMIT 5
");
$recentHomework->execute(['cs' => $csId ?? 0, 's' => $student['id']]);
$recentHomework = $recentHomework->fetchAll();

$recentNotices = $db->query("
    SELECT title, notice_date FROM notices WHERE audience IN ('everyone','students') ORDER BY notice_date DESC LIMIT 5
")->fetchAll();

$feeSummary = $db->prepare("
    SELECT COALESCE(SUM(sf.amount - sf.discount + sf.fine),0) AS billed,
           COALESCE((SELECT SUM(p.amount) FROM payments p JOIN student_fees sf2 ON sf2.id = p.student_fee_id WHERE sf2.student_id = sf.student_id),0) AS paid
    FROM student_fees sf WHERE sf.student_id = :s
");
$feeSummary->execute(['s' => $student['id']]);
$feeSummary = $feeSummary->fetch();
$due = max(0, (float)$feeSummary['billed'] - (float)$feeSummary['paid']);

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="card mb-3">
    <div class="card-body d-flex align-items-center gap-3 flex-wrap">
        <div class="avatar-lg"><?= e(strtoupper(substr($student['first_name'], 0, 1))) ?></div>
        <div>
            <h5 class="mb-0">Welcome back, <?= e($student['first_name']) ?>!</h5>
            <div class="text-muted small">
                <?= e($student['admission_number']) ?>
                <?php if ($enr): ?> &middot; <?= e($enr['class_name'] . ' - ' . $enr['section_name']) ?> &middot; Roll <?= e($enr['roll_number'] ?? '-') ?><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-1">
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:#e6f7ee;color:#1aa260"><i class="fa-solid fa-clipboard-check"></i></div>
        <div><div class="stat-value"><?= $todayAttendance ? ucfirst($todayAttendance) : 'Not marked' ?></div><div class="stat-label">Today's Attendance</div></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:#e8ecfb;color:#2f5fdc"><i class="fa-solid fa-percent"></i></div>
        <div><div class="stat-value"><?= $attPct !== null ? $attPct . '%' : '-' ?></div><div class="stat-label">Attendance Rate</div></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:#fff2e0;color:#d9822b"><i class="fa-solid fa-file-pen"></i></div>
        <div><div class="stat-value"><?= count($upcomingExams) ?></div><div class="stat-label">Upcoming Exams</div></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:#fdeaea;color:#d9364a"><i class="fa-solid fa-money-bill-wave"></i></div>
        <div><div class="stat-value"><?= format_money($due) ?></div><div class="stat-label">Fees Due</div></div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header">Today's Timetable</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($todayClasses as $c): ?>
                    <li class="list-group-item">
                        <div class="fw-semibold"><?= e($c['subject_name']) ?></div>
                        <small class="text-muted"><?= e(substr($c['start_time'],0,5)) ?> - <?= e(substr($c['end_time'],0,5)) ?> &middot; <?= e($c['teacher_name']) ?> &middot; <?= e($c['room'] ?? 'Room TBA') ?></small>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($todayClasses)): ?><li class="list-group-item empty-state">No classes scheduled for today.</li><?php endif; ?>
            </ul>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">Recent Homework <a href="<?= base_url('student/homework.php') ?>" class="small">View all</a></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($recentHomework as $h): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold"><?= e($h['title']) ?></div>
                            <small class="text-muted"><?= e($h['subject_name']) ?> &middot; Due <?= format_date($h['due_date']) ?></small>
                        </div>
                        <?= status_badge($h['sub_status'] ?: 'pending') ?>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($recentHomework)): ?><li class="list-group-item empty-state">No homework assigned yet.</li><?php endif; ?>
            </ul>
        </div>

        <div class="card">
            <div class="card-header">Upcoming Exams</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($upcomingExams as $ex): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <div><span class="fw-semibold"><?= e($ex['subject_name']) ?></span> <span class="text-muted small">(<?= e($ex['exam_name']) ?>)</span></div>
                        <span class="text-muted small"><?= format_date($ex['exam_date']) ?> &middot; <?= e(substr($ex['start_time'],0,5)) ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($upcomingExams)): ?><li class="list-group-item empty-state">No upcoming exams scheduled.</li><?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">Recent Notices <a href="<?= base_url('student/notices.php') ?>" class="small">View all</a></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($recentNotices as $n): ?>
                    <li class="list-group-item"><div class="fw-semibold"><?= e($n['title']) ?></div><small class="text-muted"><?= format_date($n['notice_date']) ?></small></li>
                <?php endforeach; ?>
                <?php if (empty($recentNotices)): ?><li class="list-group-item empty-state">No notices yet.</li><?php endif; ?>
            </ul>
        </div>

        <div class="card">
            <div class="card-header">Quick Links</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <a href="<?= base_url('student/timetable.php') ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-calendar-days me-1"></i>Timetable</a>
                <a href="<?= base_url('student/results.php') ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-trophy me-1"></i>Results</a>
                <a href="<?= base_url('student/fees.php') ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-money-bill me-1"></i>Fees</a>
                <a href="<?= base_url('student/leave.php') ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-person-walking-arrow-right me-1"></i>Apply for Leave</a>
                <a href="<?= base_url('student/materials.php') ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-book-open-reader me-1"></i>Materials</a>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
