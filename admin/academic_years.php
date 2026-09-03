<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireArea('admin');

$pageTitle = 'Academic Years';
$activeMenu = 'academic_years';
$db = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $v = new Validator($_POST);
        $v->required('name', 'Name')->maxLength('name', 20, 'Name')
          ->date('start_date', 'Start date')->date('end_date', 'End date');

        if (!$v->fails()) {
            $stmt = $db->prepare('INSERT INTO academic_years (name, start_date, end_date, is_active) VALUES (:n,:s,:e,0)');
            $stmt->execute(['n' => $_POST['name'], 's' => $_POST['start_date'], 'e' => $_POST['end_date']]);
            Auth::log(Auth::id(), 'Created academic year ' . $_POST['name']);
            flash('success', 'Academic year created.');
        } else {
            flash('error', implode(' ', $v->errors()));
        }
        redirect(base_url('admin/academic_years.php'));
    }

    if ($action === 'activate') {
        $id = (int)($_POST['id'] ?? 0);
        // Only one academic year may be active at a time.
        $db->beginTransaction();
        $db->exec('UPDATE academic_years SET is_active = 0');
        $db->prepare('UPDATE academic_years SET is_active = 1 WHERE id = :id')->execute(['id' => $id]);
        $db->commit();
        Auth::log(Auth::id(), 'Activated academic year #' . $id);
        flash('success', 'Academic year activated.');
        redirect(base_url('admin/academic_years.php'));
    }
}

$years = $db->query('SELECT * FROM academic_years ORDER BY start_date DESC')->fetchAll();

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Add Academic Year</div>
            <div class="card-body">
                <form method="post">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. 2026-2027" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" required>
                    </div>
                    <button class="btn btn-primary w-100">Add Academic Year</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">All Academic Years</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Name</th><th>Start</th><th>End</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($years as $y): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($y['name']) ?></td>
                            <td><?= format_date($y['start_date']) ?></td>
                            <td><?= format_date($y['end_date']) ?></td>
                            <td><?= $y['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' ?></td>
                            <td>
                                <?php if (!$y['is_active']): ?>
                                <form method="post" class="d-inline" data-confirm="Make <?= e($y['name']) ?> the active academic year?">
                                    <?= Auth::csrfField() ?>
                                    <input type="hidden" name="action" value="activate">
                                    <input type="hidden" name="id" value="<?= (int)$y['id'] ?>">
                                    <button class="btn btn-sm btn-outline-primary">Set Active</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($years)): ?>
                        <tr><td colspan="5" class="empty-state">No academic years yet. Add one to get started.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
