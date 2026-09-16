<?php
require_once __DIR__ . '/../core/bootstrap.php';
$guardian = require_guardian_profile();
$children = guardian_children($guardian['id']);
$child = selected_child($children);

$pageTitle = 'Child Results';
$activeMenu = 'results';
$db = Database::connection();

if (!$child) {
    include __DIR__ . '/../includes/layout_start.php';
    echo '<div class="empty-state">No children are linked to your account yet. Contact the school admin.</div>';
    include __DIR__ . '/../includes/layout_end.php';
    exit;
}

$sid = $child['id'];
$stmt = $db->prepare("
    SELECT e.name AS exam_name, e.status AS exam_status,
           subj.name AS subject_name, m.obtained_marks, m.is_absent, m.grade, es.full_marks, es.pass_marks
    FROM marks m
    JOIN exam_schedule es ON es.id = m.exam_schedule_id
    JOIN exams e ON e.id = es.exam_id
    JOIN subjects subj ON subj.id = es.subject_id
    WHERE m.student_id = :s AND e.status = 'result_published'
    ORDER BY e.start_date DESC, subj.name
");
$stmt->execute(['s' => $sid]);
$rows = $stmt->fetchAll();

$byExam = [];
foreach ($rows as $r) { $byExam[$r['exam_name']][] = $r; }

include __DIR__ . '/../includes/layout_start.php';
include __DIR__ . '/../includes/child_selector.php';
?>
<?php if (empty($byExam)): ?>
    <div class="card"><div class="empty-state py-5"><i class="fa-solid fa-file-lines"></i><p class="mb-0">No results published yet for <?= e($child['first_name']) ?>.</p></div></div>
<?php endif; ?>
<?php foreach ($byExam as $examName => $subjects): ?>
    <?php
    $totalFull = 0; $totalObtained = 0; $allPass = true;
    foreach ($subjects as $s) {
        $totalFull += (float) $s['full_marks'];
        $totalObtained += $s['is_absent'] ? 0 : (float) $s['obtained_marks'];
        if ($s['is_absent'] || (float) $s['obtained_marks'] < (float) $s['pass_marks']) $allPass = false;
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
                <thead><tr><th>Subject</th><th>Full Marks</th><th>Obtained</th><th>Grade</th></tr></thead>
                <tbody>
                <?php foreach ($subjects as $s): ?>
                    <tr>
                        <td><?= e($s['subject_name']) ?></td>
                        <td><?= e($s['full_marks']) ?></td>
                        <td><?= $s['is_absent'] ? 'Absent' : e($s['obtained_marks']) ?></td>
                        <td><span class="badge text-bg-light border"><?= e($s['grade']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
