<?php
require_once __DIR__ . '/core/bootstrap.php';

if (Auth::check()) {
    redirect(Auth::homeFor(Auth::role()));
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Please enter both username and password.';
        } else {
            $result = Auth::attempt($username, $password);
            if ($result['ok']) {
                redirect(Auth::homeFor($result['user']['role']));
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Lilliput School Management System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="<?= base_url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-card">
    <div class="auth-brand">
        <i class="fa-solid fa-graduation-cap"></i>
        <h1>Lilliput School</h1>
        <p>School Management System — Phase 1 Demo</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger py-2"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= base_url('login.php') ?>">
        <?= Auth::csrfField() ?>
        <div class="mb-3">
            <label class="form-label">Username or Email</label>
            <input type="text" name="username" class="form-control" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Log In</button>
    </form>

    <div class="demo-credentials">
        <strong>Demo accounts</strong> (password for all: <code>Demo@123</code>)
        <ul class="mb-0 mt-2 ps-3">
            <li>Admin — <code>admin</code></li>
            <li>Teacher — <code>anita.sharma</code></li>
            <li>Accountant — <code>accountant</code></li>
            <li>Parent — <code>maya.karki</code> (2 children)</li>
            <li>Student — <code>rishav.shrestha</code></li>
        </ul>
    </div>
  </div>
</div>
</body>
</html>
