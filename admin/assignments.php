<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireArea('admin');

$pageTitle = 'Teacher Assignments';
$activeMenu = 'assignments';
$db = Database::connection();

$activeYear = $db->query("SELECT * FROM academic_years WHERE is_active=1 LIMIT 1")->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf() && $activeYear) {
    $action = $_POST['action'] ?? '';

    if ($action === 'assign') {
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        $classSectionId = (int)($_POST['class_section_id'] ?? 0);
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        try {
            $db->prepare('INSERT INTO teacher_assignments (teacher_id, class_section_id, subject_id, academic_year_id) VALUES (:t,:cs,:s,:y)')
               ->execute(['t' => $teacherId, 'cs' => $classSectionId, 's' => $subjectId, 'y' => $activeYear['id']]);
            flash('success', 'Assignment created.');
        } catch (PDOException $e) {
            flash('error', 'This teacher is already assigned to that subject in that class-section.');
        }
        redirect(base_url('admin/assignments.php'));
    }

    if ($action === 'set_class_teacher') {
        $classSectionId = (int)($_POST['class_section_id'] ?? 0);
        $teacherId = (int)($_POST['teacher_id'] ?? 0) ?: null;
        $db->prepare('UPDATE class_sections SET class_teacher_id = :t WHERE id = :cs')
           ->execute(['t' => $teacherId, 'cs' => $classSectionId]);
        flash('success', 'Class teacher updated.');
        redirect(base_url('admin/assignments.php'));
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare('DELETE FROM teacher_assignments WHERE id = :id')->execute(['id' => $id]);
        flash('success', 'Assignment removed.');
        redirect(base_url('admin/assignments.php'));
    }
}

$teachers = $db->query('SELECT id, first_name, last_name FROM teachers WHERE status="active" ORDER BY first_name')->fetchAll();
$subjects = $db->query('SELECT id, name, code FROM subjects WHERE status="active" ORDER BY name')->fetchAll();

$classSections = [];
$assignments = [];
if ($activeYear) {
    $classSections = $db->prepare("
        SELECT cs.id, c.name AS class_name, s.name AS section_name, cs.class_teacher_id
        FROM class_sections cs JOIN classes c ON cs.class_id=c.id JOIN sections s ON cs.section_id=s.id
        WHERE cs.academic_year_id = :y ORDER BY c.sort_order, s.name
    ");
    $classSections->execute(['y' => $activeYear['id']]);
    $classSections = $classSections->fetchAll();

    $assignments = $db->prepare("
        SELECT ta.id, t.first_name, t.last_name, sub.name AS subject_name, c.name AS class_name, sec.name AS section_name
        FROM teacher_assignments ta
        JOIN teachers t ON ta.teacher_id = t.id
        JOIN subjects sub ON ta.subject_id = sub.id
        JOIN class_sections cs ON ta.class_section_id = cs.id
        JOIN classes c ON cs.class_id = c.id
        JOIN sections sec ON cs.section_id = sec.id
        WHERE ta.academic_year_id = :y
        ORDER BY c.sort_order, sec.name, sub.name
    ");
    $assignments->execute(['y' => $activeYear['id']]);
    $assignments = $assignments->fetchAll();
}

include __DIR__ . '/../includes/layout_start.php';
?>
<?php if (!$activeYear): ?>
    <div class="alert alert-warning">Set an active academic year and offer at least one class-section before assigning teachers.</div>
<?php else: ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">Assign Teacher to Subject</div>
            <div class="card-body">
                <form method="post">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="assign">
                    <div class="mb-2">
                        <label class="form-label small">Class - Section</label>
                        <select name="class_section_id" class="form-select" required>
                            <?php foreach ($classSections as $cs): ?>
                                <option value="<?= (int)$cs['id'] ?>"><?= e($cs['class_name'] . ' - ' . $cs['section_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Subject</label>
                        <select name="subject_id" class="form-select" required>
                            <?php foreach ($subjects as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Teacher</label>
                        <select name="teacher_id" class="form-select" required>
                            <?php foreach ($teachers as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['first_name'] . ' ' . $t['last_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-primary w-100">Create Assignment</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Set Class Teacher</div>
            <div class="card-body">
                <form method="post">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="set_class_teacher">
                    <div class="mb-2">
                        <label class="form-label small">Class - Section</label>
                        <select name="class_section_id" class="form-select" required>
                            <?php foreach ($classSections as $cs): ?>
                                <option value="<?= (int)$cs['id'] ?>"><?= e($cs['class_name'] . ' - ' . $cs['section_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Class Teacher</label>
                        <select name="teacher_id" class="form-select">
                            <option value="">— None —</option>
                            <?php foreach ($teachers as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['first_name'] . ' ' . $t['last_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-outline-primary w-100">Update Class Teacher</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Current Assignments — <?= e($activeYear['name']) ?></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Class</th><th>Subject</th><th>Teacher</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($assignments as $a): ?>
                        <tr>
                            <td><?= e($a['class_name'] . ' - ' . $a['section_name']) ?></td>
                            <td><?= e($a['subject_name']) ?></td>
                            <td><?= e($a['first_name'] . ' ' . $a['last_name']) ?></td>
                            <td>
                                <form method="post" data-confirm="Remove this assignment?">
                                    <?= Auth::csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($assignments)): ?>
                        <tr><td colspan="4" class="empty-state">No assignments yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
