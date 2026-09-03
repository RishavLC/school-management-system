<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireArea('admin');

$pageTitle = 'Attendance Reports';
$activeMenu = 'attendance';
$db = Database::connection();
$activeYear = $db->query("SELECT * FROM academic_years WHERE is_active=1 LIMIT 1")->fetch();

$classSections = [];
if ($activeYear) {
    $classSections = $db->prepare("
        SELECT cs.id, c.name AS class_name, sec.name AS section_name
        FROM class_sections cs JOIN classes c ON c.id=cs.class_id JOIN sections sec ON sec.id=cs.section_id
        WHERE cs.academic_year_id = :y ORDER BY c.sort_order, sec.name
    ");
    $classSections->execute(['y' => $activeYear['id']]);
    $classSections = $classSections->fetchAll();
}

$csFilter = (int)($_GET['class_section_id'] ?? ($classSections[0]['id'] ?? 0));
$dateFrom = $_GET['from'] ?? date('Y-m-d', strtotime('-6 days'));
$dateTo   = $_GET['to'] ?? date('Y-m-d');

$rows = [];
$summary = ['present' => 0, 'absent' => 0, 'late' => 0];
if ($csFilter) {
    $stmt = $db->prepare("
        SELECT s.admission_number, s.first_name, s.last_name,
               SUM(a.status='present') AS present, SUM(a.status='absent') AS absent, SUM(a.status='late') AS late,
               COUNT(*) AS total
        FROM attendance a
        JOIN students s ON s.id = a.student_id
        WHERE a.class_section_id = :cs AND a.attendance_date BETWEEN :from AND :to
        GROUP BY s.id ORDER BY s.first_name
    ");
    $stmt->execute(['cs' => $csFilter, 'from' => $dateFrom, 'to' => $dateTo]);
    $rows = $stmt->fetchAll();

    $sumStmt = $db->prepare("
        SELECT status, COUNT(*) c FROM attendance WHERE class_section_id = :cs AND attendance_date BETWEEN :from AND :to GROUP BY status
    ");
    $sumStmt->execute(['cs' => $csFilter, 'from' => $dateFrom, 'to' => $dateTo]);
    foreach ($sumStmt->fetchAll() as $r) { $summary[$r['status']] = (int)$r['c']; }
}

include __DIR__ . '/../includes/layout_start.php';
?>
<form class="row g-2 align-items-end mb-3" method="get">
    <div class="col-md-4">
        <label class="form-label small">Class - Section</label>
        <select name="class_section_id" class="form-select">
            <?php foreach ($classSections as $cs): ?>
                <option value="<?= (int)$cs['id'] ?>" <?= $csFilter === (int)$cs['id'] ? 'selected' : '' ?>><?= e($cs['class_name'] . ' - ' . $cs['section_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label small">From</label>
        <input type="date" name="from" class="form-control" value="<?= e($dateFrom) ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label small">To</label>
        <input type="date" name="to" class="form-control" value="<?= e($dateTo) ?>">
    </div>
    <div class="col-md-2">
        <button class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i>Filter</button>
    </div>
</form>

<div class="row g-3 mb-1">
    <div class="col-md-4"><div class="stat-card"><div class="stat-icon" style="background:#e6f7ee;color:#1aa260"><i class="fa-solid fa-user-check"></i></div><div><div class="stat-value"><?= $summary['present'] ?></div><div class="stat-label">Present (period)</div></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-icon" style="background:#fdeaea;color:#d9364a"><i class="fa-solid fa-user-xmark"></i></div><div><div class="stat-value"><?= $summary['absent'] ?></div><div class="stat-label">Absent (period)</div></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-icon" style="background:#fff2e0;color:#d9822b"><i class="fa-solid fa-user-clock"></i></div><div><div class="stat-value"><?= $summary['late'] ?></div><div class="stat-label">Late (period)</div></div></div></div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        Student-wise Attendance — <?= e($dateFrom) ?> to <?= e($dateTo) ?>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-print me-1"></i>Print</button>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Admission #</th><th>Name</th><th>Present</th><th>Absent</th><th>Late</th><th>Total Days</th><th>Attendance %</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $pct = $r['total'] ? round(($r['present']+$r['late'])/$r['total']*100,1) : 0; ?>
                <tr>
                    <td><?= e($r['admission_number']) ?></td>
                    <td><?= e($r['first_name'].' '.$r['last_name']) ?></td>
                    <td><?= (int)$r['present'] ?></td>
                    <td><?= (int)$r['absent'] ?></td>
                    <td><?= (int)$r['late'] ?></td>
                    <td><?= (int)$r['total'] ?></td>
                    <td><span class="badge text-bg-<?= $pct >= 75 ? 'success' : 'warning' ?>"><?= $pct ?>%</span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?><tr><td colspan="7" class="empty-state">No attendance recorded for this class and period.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
