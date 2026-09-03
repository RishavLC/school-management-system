<?php
require_once __DIR__ . '/../core/bootstrap.php';
$student = require_student_profile();

$pageTitle = 'Leave Request';
$activeMenu = 'leave';
$db = Database::connection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $v = new Validator($_POST);
        $v->date('from_date', 'From date')
          ->date('to_date', 'To date')
          ->required('reason', 'Reason')
          ->maxLength('reason', 255, 'Reason');

        $dateOrderInvalid = !$v->fails() && $_POST['to_date'] < $_POST['from_date'];

        if ($v->fails() || $dateOrderInvalid) {
            $errors = array_values($v->errors());
            if ($dateOrderInvalid) $errors[] = 'To date cannot be before the from date.';
        } else {
            $db->prepare('INSERT INTO leave_requests (student_id, from_date, to_date, reason) VALUES (:s, :f, :t, :r)')
               ->execute(['s' => $student['id'], 'f' => $_POST['from_date'], 't' => $_POST['to_date'], 'r' => trim($_POST['reason'])]);
            flash('success', 'Leave request submitted. You will be notified once it is reviewed.');
            redirect(base_url('student/leave.php'));
        }
    }
}

$requests = $db->prepare('SELECT * FROM leave_requests WHERE student_id = :s ORDER BY created_at DESC');
$requests->execute(['s' => $student['id']]);
$requests = $requests->fetchAll();

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Apply for Leave</div>
            <div class="card-body">
                <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
                <form method="post">
                    <?= Auth::csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label">From Date</label>
                        <input type="date" name="from_date" class="form-control" required value="<?= e($_POST['from_date'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">To Date</label>
                        <input type="date" name="to_date" class="form-control" required value="<?= e($_POST['to_date'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea name="reason" class="form-control" rows="3" required maxlength="255"><?= e($_POST['reason'] ?? '') ?></textarea>
                    </div>
                    <button class="btn btn-primary">Submit Request</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">My Leave Requests</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>From</th><th>To</th><th>Reason</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($requests as $r): ?>
                        <tr>
                            <td><?= format_date($r['from_date']) ?></td>
                            <td><?= format_date($r['to_date']) ?></td>
                            <td class="small"><?= e($r['reason']) ?><?php if ($r['review_remarks']): ?><div class="text-muted">Remarks: <?= e($r['review_remarks']) ?></div><?php endif; ?></td>
                            <td><?= status_badge($r['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($requests)): ?><tr><td colspan="4" class="empty-state">No leave requests yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
