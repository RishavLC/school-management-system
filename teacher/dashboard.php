<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireArea('teacher');

$pageTitle = 'Teacher Dashboard';
$activeMenu = 'dashboard';
$db = Database::connection();

$teacher = $db->prepare('SELECT * FROM teachers WHERE user_id = :u');
$teacher->execute(['u' => Auth::id()]);
$teacher = $teacher->fetch();

if (!$teacher) {
    flash('error', 'No teacher profile is linked to your account. Contact the school admin.');
    include __DIR__ . '/../includes/layout_start.php';
    echo '<div class="empty-state">No teacher profile found.</div>';
    include __DIR__ . '/../includes/layout_end.php';
    exit;
}

$dow = (int) date('N') % 7 + 1; // PHP N: 1=Mon..7=Sun -> convert to our 1=Sun..6=Fri scheme approx
// Simpler: map PHP w (0=Sun..6=Sat) directly to our 1..7 scheme where 1=Sun
$dow = (int) date('w') + 1;

$todayClasses = $db->prepare("
    SELECT tt.*, subj.name AS subject_name, c.name AS class_name, sec.name AS section_name
    FROM timetable tt
    JOIN subjects subj ON subj.id = tt.subject_id
    JOIN class_sections cs ON cs.id = tt.class_section_id
    JOIN classes c ON c.id = cs.class_id
    JOIN sections sec ON sec.id = cs.section_id
    WHERE tt.teacher_id = :t AND tt.day_of_week = :d
    ORDER BY tt.start_time
");
$todayClasses->execute(['t' => $teacher['id'], 'd' => $dow]);
$todayClasses = $todayClasses->fetchAll();

$assignedClasses = $db->prepare("
    SELECT DISTINCT cs.id, c.name AS class_name, sec.name AS section_name,
        (SELECT COUNT(*) FROM student_enrollments se WHERE se.class_section_id = cs.id) AS student_count
    FROM teacher_assignments ta
    JOIN class_sections cs ON cs.id = ta.class_section_id
    JOIN classes c ON c.id = cs.class_id
    JOIN sections sec ON sec.id = cs.section_id
    WHERE ta.teacher_id = :t
    ORDER BY c.sort_order, sec.name
");
$assignedClasses->execute(['t' => $teacher['id']]);
$assignedClasses = $assignedClasses->fetchAll();

$homeworkCount = $db->prepare('SELECT COUNT(*) FROM homework WHERE teacher_id = :t AND due_date >= CURDATE()');
$homeworkCount->execute(['t' => $teacher['id']]);
$homeworkCount = (int) $homeworkCount->fetchColumn();

$recentNotices = $db->query("
    SELECT title, notice_date, audience FROM notices
    WHERE audience IN ('everyone','teachers') ORDER BY notice_date DESC LIMIT 5
")->fetchAll();

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="row g-3 mb-1">
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:#e8ecfb;color:#2f5fdc"><i class="fa-solid fa-chalkboard"></i></div>
        <div><div class="stat-value"><?= count($assignedClasses) ?></div><div class="stat-label">Assigned Classes</div></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:#e6f7ee;color:#1aa260"><i class="fa-solid fa-calendar-day"></i></div>
        <div><div class="stat-value"><?= count($todayClasses) ?></div><div class="stat-label">Classes Today</div></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:#fff2e0;color:#d9822b"><i class="fa-solid fa-book-open"></i></div>
        <div><div class="stat-value"><?= $homeworkCount ?></div><div class="stat-label">Pending Homework</div></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:#fdeaea;color:#d9364a"><i class="fa-solid fa-clipboard-check"></i></div>
        <div><a href="<?= base_url('teacher/attendance.php') ?>" class="stretched-link text-decoration-none"><div class="stat-value" style="font-size:16px">Take</div><div class="stat-label">Attendance</div></a></div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header">Today's Timetable</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($todayClasses as $c): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold"><?= e($c['subject_name']) ?> — <?= e($c['class_name'].' - '.$c['section_name']) ?></div>
                            <small class="text-muted"><?= e(substr($c['start_time'],0,5)) ?> - <?= e(substr($c['end_time'],0,5)) ?> &middot; <?= e($c['room'] ?? 'Room TBA') ?></small>
                        </div>
                        <a href="<?= base_url('teacher/attendance.php?class_section_id=' . (int)$c['class_section_id']) ?>" class="btn btn-sm btn-outline-primary">Attendance</a>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($todayClasses)): ?><li class="list-group-item empty-state">No classes scheduled for today.</li><?php endif; ?>
            </ul>
        </div>

        <div class="card">
            <div class="card-header">My Assigned Classes</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Class</th><th>Students</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($assignedClasses as $c): ?>
                        <tr>
                            <td><?= e($c['class_name'].' - '.$c['section_name']) ?></td>
                            <td><?= (int)$c['student_count'] ?></td>
                            <td class="text-end">
                                <a href="<?= base_url('teacher/attendance.php?class_section_id='.(int)$c['id']) ?>" class="btn btn-sm btn-outline-primary">Attendance</a>
                                <a href="<?= base_url('teacher/marks.php?class_section_id='.(int)$c['id']) ?>" class="btn btn-sm btn-outline-secondary">Marks</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($assignedClasses)): ?><tr><td colspan="3" class="empty-state">No classes assigned yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Notices</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($recentNotices as $n): ?>
                    <li class="list-group-item"><div class="fw-semibold"><?= e($n['title']) ?></div><small class="text-muted"><?= format_date($n['notice_date']) ?></small></li>
                <?php endforeach; ?>
                <?php if (empty($recentNotices)): ?><li class="list-group-item empty-state">No notices yet.</li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
