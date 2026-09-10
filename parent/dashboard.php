<?php
require_once __DIR__ . '/../core/bootstrap.php';
$guardian = require_guardian_profile();
$children = guardian_children($guardian['id']);
$child = selected_child($children);

$pageTitle = 'Parent Dashboard';
$activeMenu = 'dashboard';
$db = Database::connection();

if (!$child) {
    include __DIR__ . '/../includes/layout_start.php';
    echo '<div class="empty-state">No children are linked to your account yet. Contact the school admin.</div>';
    include __DIR__ . '/../includes/layout_end.php';
    exit;
}

$sid = $child['id'];
$csId = $child['class_section_id'];

// Attendance %
$att = $db->prepare("SELECT status, COUNT(*) c FROM attendance WHERE student_id = :s GROUP BY status");
$att->execute(['s' => $sid]);
$counts = ['present' => 0, 'absent' => 0, 'late' => 0];
foreach ($att->fetchAll() as $r) { $counts[$r['status']] = (int)$r['c']; }
$total = array_sum($counts);
$attPct = $total > 0 ? round(($counts['present'] + $counts['late']) / $total * 100, 1) : null;

// Fees due
$feeRow = $db->prepare("
    SELECT COALESCE(SUM(amount - discount + fine), 0) billed,
           COALESCE((SELECT SUM(p.amount) FROM payments p JOIN student_fees sf2 ON sf2.id = p.student_fee_id WHERE sf2.student_id = sf.student_id), 0) paid
    FROM student_fees sf WHERE sf.student_id = :s
");
$feeRow->execute(['s' => $sid]);
$feeRow = $feeRow->fetch();
$due = $feeRow ? max(0, (float)$feeRow['billed'] - (float)$feeRow['paid']) : 0;

// Upcoming homework
$homework = [];
if ($csId) {
    $stmt = $db->prepare("
        SELECT h.title, h.due_date, subj.name AS subject_name
        FROM homework h JOIN subjects subj ON subj.id = h.subject_id
        WHERE h.class_section_id = :cs AND h.due_date >= CURDATE()
        ORDER BY h.due_date LIMIT 5
    ");
    $stmt->execute(['cs' => $csId]);
    $homework = $stmt->fetchAll();
}

// Recent results
$results = $db->prepare("
    SELECT e.name AS exam_name, subj.name AS subject_name, m.obtained_marks, m.grade, es.full_marks
    FROM marks m
    JOIN exam_schedule es ON es.id = m.exam_schedule_id
    JOIN exams e ON e.id = es.exam_id
    JOIN subjects subj ON subj.id = es.subject_id
    WHERE m.student_id = :s ORDER BY m.entered_at DESC LIMIT 5
");
$results->execute(['s' => $sid]);
$results = $results->fetchAll();

// Notices relevant to this child
$notices = $db->prepare("
    SELECT title, notice_date FROM notices
    WHERE audience IN ('everyone','parents') OR (audience = 'class' AND class_section_id = :cs)
    ORDER BY notice_date DESC LIMIT 5
");
$notices->execute(['cs' => $csId ?? 0]);
$notices = $notices->fetchAll();

// Early warning flags for this child
$flags = student_attention_flags($sid);

include __DIR__ . '/../includes/layout_start.php';
?>
<?php if (count($children) > 1): ?>
<div class="card mb-3">
    <div class="card-body">
        <div class="text-muted small mb-2">My Children</div>
        <div class="d-flex gap-2 flex-wrap">
            <?php foreach ($children as $c): ?>
                <a href="?student_id=<?= (int)$c['id'] ?>" class="btn btn-sm <?= $c['id'] === $sid ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= e($c['first_name'] . ' ' . $c['last_name']) ?>
                    <?php if ($c['class_name']): ?><span class="opacity-75">(<?= e($c['class_name'] . '-' . $c['section_name']) ?>)</span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body d-flex align-items-center gap-3">
        <div class="avatar-lg"><?= e(strtoupper(substr($child['first_name'],0,1))) ?></div>
        <div>
            <h5 class="mb-0"><?= e($child['first_name'] . ' ' . $child['last_name']) ?></h5>
            <div class="text-muted small">
                <?= e($child['admission_number']) ?>
                <?php if ($child['class_name']): ?> &middot; <?= e($child['class_name'] . ' - ' . $child['section_name']) ?> &middot; Roll <?= e($child['roll_number'] ?? '-') ?><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($flags)): ?>
<div class="alert alert-warning">
    <strong><i class="fa-solid fa-triangle-exclamation me-1"></i>Needs Attention:</strong>
    <?php foreach ($flags as $f): ?><span class="badge text-bg-light border me-1"><?= e($f['label']) ?></span><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row g-3 mb-1">
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:#e6f7ee;color:#1aa260"><i class="fa-solid fa-clipboard-check"></i></div>
        <div><div class="stat-value"><?= $attPct !== null ? $attPct.'%' : '-' ?></div><div class="stat-label">Attendance</div></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:#fdeaea;color:#d9364a"><i class="fa-solid fa-money-bill-wave"></i></div>
        <div><div class="stat-value"><?= format_money($due) ?></div><div class="stat-label">Fees Due</div></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:#e8ecfb;color:#2f5fdc"><i class="fa-solid fa-book-open"></i></div>
        <div><div class="stat-value"><?= count($homework) ?></div><div class="stat-label">Pending Homework</div></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:#fff2e0;color:#d9822b"><i class="fa-solid fa-bullhorn"></i></div>
        <div><div class="stat-value"><?= count($notices) ?></div><div class="stat-label">Notices</div></div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header">Pending Homework</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($homework as $h): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><span class="badge text-bg-light border me-1"><?= e($h['subject_name']) ?></span><?= e($h['title']) ?></span>
                        <small class="text-muted">Due <?= format_date($h['due_date']) ?></small>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($homework)): ?><li class="list-group-item empty-state">No pending homework.</li><?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header">Recent Results</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($results as $r): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?= e($r['subject_name']) ?> <small class="text-muted">(<?= e($r['exam_name']) ?>)</small></span>
                        <span><?= e($r['obtained_marks']) ?>/<?= e($r['full_marks']) ?> <span class="badge text-bg-light border"><?= e($r['grade']) ?></span></span>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($results)): ?><li class="list-group-item empty-state">No results published yet.</li><?php endif; ?>
            </ul>
        </div>
        <div class="card">
            <div class="card-header">Notices</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($notices as $n): ?>
                    <li class="list-group-item"><div class="fw-semibold"><?= e($n['title']) ?></div><small class="text-muted"><?= format_date($n['notice_date']) ?></small></li>
                <?php endforeach; ?>
                <?php if (empty($notices)): ?><li class="list-group-item empty-state">No notices yet.</li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
