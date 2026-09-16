<?php
require_once __DIR__ . '/../core/bootstrap.php';
$guardian = require_guardian_profile();
$children = guardian_children($guardian['id']);
$child = selected_child($children);

$pageTitle = 'Child Homework';
$activeMenu = 'homework';
$db = Database::connection();

if (!$child) {
    include __DIR__ . '/../includes/layout_start.php';
    echo '<div class="empty-state">No children are linked to your account yet. Contact the school admin.</div>';
    include __DIR__ . '/../includes/layout_end.php';
    exit;
}

$sid = $child['id'];
$csId = $child['class_section_id'] ?? null;

$homework = [];
if ($csId) {
    $stmt = $db->prepare("
        SELECT h.*, subj.name AS subject_name, CONCAT(t.first_name,' ',t.last_name) AS teacher_name,
            hs.status AS sub_status, hs.submission_text, hs.submitted_at, hs.marks_obtained, hs.teacher_remarks
        FROM homework h
        JOIN subjects subj ON subj.id = h.subject_id
        JOIN teachers t ON t.id = h.teacher_id
        LEFT JOIN homework_submissions hs ON hs.homework_id = h.id AND hs.student_id = :s
        WHERE h.class_section_id = :cs
        ORDER BY h.due_date DESC
    ");
    $stmt->execute(['s' => $sid, 'cs' => $csId]);
    $homework = $stmt->fetchAll();
}

include __DIR__ . '/../includes/layout_start.php';
include __DIR__ . '/../includes/child_selector.php';
?>
<div class="row g-3">
    <?php foreach ($homework as $h): $status = $h['sub_status'] ?: 'pending'; $overdue = !$h['sub_status'] && strtotime(date('Y-m-d')) > strtotime($h['due_date']); ?>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <h6 class="fw-bold mb-0"><?= e($h['title']) ?></h6>
                        <?= status_badge($overdue ? 'absent' : $status) ?>
                    </div>
                    <div class="small text-muted mb-2"><?= e($h['subject_name']) ?> &middot; <?= e($h['teacher_name']) ?></div>
                    <p class="small mb-2"><?= nl2br(e($h['description'] ?? '')) ?></p>
                    <div class="small text-muted mb-2">
                        Assigned <?= format_date($h['created_at']) ?> &middot; Due <?= format_date($h['due_date']) ?>
                        <?php if ($h['attachment']): ?> &middot; <a href="<?= e(base_url('assets/uploads/homework/' . $h['attachment'])) ?>" target="_blank">Download attachment</a><?php endif; ?>
                    </div>
                    <?php if ($h['sub_status']): ?>
                        <div class="border rounded p-2 bg-light">
                            <div class="small fw-semibold mb-1"><?= e($child['first_name']) ?>'s submission (<?= format_date($h['submitted_at']) ?>)</div>
                            <div class="small mb-1"><?= nl2br(e($h['submission_text'])) ?></div>
                            <?php if ($h['sub_status'] === 'checked'): ?>
                                <div class="small text-success">Marks: <?= e($h['marks_obtained'] ?? '-') ?><?php if ($h['teacher_remarks']): ?> &middot; <?= e($h['teacher_remarks']) ?><?php endif; ?></div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="small text-muted"><i class="fa-solid fa-circle-info me-1"></i>Not yet submitted.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($homework)): ?><div class="col-12"><div class="empty-state">No homework assigned yet.</div></div><?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
