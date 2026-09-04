<?php
require_once __DIR__ . '/../core/bootstrap.php';
$student = require_student_profile();

$pageTitle = 'My Attendance';
$activeMenu = 'attendance';
$db = Database::connection();

$todayStatus = $db->prepare('SELECT status FROM attendance WHERE student_id = :s AND attendance_date = CURDATE()');
$todayStatus->execute(['s' => $student['id']]);
$todayStatus = $todayStatus->fetchColumn();

$history = $db->prepare('SELECT attendance_date, status FROM attendance WHERE student_id = :s ORDER BY attendance_date DESC LIMIT 60');
$history->execute(['s' => $student['id']]);
$history = $history->fetchAll();

// Monthly breakdown
$monthly = $db->prepare("
    SELECT DATE_FORMAT(attendance_date, '%Y-%m') AS ym, DATE_FORMAT(attendance_date, '%M %Y') AS label,
        SUM(status='present') AS present, SUM(status='absent') AS absent, SUM(status='late') AS late, COUNT(*) AS total
    FROM attendance WHERE student_id = :s
    GROUP BY ym ORDER BY ym DESC
");
$monthly->execute(['s' => $student['id']]);
$monthly = $monthly->fetchAll();

$overall = $db->prepare("SELECT status, COUNT(*) c FROM attendance WHERE student_id = :s GROUP BY status");
$overall->execute(['s' => $student['id']]);
$ov = ['present' => 0, 'absent' => 0, 'late' => 0];
foreach ($overall->fetchAll() as $r) { $ov[$r['status']] = (int)$r['c']; }
$ovTotal = array_sum($ov);
$ovPct = $ovTotal > 0 ? round(($ov['present'] + $ov['late']) / $ovTotal * 100, 1) : null;

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="row g-3 mb-1">
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:#e8ecfb;color:#2f5fdc"><i class="fa-solid fa-calendar-day"></i></div>
        <div><div class="stat-value"><?= $todayStatus ? ucfirst($todayStatus) : 'Not marked' ?></div><div class="stat-label">Today</div></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:#e6f7ee;color:#1aa260"><i class="fa-solid fa-circle-check"></i></div>
        <div><div class="stat-value"><?= $ov['present'] ?></div><div class="stat-label">Present Days</div></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:#fdeaea;color:#d9364a"><i class="fa-solid fa-circle-xmark"></i></div>
        <div><div class="stat-value"><?= $ov['absent'] ?></div><div class="stat-label">Absent Days</div></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:#fff2e0;color:#d9822b"><i class="fa-solid fa-percent"></i></div>
        <div><div class="stat-value"><?= $ovPct !== null ? $ovPct . '%' : '-' ?></div><div class="stat-label">Attendance %</div></div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Monthly Summary</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Month</th><th>Present</th><th>Absent</th><th>Late</th><th>%</th></tr></thead>
                    <tbody>
                    <?php foreach ($monthly as $m): $pct = $m['total'] > 0 ? round(($m['present']+$m['late'])/$m['total']*100) : 0; ?>
                        <tr>
                            <td><?= e($m['label']) ?></td>
                            <td><?= (int)$m['present'] ?></td>
                            <td><?= (int)$m['absent'] ?></td>
                            <td><?= (int)$m['late'] ?></td>
                            <td><?= $pct ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($monthly)): ?><tr><td colspan="5" class="empty-state">No attendance recorded yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Daily History</div>
            <div class="table-responsive" style="max-height:480px;overflow:auto">
                <table class="table mb-0">
                    <thead><tr><th>Date</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($history as $h): ?>
                        <tr><td><?= format_date($h['attendance_date']) ?></td><td><?= status_badge($h['status']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (empty($history)): ?><tr><td colspan="2" class="empty-state">No attendance recorded yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
