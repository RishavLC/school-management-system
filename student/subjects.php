<?php
require_once __DIR__ . '/../core/bootstrap.php';
$student = require_student_profile();

$pageTitle = 'My Subjects';
$activeMenu = 'subjects';
$db = Database::connection();
$csId = $student['enrollment']['class_section_id'] ?? null;

$subjects = [];
if ($csId) {
    $stmt = $db->prepare("
        SELECT DISTINCT subj.name, subj.code, subj.description, CONCAT(t.first_name,' ',t.last_name) AS teacher_name
        FROM teacher_assignments ta
        JOIN subjects subj ON subj.id = ta.subject_id
        JOIN teachers t ON t.id = ta.teacher_id
        WHERE ta.class_section_id = :cs
        ORDER BY subj.name
    ");
    $stmt->execute(['cs' => $csId]);
    $subjects = $stmt->fetchAll();
}

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="card mb-3">
    <div class="card-body">
        <strong>Class:</strong> <?= $student['enrollment'] ? e($student['enrollment']['class_name'] . ' - ' . $student['enrollment']['section_name']) : '-' ?>
        &nbsp;&middot;&nbsp; <strong>Academic Year:</strong> <?= $student['enrollment'] ? e($student['enrollment']['year_name']) : '-' ?>
    </div>
</div>
<div class="row g-3">
    <?php foreach ($subjects as $s): ?>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <h6 class="fw-bold mb-1"><?= e($s['name']) ?></h6>
                        <span class="badge text-bg-light border"><?= e($s['code']) ?></span>
                    </div>
                    <p class="text-muted small mb-2"><?= e($s['description'] ?? '') ?></p>
                    <div class="small"><i class="fa-solid fa-chalkboard-user me-1 text-muted"></i><?= e($s['teacher_name']) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($subjects)): ?><div class="col-12"><div class="empty-state">No subjects assigned to your class yet.</div></div><?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
