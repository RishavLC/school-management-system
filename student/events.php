<?php
require_once __DIR__ . '/../core/bootstrap.php';
$student = require_student_profile();

$pageTitle = 'School Events';
$activeMenu = 'events';
$db = Database::connection();

$events = $db->query("
    SELECT * FROM school_events
    WHERE audience IN ('everyone','students')
    ORDER BY event_date ASC
")->fetchAll();

$upcoming = array_filter($events, fn($e) => $e['event_date'] >= date('Y-m-d'));
$past = array_filter($events, fn($e) => $e['event_date'] < date('Y-m-d'));

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="card mb-3">
    <div class="card-header">Upcoming Events</div>
    <ul class="list-group list-group-flush">
        <?php foreach ($upcoming as $ev): ?>
            <li class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="fw-semibold"><?= e($ev['title']) ?></div>
                    <span class="text-muted small"><?= format_date($ev['event_date']) ?><?= $ev['end_date'] ? ' - ' . format_date($ev['end_date']) : '' ?></span>
                </div>
                <p class="mb-0 small text-muted"><?= nl2br(e($ev['description'] ?? '')) ?></p>
                <?php if ($ev['location']): ?><div class="small"><i class="fa-solid fa-location-dot me-1"></i><?= e($ev['location']) ?></div><?php endif; ?>
            </li>
        <?php endforeach; ?>
        <?php if (empty($upcoming)): ?><li class="list-group-item empty-state">No upcoming events.</li><?php endif; ?>
    </ul>
</div>
<?php if (!empty($past)): ?>
<div class="card">
    <div class="card-header">Past Events</div>
    <ul class="list-group list-group-flush">
        <?php foreach (array_reverse($past) as $ev): ?>
            <li class="list-group-item text-muted">
                <div class="d-flex justify-content-between"><span><?= e($ev['title']) ?></span><span class="small"><?= format_date($ev['event_date']) ?></span></div>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
