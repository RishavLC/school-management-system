<?php
require_once __DIR__ . '/../core/bootstrap.php';
$student = require_student_profile();

$pageTitle = 'Notices';
$activeMenu = 'notices';
$db = Database::connection();
$csId = $student['enrollment']['class_section_id'] ?? null;

$stmt = $db->prepare("
    SELECT * FROM notices
    WHERE audience IN ('everyone','students') OR (audience = 'class' AND class_section_id = :cs)
    ORDER BY notice_date DESC
");
$stmt->execute(['cs' => $csId ?? 0]);
$notices = $stmt->fetchAll();

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="card">
    <ul class="list-group list-group-flush">
        <?php foreach ($notices as $n): ?>
            <li class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="fw-semibold"><?= e($n['title']) ?></div>
                    <span class="badge text-bg-light border text-capitalize"><?= e($n['audience']) ?></span>
                </div>
                <div class="small text-muted mb-1"><?= format_date($n['notice_date']) ?></div>
                <p class="mb-0 small"><?= nl2br(e($n['description'] ?? '')) ?></p>
                <?php if ($n['attachment']): ?><a href="<?= e(base_url('assets/uploads/notices/' . $n['attachment'])) ?>" target="_blank" class="small">Download attachment</a><?php endif; ?>
            </li>
        <?php endforeach; ?>
        <?php if (empty($notices)): ?><li class="list-group-item empty-state">No notices yet.</li><?php endif; ?>
    </ul>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
