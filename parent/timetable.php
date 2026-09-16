<?php
require_once __DIR__ . '/../core/bootstrap.php';
$guardian = require_guardian_profile();
$children = guardian_children($guardian['id']);
$child = selected_child($children);

$pageTitle = 'Child Timetable';
$activeMenu = 'timetable';
$db = Database::connection();

if (!$child) {
    include __DIR__ . '/../includes/layout_start.php';
    echo '<div class="empty-state">No children are linked to your account yet. Contact the school admin.</div>';
    include __DIR__ . '/../includes/layout_end.php';
    exit;
}

$csId = $child['class_section_id'] ?? null;
$days = [1 => 'Sunday', 2 => 'Monday', 3 => 'Tuesday', 4 => 'Wednesday', 5 => 'Thursday', 6 => 'Friday'];
$byDay = array_fill_keys(array_keys($days), []);

if ($csId) {
    $stmt = $db->prepare("
        SELECT tt.*, subj.name AS subject_name, CONCAT(t.first_name,' ',t.last_name) AS teacher_name
        FROM timetable tt
        JOIN subjects subj ON subj.id = tt.subject_id
        JOIN teachers t ON t.id = tt.teacher_id
        WHERE tt.class_section_id = :cs
        ORDER BY tt.day_of_week, tt.start_time
    ");
    $stmt->execute(['cs' => $csId]);
    foreach ($stmt->fetchAll() as $row) {
        if (isset($byDay[$row['day_of_week']])) $byDay[$row['day_of_week']][] = $row;
    }
}

$todayDow = (int) date('w') + 1;

include __DIR__ . '/../includes/layout_start.php';
include __DIR__ . '/../includes/child_selector.php';
?>
<?php if (!$csId): ?>
    <div class="empty-state"><?= e($child['first_name']) ?> is not enrolled in a class for the current academic year yet.</div>
<?php else: ?>
<ul class="nav nav-tabs mb-3" role="tablist">
    <?php $first = true; foreach ($days as $dow => $label): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $dow === $todayDow ? 'active' : ($first && $todayDow > 6 ? 'active' : '') ?>" data-bs-toggle="tab" data-bs-target="#day<?= $dow ?>" type="button"><?= e($label) ?></button>
        </li>
    <?php $first = false; endforeach; ?>
</ul>
<div class="tab-content">
    <?php $first = true; foreach ($days as $dow => $label): ?>
        <div class="tab-pane fade <?= ($dow === $todayDow || ($first && $todayDow > 6)) ? 'show active' : '' ?>" id="day<?= $dow ?>">
            <div class="card">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Period</th><th>Subject</th><th>Teacher</th><th>Room</th></tr></thead>
                        <tbody>
                        <?php foreach ($byDay[$dow] as $i => $p): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td class="fw-semibold"><?= e($p['subject_name']) ?></td>
                                <td><?= e($p['teacher_name']) ?></td>
                                <td><?= e($p['room'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($byDay[$dow])): ?><tr><td colspan="4" class="empty-state">No periods scheduled.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php $first = false; endforeach; ?>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
