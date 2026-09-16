<?php
require_once __DIR__ . '/../core/bootstrap.php';
$guardian = require_guardian_profile();
$children = guardian_children($guardian['id']);
$child = selected_child($children);

$pageTitle = 'Child Attendance';
$activeMenu = 'attendance';
$db = Database::connection();

if (!$child) {
    include __DIR__ . '/../includes/layout_start.php';
    echo '<div class="empty-state">No children are linked to your account yet. Contact the school admin.</div>';
    include __DIR__ . '/../includes/layout_end.php';
    exit;
}

$sid = $child['id'];
$records = $db->prepare("SELECT * FROM attendance WHERE student_id = :s ORDER BY attendance_date DESC LIMIT 60");
$records->execute(['s' => $sid]);
$records = $records->fetchAll();

$summary = $db->prepare("SELECT status, COUNT(*) c FROM attendance WHERE student_id = :s GROUP BY status");
$summary->execute(['s' => $sid]);
$counts = ['present' => 0, 'absent' => 0, 'late' => 0];
foreach ($summary->fetchAll() as $row) { $counts[$row['status']] = (int) $row['c']; }
$total = array_sum($counts);
$pct = $total > 0 ? round(($counts['present'] + $counts['late']) / $total * 100, 1) : null;

include __DIR__ . '/../includes/layout_start.php';
include __DIR__ . '/../includes/child_selector.php';
?>
<div class="row g-3 mb-1">
    <div class="col-md-3"><div class="stat-card"><div class="stat-icon" style="background:#e6f7ee;color:#1aa260"><i class="fa-solid fa-user-check"></i></div><div><div class="stat-value"><?= $counts['present'] ?></div><div class="stat-label">Present</div></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-icon" style="background:#fdeaea;color:#d9364a"><i class="fa-solid fa-user-xmark"></i></div><div><div class="stat-value"><?= $counts['absent'] ?></div><div class="stat-label">Absent</div></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-icon" style="background:#fff2e0;color:#d9822b"><i class="fa-solid fa-user-clock"></i></div><div><div class="stat-value"><?= $counts['late'] ?></div><div class="stat-label">Late</div></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-icon" style="background:#e8ecfb;color:#2f5fdc"><i class="fa-solid fa-percent"></i></div><div><div class="stat-value"><?= $pct !== null ? $pct.'%' : '-' ?></div><div class="stat-label">Attendance Rate</div></div></div></div>
</div>

<div class="card">
    <div class="card-header"><?= e($child['first_name']) ?>'s Attendance History</div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Date</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($records as $r): ?>
                <tr><td><?= format_date($r['attendance_date']) ?></td><td><?= status_badge($r['status']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (empty($records)): ?><tr><td colspan="2" class="empty-state">No attendance recorded yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
