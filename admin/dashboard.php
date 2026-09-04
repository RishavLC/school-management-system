<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireArea('admin');

$pageTitle = 'Admin Dashboard';
$activeMenu = 'dashboard';
$db = Database::connection();

$totalStudents = (int) $db->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetchColumn();
$totalTeachers = (int) $db->query("SELECT COUNT(*) FROM teachers WHERE status='active'")->fetchColumn();
$totalClasses  = (int) $db->query("SELECT COUNT(*) FROM class_sections cs JOIN academic_years ay ON cs.academic_year_id=ay.id WHERE ay.is_active=1")->fetchColumn();

$stmt = $db->prepare("SELECT status, COUNT(*) c FROM attendance WHERE attendance_date = CURDATE() GROUP BY status");
$stmt->execute();
$todayAttendance = ['present' => 0, 'absent' => 0, 'late' => 0];
foreach ($stmt->fetchAll() as $row) { $todayAttendance[$row['status']] = (int) $row['c']; }
$todayMarked = array_sum($todayAttendance);

$feeStats = $db->query("
    SELECT
      COALESCE(SUM(amount - discount + fine),0) AS total_billed,
      COALESCE(SUM((SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.student_fee_id = sf.id)),0) AS total_collected
    FROM student_fees sf
")->fetch();
$outstanding = max(0, $feeStats['total_billed'] - $feeStats['total_collected']);

$upcomingExams = $db->query("
    SELECT e.name, e.start_date, e.end_date FROM exams e
    WHERE e.end_date >= CURDATE() ORDER BY e.start_date ASC LIMIT 5
")->fetchAll();

$recentNotices = $db->query("
    SELECT n.title, n.notice_date, n.audience, u.username AS created_by
    FROM notices n JOIN users u ON n.created_by = u.id
    ORDER BY n.notice_date DESC, n.id DESC LIMIT 5
")->fetchAll();

$recentActivity = $db->query("
    SELECT a.action, a.created_at, u.username, u.role
    FROM activity_log a LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.id DESC LIMIT 8
")->fetchAll();

// Attendance trend for the last 7 days (for the chart)
$trend = $db->query("
    SELECT attendance_date,
           SUM(status='present') AS present,
           SUM(status='absent') AS absent
    FROM attendance
    WHERE attendance_date >= CURDATE() - INTERVAL 6 DAY
    GROUP BY attendance_date ORDER BY attendance_date ASC
")->fetchAll();

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="row g-3 mb-2">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e8ecfb;color:#2f5fdc"><i class="fa-solid fa-user-graduate"></i></div>
            <div><div class="stat-value"><?= $totalStudents ?></div><div class="stat-label">Total Students</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e6f7ee;color:#1aa260"><i class="fa-solid fa-chalkboard-teacher"></i></div>
            <div><div class="stat-value"><?= $totalTeachers ?></div><div class="stat-label">Total Teachers</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff2e0;color:#d9822b"><i class="fa-solid fa-layer-group"></i></div>
            <div><div class="stat-value"><?= $totalClasses ?></div><div class="stat-label">Active Classes</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fdeaea;color:#d9364a"><i class="fa-solid fa-money-bill-wave"></i></div>
            <div><div class="stat-value"><?= format_money($outstanding) ?></div><div class="stat-label">Outstanding Fees</div></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-2">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e6f7ee;color:#1aa260"><i class="fa-solid fa-user-check"></i></div>
            <div><div class="stat-value"><?= $todayAttendance['present'] ?></div><div class="stat-label">Present Today</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fdeaea;color:#d9364a"><i class="fa-solid fa-user-xmark"></i></div>
            <div><div class="stat-value"><?= $todayAttendance['absent'] ?></div><div class="stat-label">Absent Today</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e8ecfb;color:#2f5fdc"><i class="fa-solid fa-sack-dollar"></i></div>
            <div><div class="stat-value"><?= format_money($feeStats['total_collected']) ?></div><div class="stat-label">Fees Collected</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff2e0;color:#d9822b"><i class="fa-solid fa-file-lines"></i></div>
            <div><div class="stat-value"><?= count($upcomingExams) ?></div><div class="stat-label">Upcoming Exams</div></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header">Attendance — Last 7 Days</div>
            <div class="card-body">
                <?php if (empty($trend)): ?>
                    <div class="empty-state py-3"><i class="fa-solid fa-clipboard-check"></i><p class="mb-0">No attendance recorded yet in this window.</p></div>
                <?php else: ?>
                    <canvas id="attendanceChart" height="110"></canvas>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                Recent Activity
            </div>
            <ul class="list-group list-group-flush">
                <?php if (empty($recentActivity)): ?>
                    <li class="list-group-item text-muted">No recent activity yet.</li>
                <?php endif; ?>
                <?php foreach ($recentActivity as $log): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><strong><?= e($log['username'] ?? 'System') ?></strong> <?= e($log['action']) ?></span>
                        <small class="text-muted"><?= e(date('d M, h:i A', strtotime($log['created_at']))) ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                Upcoming Exams
                <a href="<?= base_url('admin/exams.php') ?>" class="small">Manage</a>
            </div>
            <ul class="list-group list-group-flush">
                <?php if (empty($upcomingExams)): ?>
                    <li class="list-group-item text-muted">No upcoming exams scheduled.</li>
                <?php endif; ?>
                <?php foreach ($upcomingExams as $ex): ?>
                    <li class="list-group-item">
                        <div class="fw-semibold"><?= e($ex['name']) ?></div>
                        <small class="text-muted"><?= format_date($ex['start_date']) ?> &ndash; <?= format_date($ex['end_date']) ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                Recent Notices
                <a href="<?= base_url('admin/notices.php') ?>" class="small">Manage</a>
            </div>
            <ul class="list-group list-group-flush">
                <?php if (empty($recentNotices)): ?>
                    <li class="list-group-item text-muted">No notices posted yet.</li>
                <?php endif; ?>
                <?php foreach ($recentNotices as $n): ?>
                    <li class="list-group-item">
                        <div class="fw-semibold"><?= e($n['title']) ?></div>
                        <small class="text-muted"><?= format_date($n['notice_date']) ?> &middot; Audience: <?= e(ucfirst($n['audience'])) ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<?php if (!empty($trend)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('attendanceChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($r) => date('d M', strtotime($r['attendance_date'])), $trend)) ?>,
        datasets: [
            { label: 'Present', data: <?= json_encode(array_map(fn($r) => (int)$r['present'], $trend)) ?>, backgroundColor: '#2f5fdc' },
            { label: 'Absent', data: <?= json_encode(array_map(fn($r) => (int)$r['absent'], $trend)) ?>, backgroundColor: '#d9364a' }
        ]
    },
    options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});
</script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
