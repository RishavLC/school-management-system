<?php
require_once __DIR__ . '/../core/bootstrap.php';
$student = require_student_profile();

$pageTitle = 'Notifications';
$activeMenu = 'notifications';
$db = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf()) {
        flash('error', 'Your session expired. Please try again.');
    } elseif (isset($_POST['mark_read'])) {
        $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :u')
           ->execute(['id' => (int)$_POST['mark_read'], 'u' => Auth::id()]);
    } elseif (isset($_POST['mark_all_read'])) {
        $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :u')->execute(['u' => Auth::id()]);
    }
    redirect(base_url('student/notifications.php'));
}

$notifications = $db->prepare('SELECT * FROM notifications WHERE user_id = :u ORDER BY created_at DESC');
$notifications->execute(['u' => Auth::id()]);
$notifications = $notifications->fetchAll();

$icons = [
    'notice' => 'fa-bullhorn', 'homework' => 'fa-book-open', 'result' => 'fa-trophy',
    'fee' => 'fa-money-bill', 'attendance' => 'fa-clipboard-check', 'general' => 'fa-bell',
];

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        Notifications
        <form method="post"><?= Auth::csrfField() ?><button type="submit" name="mark_all_read" value="1" class="btn btn-sm btn-outline-secondary">Mark all as read</button></form>
    </div>
    <ul class="list-group list-group-flush">
        <?php foreach ($notifications as $n): ?>
            <li class="list-group-item d-flex justify-content-between align-items-start <?= $n['is_read'] ? '' : 'bg-light' ?>">
                <div class="d-flex gap-2">
                    <i class="fa-solid <?= $icons[$n['type']] ?? 'fa-bell' ?> text-muted mt-1"></i>
                    <div>
                        <div class="fw-semibold"><?= e($n['title']) ?> <?php if (!$n['is_read']): ?><span class="badge text-bg-primary ms-1">New</span><?php endif; ?></div>
                        <div class="small"><?= e($n['message']) ?></div>
                        <div class="small text-muted"><?= format_date($n['created_at']) ?>, <?= e(substr($n['created_at'], 11, 5)) ?></div>
                    </div>
                </div>
                <?php if (!$n['is_read']): ?>
                    <form method="post"><?= Auth::csrfField() ?><input type="hidden" name="mark_read" value="<?= (int)$n['id'] ?>"><button class="btn btn-sm btn-link">Mark read</button></form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        <?php if (empty($notifications)): ?><li class="list-group-item empty-state">No notifications yet.</li><?php endif; ?>
    </ul>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
