<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireArea('admin');

$pageTitle = 'Classes & Sections';
$activeMenu = 'classes';
$db = Database::connection();

$activeYear = $db->query("SELECT * FROM academic_years WHERE is_active=1 LIMIT 1")->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_class') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $db->prepare('INSERT INTO classes (name, sort_order) VALUES (:n, :o)')
               ->execute(['n' => $name, 'o' => (int)($_POST['sort_order'] ?? 0)]);
            flash('success', 'Class added.');
        }
        redirect(base_url('admin/classes.php'));
    }

    if ($action === 'add_section') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            try {
                $db->prepare('INSERT INTO sections (name) VALUES (:n)')->execute(['n' => $name]);
                flash('success', 'Section added.');
            } catch (PDOException $e) {
                flash('error', 'That section already exists.');
            }
        }
        redirect(base_url('admin/classes.php'));
    }

    if ($action === 'offer') {
        if (!$activeYear) {
            flash('error', 'Set an active academic year first.');
            redirect(base_url('admin/academic_years.php'));
        }
        $classId = (int)($_POST['class_id'] ?? 0);
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $room = trim($_POST['room'] ?? '') ?: null;
        try {
            $db->prepare('INSERT INTO class_sections (academic_year_id, class_id, section_id, room) VALUES (:y,:c,:s,:r)')
               ->execute(['y' => $activeYear['id'], 'c' => $classId, 's' => $sectionId, 'r' => $room]);
            flash('success', 'Class-section offering created for ' . $activeYear['name'] . '.');
        } catch (PDOException $e) {
            flash('error', 'This class + section is already offered for the active year.');
        }
        redirect(base_url('admin/classes.php'));
    }
}

$classes = $db->query('SELECT * FROM classes ORDER BY sort_order, name')->fetchAll();
$sections = $db->query('SELECT * FROM sections ORDER BY name')->fetchAll();

$offerings = [];
if ($activeYear) {
    $offerings = $db->prepare("
        SELECT cs.id, c.name AS class_name, s.name AS section_name, cs.room,
               (SELECT COUNT(*) FROM student_enrollments se WHERE se.class_section_id = cs.id) AS student_count,
               t.first_name AS ct_first, t.last_name AS ct_last
        FROM class_sections cs
        JOIN classes c ON cs.class_id = c.id
        JOIN sections s ON cs.section_id = s.id
        LEFT JOIN teachers t ON cs.class_teacher_id = t.id
        WHERE cs.academic_year_id = :y
        ORDER BY c.sort_order, s.name
    ");
    $offerings->execute(['y' => $activeYear['id']]);
    $offerings = $offerings->fetchAll();
}

include __DIR__ . '/../includes/layout_start.php';
?>
<?php if (!$activeYear): ?>
    <div class="alert alert-warning">No active academic year is set. <a href="<?= base_url('admin/academic_years.php') ?>">Set one</a> before offering classes.</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">Add Class</div>
            <div class="card-body">
                <form method="post" class="row g-2">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="add_class">
                    <div class="col-8"><input class="form-control" name="name" placeholder="e.g. Grade 11" required></div>
                    <div class="col-4"><input class="form-control" type="number" name="sort_order" placeholder="Order"></div>
                    <div class="col-12"><button class="btn btn-primary w-100">Add Class</button></div>
                </form>
                <hr>
                <ul class="list-group list-group-flush">
                    <?php foreach ($classes as $c): ?>
                        <li class="list-group-item d-flex justify-content-between"><?= e($c['name']) ?> <span class="text-muted">#<?= (int)$c['sort_order'] ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Add Section</div>
            <div class="card-body">
                <form method="post" class="d-flex gap-2">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="add_section">
                    <input class="form-control" name="name" placeholder="e.g. D" required maxlength="10">
                    <button class="btn btn-primary">Add</button>
                </form>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    <?php foreach ($sections as $s): ?>
                        <span class="badge text-bg-light border"><?= e($s['name']) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">Offer a Class + Section for <?= $activeYear ? e($activeYear['name']) : 'active year' ?></div>
            <div class="card-body">
                <form method="post" class="row g-2 align-items-end">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="offer">
                    <div class="col-md-4">
                        <label class="form-label small">Class</label>
                        <select name="class_id" class="form-select" required>
                            <?php foreach ($classes as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Section</label>
                        <select name="section_id" class="form-select" required>
                            <?php foreach ($sections as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Room</label>
                        <input class="form-control" name="room" placeholder="e.g. Room 105">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" <?= $activeYear ? '' : 'disabled' ?>>Offer</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Current Offerings — <?= $activeYear ? e($activeYear['name']) : '-' ?></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Class</th><th>Section</th><th>Room</th><th>Class Teacher</th><th>Students</th></tr></thead>
                    <tbody>
                    <?php foreach ($offerings as $o): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($o['class_name']) ?></td>
                            <td><?= e($o['section_name']) ?></td>
                            <td><?= e($o['room'] ?? '-') ?></td>
                            <td><?= $o['ct_first'] ? e($o['ct_first'] . ' ' . $o['ct_last']) : '<span class="text-muted">Not assigned</span>' ?></td>
                            <td><?= (int)$o['student_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($offerings)): ?>
                        <tr><td colspan="5" class="empty-state">No class-section offerings yet for this year.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
