<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireArea('admin');

$pageTitle = 'Teachers';
$activeMenu = 'teachers';
$db = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf() && ($_POST['action'] ?? '') === 'create') {
    $v = new Validator($_POST);
    $v->required('first_name', 'First name')->required('last_name', 'Last name')
      ->required('employee_id', 'Employee ID')->required('username', 'Username')
      ->email('email', 'Email', false);

    if (!$v->fails()) {
        try {
            $db->beginTransaction();
            $tempPassword = 'Demo@123'; // Phase-1 demo default; production should email a reset link.
            $db->prepare('INSERT INTO users (username, email, password_hash, role, status, must_change_password) VALUES (:u,:e,:p,"teacher","active",1)')
               ->execute([
                   'u' => $_POST['username'],
                   'e' => $_POST['email'] ?: null,
                   'p' => password_hash($tempPassword, PASSWORD_DEFAULT),
               ]);
            $userId = $db->lastInsertId();

            $db->prepare('INSERT INTO teachers (user_id, employee_id, first_name, last_name, phone, email, address, qualification, joining_date, status)
                           VALUES (:uid,:emp,:fn,:ln,:ph,:em,:ad,:ql,:jd,"active")')
               ->execute([
                   'uid' => $userId, 'emp' => $_POST['employee_id'], 'fn' => $_POST['first_name'], 'ln' => $_POST['last_name'],
                   'ph' => $_POST['phone'] ?: null, 'em' => $_POST['email'] ?: null, 'ad' => $_POST['address'] ?: null,
                   'ql' => $_POST['qualification'] ?: null, 'jd' => $_POST['joining_date'] ?: null,
               ]);
            $db->commit();
            Auth::log(Auth::id(), 'Added teacher ' . $_POST['first_name'] . ' ' . $_POST['last_name']);
            flash('success', 'Teacher added. Temporary password: Demo@123 (they will be asked to change it on first login).');
        } catch (PDOException $e) {
            $db->rollBack();
            flash('error', 'Could not create teacher — username or employee ID may already be taken.');
        }
    } else {
        flash('error', implode(' ', $v->errors()));
    }
    redirect(base_url('admin/teachers.php'));
}

$teachers = $db->query("
    SELECT t.*, u.status AS account_status,
        (SELECT COUNT(*) FROM teacher_assignments ta WHERE ta.teacher_id = t.id) AS assignment_count
    FROM teachers t JOIN users u ON t.user_id = u.id
    ORDER BY t.first_name
")->fetchAll();

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeacherModal"><i class="fa-solid fa-plus me-1"></i> Add Teacher</button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Employee ID</th><th>Name</th><th>Phone</th><th>Qualification</th><th>Joined</th><th>Assignments</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($teachers as $t): ?>
                <tr>
                    <td><span class="badge text-bg-light border"><?= e($t['employee_id']) ?></span></td>
                    <td class="d-flex align-items-center gap-2">
                        <div class="avatar-sm"><?= e(strtoupper(substr($t['first_name'],0,1))) ?></div>
                        <?= e($t['first_name'] . ' ' . $t['last_name']) ?>
                    </td>
                    <td><?= e($t['phone'] ?? '-') ?></td>
                    <td><?= e($t['qualification'] ?? '-') ?></td>
                    <td><?= format_date($t['joining_date']) ?></td>
                    <td><?= (int)$t['assignment_count'] ?> class(es)</td>
                    <td><?= status_badge($t['account_status']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($teachers)): ?>
                <tr><td colspan="7" class="empty-state">No teachers added yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addTeacherModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="create">
      <div class="modal-header"><h5 class="modal-title">Add Teacher</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-2">
            <div class="col-6"><label class="form-label small">First Name</label><input class="form-control" name="first_name" required></div>
            <div class="col-6"><label class="form-label small">Last Name</label><input class="form-control" name="last_name" required></div>
            <div class="col-6"><label class="form-label small">Employee ID</label><input class="form-control" name="employee_id" required placeholder="EMP-004"></div>
            <div class="col-6"><label class="form-label small">Username</label><input class="form-control" name="username" required></div>
            <div class="col-6"><label class="form-label small">Email</label><input class="form-control" type="email" name="email"></div>
            <div class="col-6"><label class="form-label small">Phone</label><input class="form-control" name="phone"></div>
            <div class="col-12"><label class="form-label small">Address</label><input class="form-control" name="address"></div>
            <div class="col-6"><label class="form-label small">Qualification</label><input class="form-control" name="qualification"></div>
            <div class="col-6"><label class="form-label small">Joining Date</label><input class="form-control" type="date" name="joining_date"></div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-primary">Create Teacher</button></div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
