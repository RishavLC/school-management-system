<?php
require_once __DIR__ . '/core/bootstrap.php';
if (!Auth::check()) redirect(base_url('login.php'));

$pageTitle = 'Change Password';
$activeMenu = 'change_password';
$db = Database::connection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute(['id' => Auth::id()]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New password and confirmation do not match.';
        } else {
            $db->prepare('UPDATE users SET password_hash = :h, must_change_password = 0 WHERE id = :id')
               ->execute(['h' => password_hash($new, PASSWORD_DEFAULT), 'id' => Auth::id()]);
            Auth::log(Auth::id(), 'Changed password');
            flash('success', 'Password updated successfully.');
            redirect(base_url('change_password.php'));
        }
    }
}

include __DIR__ . '/includes/layout_start.php';
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Change Password</div>
            <div class="card-body">
                <?php foreach ($errors as $err): ?>
                    <div class="alert alert-danger py-2"><?= e($err) ?></div>
                <?php endforeach; ?>
                <form method="post">
                    <?= Auth::csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="8">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="8">
                    </div>
                    <button class="btn btn-primary">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/layout_end.php'; ?>
