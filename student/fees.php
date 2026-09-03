<?php
require_once __DIR__ . '/../core/bootstrap.php';
$student = require_student_profile();

$pageTitle = 'Fees';
$activeMenu = 'fees';
$db = Database::connection();

$stmt = $db->prepare("
    SELECT sf.*, ft.name AS fee_type_name,
        (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.student_fee_id = sf.id) AS paid
    FROM student_fees sf JOIN fee_types ft ON ft.id = sf.fee_type_id
    WHERE sf.student_id = :s ORDER BY sf.due_date
");
$stmt->execute(['s' => $student['id']]);
$fees = $stmt->fetchAll();

$payments = $db->prepare("
    SELECT p.*, ft.name AS fee_type_name FROM payments p
    JOIN student_fees sf ON sf.id = p.student_fee_id
    JOIN fee_types ft ON ft.id = sf.fee_type_id
    WHERE p.student_id = :s ORDER BY p.payment_date DESC
");
$payments->execute(['s' => $student['id']]);
$payments = $payments->fetchAll();

$totalBilled = 0; $totalPaid = 0;
foreach ($fees as $f) {
    $totalBilled += $f['amount'] - $f['discount'] + $f['fine'];
    $totalPaid += $f['paid'];
}
$totalDue = max(0, $totalBilled - $totalPaid);

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="row g-3 mb-1">
    <div class="col-6 col-md-4">
        <div class="stat-card"><div class="stat-icon" style="background:#e8ecfb;color:#2f5fdc"><i class="fa-solid fa-file-invoice-dollar"></i></div>
        <div><div class="stat-value"><?= format_money($totalBilled) ?></div><div class="stat-label">Total Fee</div></div></div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card"><div class="stat-icon" style="background:#e6f7ee;color:#1aa260"><i class="fa-solid fa-circle-check"></i></div>
        <div><div class="stat-value"><?= format_money($totalPaid) ?></div><div class="stat-label">Paid</div></div></div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card"><div class="stat-icon" style="background:#fdeaea;color:#d9364a"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div><div class="stat-value"><?= format_money($totalDue) ?></div><div class="stat-label">Remaining</div></div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Fee Breakdown</div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Fee Type</th><th>Amount</th><th>Discount</th><th>Fine</th><th>Paid</th><th>Due Date</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($fees as $f): $net = $f['amount'] - $f['discount'] + $f['fine']; ?>
                <tr>
                    <td class="fw-semibold"><?= e($f['fee_type_name']) ?></td>
                    <td><?= format_money($f['amount']) ?></td>
                    <td><?= format_money($f['discount']) ?></td>
                    <td><?= format_money($f['fine']) ?></td>
                    <td><?= format_money($f['paid']) ?> / <?= format_money($net) ?></td>
                    <td><?= format_date($f['due_date']) ?></td>
                    <td><?= status_badge($f['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($fees)): ?><tr><td colspan="7" class="empty-state">No fees recorded yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">Payment History &amp; Receipts</div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Receipt #</th><th>Fee Type</th><th>Amount</th><th>Date</th><th>Method</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $p): ?>
                <tr>
                    <td><?= e($p['receipt_number']) ?></td>
                    <td><?= e($p['fee_type_name']) ?></td>
                    <td><?= format_money($p['amount']) ?></td>
                    <td><?= format_date($p['payment_date']) ?></td>
                    <td class="text-capitalize"><?= e($p['payment_method']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($payments)): ?><tr><td colspan="5" class="empty-state">No payments recorded yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="text-muted small mt-2"><i class="fa-solid fa-circle-info me-1"></i>Online fee payment is not available in this Phase 1 demo. Please pay at the school office.</p>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
