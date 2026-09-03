<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireArea('admin');

$id = (int)($_GET['id'] ?? 0);
$db = Database::connection();

$stmt = $db->prepare('SELECT * FROM students WHERE id = :id');
$stmt->execute(['id' => $id]);
$student = $stmt->fetch();

if (!$student) {
    http_response_code(404);
    require __DIR__ . '/../includes/error_page.php';
    exit;
}

$pageTitle = 'Student Profile';
$activeMenu = 'students';

// Academic history — every enrollment row, oldest first (never overwritten)
$history = $db->prepare("
    SELECT se.*, ay.name AS year_name, c.name AS class_name, sec.name AS section_name
    FROM student_enrollments se
    JOIN academic_years ay ON ay.id = se.academic_year_id
    JOIN class_sections cs ON cs.id = se.class_section_id
    JOIN classes c ON c.id = cs.class_id
    JOIN sections sec ON sec.id = cs.section_id
    WHERE se.student_id = :id
    ORDER BY ay.start_date ASC
");
$history->execute(['id' => $id]);
$history = $history->fetchAll();
$current = $history[count($history) - 1] ?? null;

// Guardians
$guardians = $db->prepare("
    SELECT g.*, sg.relationship, sg.is_primary
    FROM student_guardians sg JOIN guardians g ON g.id = sg.guardian_id
    WHERE sg.student_id = :id
");
$guardians->execute(['id' => $id]);
$guardians = $guardians->fetchAll();

// Attendance summary (current academic year)
$attSummary = $db->prepare("
    SELECT status, COUNT(*) c FROM attendance WHERE student_id = :id GROUP BY status
");
$attSummary->execute(['id' => $id]);
$att = ['present' => 0, 'absent' => 0, 'late' => 0];
foreach ($attSummary->fetchAll() as $row) { $att[$row['status']] = (int)$row['c']; }
$attTotal = array_sum($att);
$attPct = $attTotal > 0 ? round(($att['present'] + $att['late']) / $attTotal * 100, 1) : null;

// Fee summary
$fees = $db->prepare("
    SELECT sf.*, ft.name AS fee_type_name,
        (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.student_fee_id = sf.id) AS paid
    FROM student_fees sf JOIN fee_types ft ON ft.id = sf.fee_type_id
    WHERE sf.student_id = :id ORDER BY sf.due_date
");
$fees->execute(['id' => $id]);
$fees = $fees->fetchAll();
$totalBilled = 0; $totalPaid = 0;
foreach ($fees as $f) {
    $totalBilled += $f['amount'] - $f['discount'] + $f['fine'];
    $totalPaid += $f['paid'];
}

// Results summary
$results = $db->prepare("
    SELECT e.name AS exam_name, subj.name AS subject_name, m.obtained_marks, m.grade, es.full_marks, es.pass_marks
    FROM marks m
    JOIN exam_schedule es ON es.id = m.exam_schedule_id
    JOIN exams e ON e.id = es.exam_id
    JOIN subjects subj ON subj.id = es.subject_id
    WHERE m.student_id = :id
    ORDER BY e.start_date DESC, subj.name
");
$results->execute(['id' => $id]);
$results = $results->fetchAll();

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body text-center">
                <div class="avatar-lg mx-auto mb-2"><?= e(strtoupper(substr($student['first_name'],0,1))) ?></div>
                <h5 class="mb-0"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></h5>
                <div class="text-muted small mb-2"><?= e($student['admission_number']) ?></div>
                <?= status_badge($student['status']) ?>
                <?php if ($current): ?>
                    <div class="mt-2"><span class="badge text-bg-light border"><?= e($current['class_name'] . ' - ' . $current['section_name']) ?> &middot; Roll <?= e($current['roll_number'] ?? '-') ?></span></div>
                <?php endif; ?>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Date of Birth</span> <?= format_date($student['dob']) ?></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Gender</span> <?= e(ucfirst($student['gender'])) ?></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Blood Group</span> <?= e($student['blood_group'] ?? '-') ?></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Phone</span> <?= e($student['phone'] ?? '-') ?></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Address</span> <span class="text-end"><?= e($student['address'] ?? '-') ?></span></li>
            </ul>
        </div>

        <div class="card">
            <div class="card-header">Parent / Guardian</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($guardians as $g): ?>
                    <li class="list-group-item">
                        <div class="fw-semibold"><?= e($g['first_name'] . ' ' . $g['last_name']) ?> <span class="text-muted small">(<?= e($g['relationship']) ?>)</span></div>
                        <div class="small text-muted"><i class="fa-solid fa-phone me-1"></i><?= e($g['phone'] ?? '-') ?></div>
                        <div class="small text-muted"><i class="fa-solid fa-envelope me-1"></i><?= e($g['email'] ?? '-') ?></div>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($guardians)): ?><li class="list-group-item text-muted">No guardian on record.</li><?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="row g-3 mb-1">
            <div class="col-md-4">
                <div class="stat-card"><div class="stat-icon" style="background:#e6f7ee;color:#1aa260"><i class="fa-solid fa-clipboard-check"></i></div>
                <div><div class="stat-value"><?= $attPct !== null ? $attPct . '%' : '-' ?></div><div class="stat-label">Attendance</div></div></div>
            </div>
            <div class="col-md-4">
                <div class="stat-card"><div class="stat-icon" style="background:#fdeaea;color:#d9364a"><i class="fa-solid fa-money-bill-wave"></i></div>
                <div><div class="stat-value"><?= format_money(max(0,$totalBilled-$totalPaid)) ?></div><div class="stat-label">Outstanding Fees</div></div></div>
            </div>
            <div class="col-md-4">
                <div class="stat-card"><div class="stat-icon" style="background:#e8ecfb;color:#2f5fdc"><i class="fa-solid fa-file-lines"></i></div>
                <div><div class="stat-value"><?= count($results) ?></div><div class="stat-label">Results Recorded</div></div></div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Academic History</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Academic Year</th><th>Class</th><th>Roll #</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach (array_reverse($history) as $h): ?>
                        <tr>
                            <td><?= e($h['year_name']) ?></td>
                            <td><?= e($h['class_name'] . ' - ' . $h['section_name']) ?></td>
                            <td><?= e($h['roll_number'] ?? '-') ?></td>
                            <td><?= status_badge($h['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($history)): ?><tr><td colspan="4" class="empty-state">Not enrolled in any academic year yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Attendance Summary</div>
            <div class="card-body">
                <div class="d-flex gap-4 flex-wrap">
                    <div><span class="badge text-bg-success">Present</span> <?= $att['present'] ?></div>
                    <div><span class="badge text-bg-danger">Absent</span> <?= $att['absent'] ?></div>
                    <div><span class="badge text-bg-warning">Late</span> <?= $att['late'] ?></div>
                    <div class="text-muted">Total days recorded: <?= $attTotal ?></div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Fee Summary</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Fee Type</th><th>Amount</th><th>Paid</th><th>Outstanding</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($fees as $f): $net = $f['amount']-$f['discount']+$f['fine']; ?>
                        <tr>
                            <td><?= e($f['fee_type_name']) ?></td>
                            <td><?= format_money($net) ?></td>
                            <td><?= format_money($f['paid']) ?></td>
                            <td><?= format_money(max(0,$net-$f['paid'])) ?></td>
                            <td><?= status_badge($f['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($fees)): ?><tr><td colspan="5" class="empty-state">No fee records yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Exam Results</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Examination</th><th>Subject</th><th>Marks</th><th>Grade</th><th>Result</th></tr></thead>
                    <tbody>
                    <?php foreach ($results as $r): $pass = $r['obtained_marks'] >= $r['pass_marks']; ?>
                        <tr>
                            <td><?= e($r['exam_name']) ?></td>
                            <td><?= e($r['subject_name']) ?></td>
                            <td><?= e($r['obtained_marks']) ?> / <?= e($r['full_marks']) ?></td>
                            <td><span class="badge text-bg-light border"><?= e($r['grade']) ?></span></td>
                            <td><?= $pass ? '<span class="badge text-bg-success">Pass</span>' : '<span class="badge text-bg-danger">Fail</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($results)): ?><tr><td colspan="5" class="empty-state">No results published yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
