<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireRole(['super_admin', 'admin', 'principal', 'teacher']);

$pageTitle = 'Global Search';
$activeMenu = 'search';
$db = Database::connection();
$q = trim($_GET['q'] ?? '');
$results = ['students' => [], 'teachers' => [], 'guardians' => [], 'classes' => [], 'notices' => []];

if ($q !== '' && mb_strlen($q) >= 2) {
    $like = "%$q%";

    $stmt = $db->prepare("
        SELECT s.id, s.first_name, s.last_name, s.admission_number, c.name AS class_name, sec.name AS section_name
        FROM students s
        LEFT JOIN student_enrollments se ON se.student_id = s.id AND se.academic_year_id = (SELECT id FROM academic_years WHERE is_active=1 LIMIT 1)
        LEFT JOIN class_sections cs ON cs.id = se.class_section_id
        LEFT JOIN classes c ON c.id = cs.class_id
        LEFT JOIN sections sec ON sec.id = cs.section_id
        WHERE s.first_name LIKE :q OR s.last_name LIKE :q2 OR s.admission_number LIKE :q3
        LIMIT 10
    ");
    $stmt->execute(['q' => $like, 'q2' => $like, 'q3' => $like]);
    $results['students'] = $stmt->fetchAll();

    // Teachers and guardians are admin-only (contain staff/parent contact info)
    if (in_array(Auth::role(), ['super_admin', 'admin', 'principal'], true)) {
        $stmt = $db->prepare("SELECT id, first_name, last_name, employee_id FROM teachers WHERE first_name LIKE :q OR last_name LIKE :q2 OR employee_id LIKE :q3 LIMIT 10");
        $stmt->execute(['q' => $like, 'q2' => $like, 'q3' => $like]);
        $results['teachers'] = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT id, first_name, last_name, phone FROM guardians WHERE first_name LIKE :q OR last_name LIKE :q2 LIMIT 10");
        $stmt->execute(['q' => $like, 'q2' => $like]);
        $results['guardians'] = $stmt->fetchAll();
    }

    $stmt = $db->prepare("
        SELECT cs.id, c.name AS class_name, sec.name AS section_name
        FROM class_sections cs JOIN classes c ON c.id=cs.class_id JOIN sections sec ON sec.id=cs.section_id
        WHERE c.name LIKE :q
        LIMIT 10
    ");
    $stmt->execute(['q' => $like]);
    $results['classes'] = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT id, title, notice_date FROM notices WHERE title LIKE :q LIMIT 10");
    $stmt->execute(['q' => $like]);
    $results['notices'] = $stmt->fetchAll();
}

$totalResults = array_sum(array_map('count', $results));

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="d-flex gap-2">
            <input type="text" name="q" class="form-control form-control-lg" placeholder="Search students, teachers, classes, notices..." value="<?= e($q) ?>" autofocus>
            <button class="btn btn-primary px-4"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
    </div>
</div>

<?php if ($q !== '' && mb_strlen($q) < 2): ?>
    <div class="alert alert-info">Type at least 2 characters to search.</div>
<?php elseif ($q !== '' && $totalResults === 0): ?>
    <div class="card"><div class="empty-state py-5"><i class="fa-solid fa-magnifying-glass"></i><p class="mb-0">No results found for "<?= e($q) ?>".</p></div></div>
<?php elseif ($q !== ''): ?>

<?php if (!empty($results['students'])): ?>
<div class="card mb-3">
    <div class="card-header"><i class="fa-solid fa-user-graduate me-2"></i>Students</div>
    <ul class="list-group list-group-flush">
        <?php foreach ($results['students'] as $s): ?>
            <li class="list-group-item d-flex justify-content-between">
                <a href="<?= base_url('admin/student_view.php?id='.(int)$s['id']) ?>"><?= e($s['first_name'].' '.$s['last_name']) ?> <span class="text-muted small">(<?= e($s['admission_number']) ?>)</span></a>
                <span class="text-muted small"><?= $s['class_name'] ? e($s['class_name'].' - '.$s['section_name']) : 'Not enrolled' ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (!empty($results['teachers'])): ?>
<div class="card mb-3">
    <div class="card-header"><i class="fa-solid fa-chalkboard-teacher me-2"></i>Teachers</div>
    <ul class="list-group list-group-flush">
        <?php foreach ($results['teachers'] as $t): ?>
            <li class="list-group-item"><?= e($t['first_name'].' '.$t['last_name']) ?> <span class="text-muted small">(<?= e($t['employee_id']) ?>)</span></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (!empty($results['guardians'])): ?>
<div class="card mb-3">
    <div class="card-header"><i class="fa-solid fa-people-roof me-2"></i>Parents / Guardians</div>
    <ul class="list-group list-group-flush">
        <?php foreach ($results['guardians'] as $g): ?>
            <li class="list-group-item"><?= e($g['first_name'].' '.$g['last_name']) ?> <span class="text-muted small"><?= e($g['phone'] ?? '') ?></span></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (!empty($results['classes'])): ?>
<div class="card mb-3">
    <div class="card-header"><i class="fa-solid fa-layer-group me-2"></i>Classes</div>
    <ul class="list-group list-group-flush">
        <?php foreach ($results['classes'] as $c): ?>
            <li class="list-group-item"><?= e($c['class_name'].' - '.$c['section_name']) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (!empty($results['notices'])): ?>
<div class="card mb-3">
    <div class="card-header"><i class="fa-solid fa-bullhorn me-2"></i>Notices</div>
    <ul class="list-group list-group-flush">
        <?php foreach ($results['notices'] as $n): ?>
            <li class="list-group-item d-flex justify-content-between"><?= e($n['title']) ?> <span class="text-muted small"><?= format_date($n['notice_date']) ?></span></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php endif; ?>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
