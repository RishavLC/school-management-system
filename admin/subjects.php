<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireArea('admin');

$pageTitle = 'Subjects';
$activeMenu = 'subjects';
$db = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $v = new Validator($_POST);
        $v->required('name', 'Subject name')->required('code', 'Subject code')->maxLength('code', 20, 'Code');
        if (!$v->fails()) {
            try {
                $db->prepare('INSERT INTO subjects (name, code, description, status) VALUES (:n,:c,:d,"active")')
                   ->execute(['n' => $_POST['name'], 'c' => strtoupper($_POST['code']), 'd' => $_POST['description'] ?? null]);
                flash('success', 'Subject added.');
            } catch (PDOException $e) {
                flash('error', 'That subject code already exists.');
            }
        } else {
            flash('error', implode(' ', $v->errors()));
        }
        redirect(base_url('admin/subjects.php'));
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE subjects SET status = IF(status='active','inactive','active') WHERE id = :id")->execute(['id' => $id]);
        redirect(base_url('admin/subjects.php'));
    }
}

$subjects = $db->query('SELECT * FROM subjects ORDER BY name')->fetchAll();
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Add Subject</div>
            <div class="card-body">
                <form method="post">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3"><label class="form-label">Subject Name</label><input class="form-control" name="name" required></div>
                    <div class="mb-3"><label class="form-label">Subject Code</label><input class="form-control" name="code" required maxlength="20" placeholder="e.g. ENG"></div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
                    <button class="btn btn-primary w-100">Add Subject</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">All Subjects</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Code</th><th>Name</th><th>Description</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($subjects as $s): ?>
                        <tr>
                            <td><span class="badge text-bg-light border"><?= e($s['code']) ?></span></td>
                            <td class="fw-semibold"><?= e($s['name']) ?></td>
                            <td class="text-muted"><?= e($s['description'] ?? '-') ?></td>
                            <td><?= status_badge($s['status']) ?></td>
                            <td>
                                <form method="post" class="d-inline">
                                    <?= Auth::csrfField() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                    <button class="btn btn-sm btn-outline-secondary"><?= $s['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($subjects)): ?>
                        <tr><td colspan="5" class="empty-state">No subjects yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
