<?php
$code = http_response_code();
$title = $code === 403 ? '403 - Access Denied' : ($code === 404 ? '404 - Not Found' : 'Error');
$message = $code === 403
    ? "You don't have permission to view this page. If you believe this is a mistake, contact the school administrator."
    : "The page you're looking for doesn't exist.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= e($title) ?> - Lilliput School</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center vh-100 bg-light">
<div class="text-center">
    <h1 class="display-4 fw-bold text-danger"><?= e((string)$code) ?></h1>
    <p class="lead mb-4"><?= e($message) ?></p>
    <a href="<?= base_url('/') ?>" class="btn btn-primary">Go to homepage</a>
</div>
</body>
</html>
