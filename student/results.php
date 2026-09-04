<?php
require_once __DIR__ . '/../core/bootstrap.php';
$student = require_student_profile();

$pageTitle = 'My Results';
$activeMenu = 'results';
$db = Database::connection();

$stmt = $db->prepare("
    SELECT e.id AS exam_id, e.name AS exam_name, e.status AS exam_status,
           subj.name AS subject_name, m.obtained_marks, m.is_absent, m.grade, es.full_marks, es.pass_marks
    FROM marks m
    JOIN exam_schedule es ON es.id = m.exam_schedule_id
    JOIN exams e ON e.id = es.exam_id
    JOIN subjects subj ON subj.id = es.subject_id
    WHERE m.student_id = :s
    ORDER BY e.start_date DESC, subj.name
");
$stmt->execute(['s' => $student['id']]);
$rows = $stmt->fetchAll();

$byExam = [];
foreach ($rows as $r) { $byExam[$r['exam_name']][] = $r; }

include __DIR__ . '/../includes/layout_start.php';
?>
<?php foreach ($byExam as $examName => $subjects): ?>
    <?php
    $totalFull = 0; $totalObtained = 0; $allPass = true;
    foreach ($subjects as $s) {
        $totalFull += (float)$s['full_marks'];
        $totalObtained += $s['is_absent'] ? 0 : (float)$s['obtained_marks'];
        if ($s['is_absent'] || (float)$s['obtained_marks'] < (float)$s['pass_marks']) $allPass = false;
    }
    $pct = $totalFull > 0 ? round($totalObtained / $totalFull * 100, 2) : 0;
    ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><?= e($examName) ?></span>
            <span class="<?= $allPass ? 'text-success' : 'text-danger' ?> fw-semibold small"><?= $allPass ? 'PASS' : 'FAIL' ?> &middot; <?= $pct ?>%</span>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Subject</th><th>Full Marks</th><th>Obtained</th><th>Grade</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($subjects as $s): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($s['subject_name']) ?></td>
                        <td><?= e($s['full_marks']) ?></td>
                        <td><?= $s['is_absent'] ? '-' : e($s['obtained_marks']) ?></td>
                        <td><?= e($s['grade'] ?? '-') ?></td>
                        <td><?= $s['is_absent'] ? status_badge('absent') : status_badge((float)$s['obtained_marks'] >= (float)$s['pass_marks'] ? 'pass' : 'fail') ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="table-light fw-semibold">
                    <td>Total</td><td><?= $totalFull ?></td><td><?= $totalObtained ?></td><td colspan="2">Percentage: <?= $pct ?>%</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>
<?php if (empty($byExam)): ?><div class="empty-state">No results published yet.</div><?php endif; ?>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
