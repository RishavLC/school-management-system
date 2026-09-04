<?php
require_once __DIR__ . '/../core/bootstrap.php';
$student = require_student_profile();

$pageTitle = 'Learning Materials';
$activeMenu = 'materials';
$db = Database::connection();
$csId = $student['enrollment']['class_section_id'] ?? null;

$materials = [];
if ($csId) {
    $stmt = $db->prepare("
        SELECT lm.*, subj.name AS subject_name, CONCAT(t.first_name,' ',t.last_name) AS teacher_name
        FROM learning_materials lm
        JOIN subjects subj ON subj.id = lm.subject_id
        JOIN teachers t ON t.id = lm.teacher_id
        WHERE lm.class_section_id = :cs
        ORDER BY lm.created_at DESC
    ");
    $stmt->execute(['cs' => $csId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as $r) { $materials[$r['subject_name']][] = $r; }
}

include __DIR__ . '/../includes/layout_start.php';
?>
<?php foreach ($materials as $subjectName => $rows): ?>
    <div class="card mb-3">
        <div class="card-header"><?= e($subjectName) ?></div>
        <ul class="list-group list-group-flush">
            <?php foreach ($rows as $m): ?>
                <li class="list-group-item d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-semibold"><i class="fa-solid fa-file-lines me-1 text-muted"></i><?= e($m['title']) ?></div>
                        <div class="small text-muted"><?= e($m['description'] ?? '') ?></div>
                        <div class="small text-muted">By <?= e($m['teacher_name']) ?> &middot; <?= format_date($m['created_at']) ?></div>
                    </div>
                    <?php if ($m['file_path']): ?>
                        <a href="<?= e(base_url('assets/uploads/materials/' . $m['file_path'])) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-download me-1"></i>Download</a>
                    <?php else: ?>
                        <span class="badge text-bg-light border">Notes only</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endforeach; ?>
<?php if (empty($materials)): ?><div class="empty-state">No learning materials uploaded yet.</div><?php endif; ?>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
