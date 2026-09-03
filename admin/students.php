<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireArea('admin');

$pageTitle = 'Students';
$activeMenu = 'students';
$db = Database::connection();
$activeYear = $db->query("SELECT * FROM academic_years WHERE is_active=1 LIMIT 1")->fetch();

$search = trim($_GET['q'] ?? '');
$classSectionFilter = (int)($_GET['class_section_id'] ?? 0);

$sql = "
    SELECT s.*, se.roll_number, c.name AS class_name, sec.name AS section_name, cs.id AS class_section_id
    FROM students s
    LEFT JOIN student_enrollments se ON se.student_id = s.id AND se.academic_year_id = :year_id
    LEFT JOIN class_sections cs ON se.class_section_id = cs.id
    LEFT JOIN classes c ON cs.class_id = c.id
    LEFT JOIN sections sec ON cs.section_id = sec.id
    WHERE 1=1
";
$params = ['year_id' => $activeYear['id'] ?? 0];

if ($search !== '') {
    $sql .= " AND (s.first_name LIKE :q OR s.last_name LIKE :q OR s.admission_number LIKE :q)";
    $params['q'] = "%$search%";
}
if ($classSectionFilter) {
    $sql .= " AND cs.id = :cs";
    $params['cs'] = $classSectionFilter;
}
$sql .= " ORDER BY s.first_name";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$classSections = [];
if ($activeYear) {
    $classSections = $db->prepare("
        SELECT cs.id, c.name AS class_name, s.name AS section_name
        FROM class_sections cs JOIN classes c ON cs.class_id=c.id JOIN sections s ON cs.section_id=s.id
        WHERE cs.academic_year_id = :y ORDER BY c.sort_order, s.name
    ");
    $classSections->execute(['y' => $activeYear['id']]);
    $classSections = $classSections->fetchAll();
}

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <form class="d-flex gap-2 flex-wrap" method="get">
        <input type="text" name="q" class="form-control" style="width:220px" placeholder="Search name or admission #" value="<?= e($search) ?>">
        <select name="class_section_id" class="form-select" style="width:200px">
            <option value="0">All Classes</option>
            <?php foreach ($classSections as $cs): ?>
                <option value="<?= (int)$cs['id'] ?>" <?= $classSectionFilter === (int)$cs['id'] ? 'selected' : '' ?>>
                    <?= e($cs['class_name'] . ' - ' . $cs['section_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-outline-secondary"><i class="fa-solid fa-filter"></i> Filter</button>
    </form>
    <a href="<?= base_url('admin/student_add.php') ?>" class="btn btn-primary"><i class="fa-solid fa-user-plus me-1"></i> Add Student</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Admission #</th><th>Name</th><th>Class</th><th>Roll #</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td><span class="badge text-bg-light border"><?= e($s['admission_number']) ?></span></td>
                    <td class="d-flex align-items-center gap-2">
                        <div class="avatar-sm"><?= e(strtoupper(substr($s['first_name'],0,1))) ?></div>
                        <?= e($s['first_name'] . ' ' . $s['last_name']) ?>
                    </td>
                    <td><?= $s['class_name'] ? e($s['class_name'] . ' - ' . $s['section_name']) : '<span class="text-muted">Not enrolled</span>' ?></td>
                    <td><?= e($s['roll_number'] ?? '-') ?></td>
                    <td><?= status_badge($s['status']) ?></td>
                    <td><a href="<?= base_url('admin/student_view.php?id=' . (int)$s['id']) ?>" class="btn btn-sm btn-outline-primary">View Profile</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($students)): ?>
                <tr><td colspan="6" class="empty-state"><i class="fa-solid fa-user-graduate"></i><p>No students found.</p></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
