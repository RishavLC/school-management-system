<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireRole(['super_admin', 'admin']); // audit trail is admin-only, not even principal

$pageTitle = 'Audit Trail';
$activeMenu = 'audit_log';
$db = Database::connection();

$module = trim($_GET['module'] ?? '');
$userId = (int) ($_GET['user_id'] ?? 0);
$dateFrom = $_GET['from'] ?? '';
$dateTo = $_GET['to'] ?? '';

$sql = "SELECT al.*, u.username FROM activity_log al LEFT JOIN users u ON u.id = al.user_id WHERE 1=1";
$params = [];
if ($module !== '') { $sql .= " AND al.module = :m"; $params['m'] = $module; }
if ($userId) { $sql .= " AND al.user_id = :u"; $params['u'] = $userId; }
if ($dateFrom !== '') { $sql .= " AND al.created_at >= :df"; $params['df'] = $dateFrom . ' 00:00:00'; }
if ($dateTo !== '') { $sql .= " AND al.created_at <= :dt"; $params['dt'] = $dateTo . ' 23:59:59'; }
$sql .= " ORDER BY al.created_at DESC LIMIT 200";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$modules = $db->query("SELECT DISTINCT module FROM activity_log WHERE module IS NOT NULL ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);
$users = $db->query("SELECT id, username FROM users ORDER BY username")->fetchAll();

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2" method="get">
            <div class="col-md-3">
                <select class="form-select" name="module">
                    <option value="">All Modules</option>
                    <?php foreach ($modules as $m): ?><option value="<?= e($m) ?>" <?= $module===$m?'selected':'' ?>><?= e(ucfirst($m)) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="user_id">
                    <option value="0">All Users</option>
                    <?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= $userId===(int)$u['id']?'selected':'' ?>><?= e($u['username']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><input type="date" class="form-control" name="from" value="<?= e($dateFrom) ?>" placeholder="From"></div>
            <div class="col-md-3"><input type="date" class="form-control" name="to" value="<?= e($dateTo) ?>" placeholder="To"></div>
            <div class="col-12"><button class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i>Filter</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        Audit Trail
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-print me-1"></i>Print</button>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Date/Time</th><th>User</th><th>Module</th><th>Action</th><th>Details</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td class="text-nowrap"><?= e(date('d M Y, h:i A', strtotime($l['created_at']))) ?></td>
                    <td><?= e($l['username'] ?? 'System') ?></td>
                    <td><?= $l['module'] ? '<span class="badge text-bg-light border">'.e(ucfirst($l['module'])).'</span>' : '-' ?></td>
                    <td><?= e($l['action']) ?></td>
                    <td class="text-muted small"><?= e($l['details'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?><tr><td colspan="5" class="empty-state">No matching audit records.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
