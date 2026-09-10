<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireArea('teacher');

$pageTitle = 'Homework';
$activeMenu = 'homework';
$db = Database::connection();

$teacher = $db->prepare('SELECT * FROM teachers WHERE user_id = :u');
$teacher->execute(['u' => Auth::id()]);
$teacher = $teacher->fetch();
if (!$teacher) {
    include __DIR__ . '/../includes/layout_start.php';
    echo '<div class="empty-state">No teacher profile is linked to your account.</div>';
    include __DIR__ . '/../includes/layout_end.php';
    exit;
}

$myAssignments = $db->prepare("
    SELECT DISTINCT cs.id AS class_section_id, c.name AS class_name, sec.name AS section_name,
           subj.id AS subject_id, subj.name AS subject_name
    FROM teacher_assignments ta
    JOIN class_sections cs ON cs.id = ta.class_section_id
    JOIN classes c ON c.id = cs.class_id
    JOIN sections sec ON sec.id = cs.section_id
    JOIN subjects subj ON subj.id = ta.subject_id
    WHERE ta.teacher_id = :t
    ORDER BY c.sort_order, subj.name
");
$myAssignments->execute(['t' => $teacher['id']]);
$myAssignments = $myAssignments->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf()) {
    $cs = (int) $_POST['class_section_id'];
    $subj = (int) $_POST['subject_id'];
    $valid = false;
    foreach ($myAssignments as $a) { if ($a['class_section_id'] === $cs && $a['subject_id'] === $subj) { $valid = true; break; } }

    $v = new Validator($_POST);
    $v->required('title', 'Title')->required('due_date', 'Due date');
    if (!$valid) {
        flash('error', 'You are not assigned to teach that subject in that class.');
    } elseif ($v->fails()) {
        flash('error', implode(' ', $v->errors()));
    } else {
        $db->prepare("
            INSERT INTO homework (class_section_id, subject_id, teacher_id, title, description, due_date)
            VALUES (:cs, :su, :t, :ti, :de, :d)
        ")->execute([
            'cs' => $cs, 'su' => $subj, 't' => $teacher['id'],
            'ti' => $_POST['title'], 'de' => $_POST['description'] ?? null, 'd' => $_POST['due_date'],
        ]);
        Auth::log(Auth::id(), 'Created homework: ' . $_POST['title']);
        flash('success', 'Homework assigned.');
    }
    redirect(base_url('teacher/homework.php'));
}

$myHomework = $db->prepare("
    SELECT h.*, c.name AS class_name, sec.name AS section_name, subj.name AS subject_name,
           (SELECT COUNT(*) FROM student_enrollments se WHERE se.class_section_id = h.class_section_id) AS total_students,
           (SELECT COUNT(*) FROM homework_submissions hs WHERE hs.homework_id = h.id) AS submitted_count
    FROM homework h
    JOIN class_sections cs ON cs.id = h.class_section_id
    JOIN classes c ON c.id = cs.class_id
    JOIN sections sec ON sec.id = cs.section_id
    JOIN subjects subj ON subj.id = h.subject_id
    WHERE h.teacher_id = :t
    ORDER BY h.due_date DESC
");
$myHomework->execute(['t' => $teacher['id']]);
$myHomework = $myHomework->fetchAll();

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Assign Homework</div>
            <div class="card-body">
                <form method="post">
                    <?= Auth::csrfField() ?>
                    <div class="mb-2">
                        <label class="form-label small">Class - Subject</label>
                        <select name="class_subject" class="form-select" id="classSubjectPicker" required>
                            <option value="">Select...</option>
                            <?php foreach ($myAssignments as $a): ?>
                                <option value="<?= (int)$a['class_section_id'] ?>|<?= (int)$a['subject_id'] ?>"><?= e($a['class_name'].' '.$a['section_name'].' — '.$a['subject_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="class_section_id" id="classSectionInput">
                        <input type="hidden" name="subject_id" id="subjectInput">
                    </div>
                    <div class="mb-2"><label class="form-label small">Title</label><input class="form-control" name="title" required></div>
                    <div class="mb-2"><label class="form-label small">Description</label><textarea class="form-control" name="description" rows="3"></textarea></div>
                    <div class="mb-3"><label class="form-label small">Due Date</label><input class="form-control" type="date" name="due_date" required></div>
                    <button class="btn btn-primary w-100" <?= empty($myAssignments) ? 'disabled' : '' ?>>Assign</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">My Homework</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Title</th><th>Class</th><th>Subject</th><th>Due</th><th>Submitted</th></tr></thead>
                    <tbody>
                    <?php foreach ($myHomework as $h): ?>
                        <tr>
                            <td><?= e($h['title']) ?></td>
                            <td><?= e($h['class_name'].' '.$h['section_name']) ?></td>
                            <td><?= e($h['subject_name']) ?></td>
                            <td><?= format_date($h['due_date']) ?></td>
                            <td><span class="badge text-bg-light border"><?= (int)$h['submitted_count'] ?>/<?= (int)$h['total_students'] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($myHomework)): ?><tr><td colspan="5" class="empty-state">No homework assigned yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('classSubjectPicker').addEventListener('change', function() {
    var parts = this.value.split('|');
    document.getElementById('classSectionInput').value = parts[0] || '';
    document.getElementById('subjectInput').value = parts[1] || '';
});
</script>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
