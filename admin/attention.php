<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireArea('admin');

$pageTitle = 'Students Needing Attention';
$activeMenu = 'attention';
$db = Database::connection();
$activeYear = $db->query("SELECT * FROM academic_years WHERE is_active=1 LIMIT 1")->fetch();

$classSections = [];
if ($activeYear) {
    $classSections = $db->prepare("
        SELECT cs.id, c.name AS class_name, sec.name AS section_name
        FROM class_sections cs JOIN classes c ON c.id=cs.class_id JOIN sections sec ON sec.id=cs.section_id
        WHERE cs.academic_year_id = :y ORDER BY c.sort_order, sec.name
    ");
    $classSections->execute(['y' => $activeYear['id']]);
    $classSections = $classSections->fetchAll();
}

$filterCs = (int) ($_GET['class_section_id'] ?? 0);

$sql = "
    SELECT s.id, s.first_name, s.last_name, s.admission_number, c.name AS class_name, sec.name AS section_name
    FROM students s
    JOIN student_enrollments se ON se.student_id = s.id AND se.academic_year_id = :y
    JOIN class_sections cs ON cs.id = se.class_section_id
    JOIN classes c ON c.id = cs.class_id
    JOIN sections sec ON sec.id = cs.section_id
    WHERE s.status = 'active'
";
$params = ['y' => $activeYear['id'] ?? 0];
if ($filterCs) { $sql .= " AND cs.id = :cs"; $params['cs'] = $filterCs; }
$sql .= " ORDER BY s.first_name";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$allStudents = $stmt->fetchAll();

$flagged = [];
foreach ($allStudents as $s) {
    $flags = student_attention_flags((int) $s['id']);
    if (!empty($flags)) {
        $s['flags'] = $flags;
        $flagged[] = $s;
    }
}

$byType = ['attendance' => 0, 'academic' => 0, 'homework' => 0, 'fee' => 0];
foreach ($flagged as $s) {
    foreach ($s['flags'] as $f) { $byType[$f['type']] = ($byType[$f['type']] ?? 0) + 1; }
}

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="row g-3 mb-1">
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon" style="background:#fdeaea;color:#d9364a"><i class="fa-solid fa-user-group"></i></div><div><div class="stat-value"><?= count($flagged) ?></div><div class="stat-label">Need Follow-up</div></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon" style="background:#fff2e0;color:#d9822b"><i class="fa-solid fa-user-clock"></i></div><div><div class="stat-value"><?= $byType['attendance'] ?></div><div class="stat-label">Attendance</div></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon" style="background:#fdeaea;color:#d9364a"><i class="fa-solid fa-chart-line"></i></div><div><div class="stat-value"><?= $byType['academic'] ?></div><div class="stat-label">Academic Support</div></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon" style="background:#e8ecfb;color:#2f5fdc"><i class="fa-solid fa-book"></i></div><div><div class="stat-value"><?= $byType['homework'] ?></div><div class="stat-label">Homework</div></div></div></div>
</div>

<div class="card">
    <div class="card-header">
        <form class="d-flex gap-2" method="get">
            <select class="form-select" style="width:260px" name="class_section_id" onchange="this.form.submit()">
                <option value="0">All Classes</option>
                <?php foreach ($classSections as $cs): ?>
                    <option value="<?= (int)$cs['id'] ?>" <?= $filterCs === (int)$cs['id'] ? 'selected' : '' ?>><?= e($cs['class_name'].' - '.$cs['section_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Student</th><th>Class</th><th>Flags</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($flagged as $s): ?>
                <tr>
                    <td><?= e($s['first_name'].' '.$s['last_name']) ?> <span class="text-muted small">(<?= e($s['admission_number']) ?>)</span></td>
                    <td><?= e($s['class_name'].' - '.$s['section_name']) ?></td>
                    <td>
                        <?php foreach ($s['flags'] as $f): $style = attention_flag_style($f['type']); ?>
                            <span class="badge text-bg-<?= $style['color'] ?> me-1" title="<?= e($f['detail']) ?>">
                                <i class="fa-solid <?= $style['icon'] ?> me-1"></i><?= e($f['label']) ?>
                            </span>
                        <?php endforeach; ?>
                    </td>
                    <td><a href="<?= base_url('admin/student_view.php?id='.(int)$s['id']) ?>" class="btn btn-sm btn-outline-primary">View Profile</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($flagged)): ?><tr><td colspan="4" class="empty-state">No students currently flagged — nice work!</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
