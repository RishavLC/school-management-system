<?php
require_once __DIR__ . '/../core/bootstrap.php';
$student = require_student_profile();

$pageTitle = 'Exams';
$activeMenu = 'exams';
$db = Database::connection();
$csId = $student['enrollment']['class_section_id'] ?? null;

$exams = [];
if ($csId) {
    $stmt = $db->prepare("
        SELECT es.*, subj.name AS subject_name, e.name AS exam_name, e.status AS exam_status
        FROM exam_schedule es
        JOIN exams e ON e.id = es.exam_id
        JOIN subjects subj ON subj.id = es.subject_id
        WHERE es.class_section_id = :cs
        ORDER BY es.exam_date, es.start_time
    ");
    $stmt->execute(['cs' => $csId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as $r) {
        $exams[$r['exam_name']][] = $r;
    }
}

include __DIR__ . '/../includes/layout_start.php';
?>
<?php foreach ($exams as $examName => $rows): ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><?= e($examName) ?></span>
            <?= status_badge($rows[0]['exam_status']) ?>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Subject</th><th>Date</th><th>Time</th><th>Room</th><th>Full Marks</th><th>Pass Marks</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($r['subject_name']) ?></td>
                        <td><?= format_date($r['exam_date']) ?></td>
                        <td><?= e(substr($r['start_time'],0,5)) ?> - <?= e(substr($r['end_time'],0,5)) ?></td>
                        <td><?= e($r['room'] ?? '-') ?></td>
                        <td><?= e($r['full_marks']) ?></td>
                        <td><?= e($r['pass_marks']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>
<?php if (empty($exams)): ?><div class="empty-state">No exams scheduled for your class yet.</div><?php endif; ?>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
