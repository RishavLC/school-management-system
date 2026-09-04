<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireArea('admin');

$pageTitle = 'Fees';
$activeMenu = 'fees';
$db = Database::connection();
$activeYear = $db->query("SELECT * FROM academic_years WHERE is_active=1 LIMIT 1")->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_fee_type') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $db->prepare('INSERT INTO fee_types (name, description) VALUES (:n,:d)')
               ->execute(['n' => $name, 'd' => $_POST['description'] ?: null]);
            flash('success', 'Fee type added.');
        }
        redirect(base_url('admin/fees.php'));
    }

    if ($action === 'assign_fee') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $feeTypeId = (int)($_POST['fee_type_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        if ($studentId && $feeTypeId && $amount > 0 && $activeYear) {
            $db->prepare("
                INSERT INTO student_fees (student_id, fee_type_id, academic_year_id, amount, discount, fine, due_date, status)
                VALUES (:s,:f,:y,:a,:d,:fi,:due,'unpaid')
            ")->execute([
                's' => $studentId, 'f' => $feeTypeId, 'y' => $activeYear['id'], 'a' => $amount,
                'd' => (float)($_POST['discount'] ?? 0), 'fi' => (float)($_POST['fine'] ?? 0), 'due' => $_POST['due_date'] ?: null,
            ]);
            Auth::log(Auth::id(), 'Assigned a fee to student #' . $studentId);
            flash('success', 'Fee assigned to student.');
        }
        redirect(base_url('admin/fees.php'));
    }
}

$feeTypes = $db->query('SELECT * FROM fee_types ORDER BY name')->fetchAll();
$students = $db->query("SELECT id, admission_number, first_name, last_name FROM students WHERE status='active' ORDER BY first_name")->fetchAll();

$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$sql = "
    SELECT sf.*, ft.name AS fee_type_name, s.admission_number, s.first_name, s.last_name,
        (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.student_fee_id = sf.id) AS paid
    FROM student_fees sf
    JOIN fee_types ft ON ft.id = sf.fee_type_id
    JOIN students s ON s.id = sf.student_id
    WHERE 1=1
";
$params = [];
if ($search !== '') { $sql .= " AND (s.first_name LIKE :q OR s.last_name LIKE :q OR s.admission_number LIKE :q)"; $params['q'] = "%$search%"; }
if ($statusFilter !== '') { $sql .= " AND sf.status = :st"; $params['st'] = $statusFilter; }
$sql .= " ORDER BY sf.due_date DESC LIMIT 100";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$feeRecords = $stmt->fetchAll();

$totals = $db->query("
    SELECT COALESCE(SUM(amount-discount+fine),0) billed,
           (SELECT COALESCE(SUM(amount),0) FROM payments) collected
    FROM student_fees
")->fetch();

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="row g-3 mb-1">
    <div class="col-md-4"><div class="stat-card"><div class="stat-icon" style="background:#e8ecfb;color:#2f5fdc"><i class="fa-solid fa-file-invoice-dollar"></i></div><div><div class="stat-value"><?= format_money($totals['billed']) ?></div><div class="stat-label">Total Billed</div></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-icon" style="background:#e6f7ee;color:#1aa260"><i class="fa-solid fa-sack-dollar"></i></div><div><div class="stat-value"><?= format_money($totals['collected']) ?></div><div class="stat-label">Total Collected</div></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-icon" style="background:#fdeaea;color:#d9364a"><i class="fa-solid fa-triangle-exclamation"></i></div><div><div class="stat-value"><?= format_money(max(0,$totals['billed']-$totals['collected'])) ?></div><div class="stat-label">Outstanding</div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">Fee Types</div>
            <div class="card-body">
                <form method="post" class="d-flex gap-2 mb-3">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="add_fee_type">
                    <input class="form-control" name="name" placeholder="e.g. Library Fee" required>
                    <button class="btn btn-outline-primary">Add</button>
                </form>
                <ul class="list-group list-group-flush">
                    <?php foreach ($feeTypes as $ft): ?>
                        <li class="list-group-item"><?= e($ft['name']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Assign Fee to Student</div>
            <div class="card-body">
                <form method="post">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="assign_fee">
                    <div class="mb-2">
                        <label class="form-label small">Student</label>
                        <select name="student_id" class="form-select" required>
                            <?php foreach ($students as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['first_name'].' '.$s['last_name'].' ('.$s['admission_number'].')') ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Fee Type</label>
                        <select name="fee_type_id" class="form-select" required>
                            <?php foreach ($feeTypes as $ft): ?><option value="<?= (int)$ft['id'] ?>"><?= e($ft['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-4"><label class="form-label small">Amount</label><input class="form-control" type="number" step="0.01" name="amount" required></div>
                        <div class="col-4"><label class="form-label small">Discount</label><input class="form-control" type="number" step="0.01" name="discount" value="0"></div>
                        <div class="col-4"><label class="form-label small">Fine</label><input class="form-control" type="number" step="0.01" name="fine" value="0"></div>
                    </div>
                    <div class="mb-3"><label class="form-label small">Due Date</label><input class="form-control" type="date" name="due_date"></div>
                    <button class="btn btn-primary w-100" <?= $activeYear ? '' : 'disabled' ?>>Assign Fee</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <form class="d-flex gap-2 flex-wrap" method="get">
                    <input class="form-control" style="width:220px" name="q" placeholder="Search student" value="<?= e($search) ?>">
                    <select class="form-select" style="width:160px" name="status">
                        <option value="">All Status</option>
                        <option value="unpaid" <?= $statusFilter==='unpaid'?'selected':'' ?>>Unpaid</option>
                        <option value="partial" <?= $statusFilter==='partial'?'selected':'' ?>>Partial</option>
                        <option value="paid" <?= $statusFilter==='paid'?'selected':'' ?>>Paid</option>
                    </select>
                    <button class="btn btn-outline-secondary"><i class="fa-solid fa-filter"></i></button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Student</th><th>Fee Type</th><th>Amount</th><th>Paid</th><th>Outstanding</th><th>Due</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($feeRecords as $f): $net = $f['amount']-$f['discount']+$f['fine']; ?>
                        <tr>
                            <td><?= e($f['first_name'].' '.$f['last_name']) ?> <span class="text-muted small">(<?= e($f['admission_number']) ?>)</span></td>
                            <td><?= e($f['fee_type_name']) ?></td>
                            <td><?= format_money($net) ?></td>
                            <td><?= format_money($f['paid']) ?></td>
                            <td><?= format_money(max(0,$net-$f['paid'])) ?></td>
                            <td><?= format_date($f['due_date']) ?></td>
                            <td><?= status_badge($f['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($feeRecords)): ?><tr><td colspan="7" class="empty-state">No fee records found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
