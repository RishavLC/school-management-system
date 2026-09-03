<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireArea('admin');

$pageTitle = 'Examinations';
$activeMenu = 'exams';
$db = Database::connection();
$activeYear = $db->query("SELECT * FROM academic_years WHERE is_active=1 LIMIT 1")->fetch();

$classSections = [];
$subjects = [];
if ($activeYear) {
    $classSections = $db->prepare("
        SELECT cs.id, c.name AS class_name, sec.name AS section_name
        FROM class_sections cs JOIN classes c ON c.id=cs.class_id JOIN sections sec ON sec.id=cs.section_id
        WHERE cs.academic_year_id = :y ORDER BY c.sort_order, sec.name
    ");
    $classSections->execute(['y' => $activeYear['id']]);
    $classSections = $classSections->fetchAll();
    $subjects = $db->query("SELECT id, name FROM subjects WHERE status='active' ORDER BY name")->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf() && $activeYear) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_exam') {
        $v = new Validator($_POST);
        $v->required('name', 'Exam name')->date('start_date', 'Start date')->date('end_date', 'End date');
        if (!$v->fails()) {
            $db->prepare('INSERT INTO exams (name, academic_year_id, start_date, end_date, status) VALUES (:n,:y,:s,:e,"upcoming")')
               ->execute(['n' => $_POST['name'], 'y' => $activeYear['id'], 's' => $_POST['start_date'], 'e' => $_POST['end_date']]);
            flash('success', 'Examination created.');
        } else {
            flash('error', implode(' ', $v->errors()));
        }
        redirect(base_url('admin/exams.php'));
    }

    if ($action === 'add_schedule') {
        try {
            $db->prepare("
                INSERT INTO exam_schedule (exam_id, class_section_id, subject_id, exam_date, start_time, end_time, room, full_marks, pass_marks)
                VALUES (:ex,:cs,:su,:d,:st,:et,:r,:fm,:pm)
            ")->execute([
                'ex' => (int)$_POST['exam_id'], 'cs' => (int)$_POST['class_section_id'], 'su' => (int)$_POST['subject_id'],
                'd' => $_POST['exam_date'], 'st' => $_POST['start_time'], 'et' => $_POST['end_time'],
                'r' => $_POST['room'] ?: null, 'fm' => $_POST['full_marks'] ?: 100, 'pm' => $_POST['pass_marks'] ?: 40,
            ]);
            flash('success', 'Exam schedule added.');
        } catch (PDOException $e) {
            flash('error', 'This subject is already scheduled for that class in this exam.');
        }
        redirect(base_url('admin/exams.php'));
    }

    if ($action === 'publish') {
        $db->prepare("UPDATE exams SET status='result_published' WHERE id = :id")->execute(['id' => (int)$_POST['id']]);
        flash('success', 'Results published — students and parents can now view them.');
        redirect(base_url('admin/exams.php'));
    }
}

$exams = $db->prepare('SELECT * FROM exams WHERE academic_year_id = :y ORDER BY start_date DESC');
$exams->execute(['y' => $activeYear['id'] ?? 0]);
$exams = $exams->fetchAll();

$schedules = $db->query("
    SELECT es.*, e.name AS exam_name, c.name AS class_name, sec.name AS section_name, subj.name AS subject_name
    FROM exam_schedule es
    JOIN exams e ON e.id = es.exam_id
    JOIN class_sections cs ON cs.id = es.class_section_id
    JOIN classes c ON c.id = cs.class_id
    JOIN sections sec ON sec.id = cs.section_id
    JOIN subjects subj ON subj.id = es.subject_id
    ORDER BY es.exam_date DESC LIMIT 30
")->fetchAll();

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">Create Examination</div>
            <div class="card-body">
                <form method="post">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="create_exam">
                    <div class="mb-2"><label class="form-label small">Exam Name</label><input class="form-control" name="name" required placeholder="e.g. First Terminal Examination"></div>
                    <div class="mb-2"><label class="form-label small">Start Date</label><input class="form-control" type="date" name="start_date" required></div>
                    <div class="mb-3"><label class="form-label small">End Date</label><input class="form-control" type="date" name="end_date" required></div>
                    <button class="btn btn-primary w-100">Create Exam</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Add Exam Schedule (Subject)</div>
            <div class="card-body">
                <form method="post">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="add_schedule">
                    <div class="mb-2">
                        <label class="form-label small">Examination</label>
                        <select name="exam_id" class="form-select" required>
                            <?php foreach ($exams as $ex): ?><option value="<?= (int)$ex['id'] ?>"><?= e($ex['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Class - Section</label>
                        <select name="class_section_id" class="form-select" required>
                            <?php foreach ($classSections as $cs): ?><option value="<?= (int)$cs['id'] ?>"><?= e($cs['class_name'].' - '.$cs['section_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Subject</label>
                        <select name="subject_id" class="form-select" required>
                            <?php foreach ($subjects as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label small">Date</label><input class="form-control" type="date" name="exam_date" required></div>
                        <div class="col-3"><label class="form-label small">Start</label><input class="form-control" type="time" name="start_time" required></div>
                        <div class="col-3"><label class="form-label small">End</label><input class="form-control" type="time" name="end_time" required></div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-4"><label class="form-label small">Room</label><input class="form-control" name="room"></div>
                        <div class="col-4"><label class="form-label small">Full Marks</label><input class="form-control" type="number" name="full_marks" value="100"></div>
                        <div class="col-4"><label class="form-label small">Pass Marks</label><input class="form-control" type="number" name="pass_marks" value="40"></div>
                    </div>
                    <button class="btn btn-outline-primary w-100">Add Schedule</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">Examinations — <?= $activeYear ? e($activeYear['name']) : '-' ?></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Name</th><th>Period</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($exams as $ex): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($ex['name']) ?></td>
                            <td><?= format_date($ex['start_date']) ?> &ndash; <?= format_date($ex['end_date']) ?></td>
                            <td><?= status_badge($ex['status'] === 'result_published' ? 'active' : ($ex['status'] === 'upcoming' ? 'pending' : $ex['status'])) ?> <span class="small text-muted"><?= e(str_replace('_',' ',ucfirst($ex['status']))) ?></span></td>
                            <td>
                                <?php if ($ex['status'] !== 'result_published'): ?>
                                <form method="post" data-confirm="Publish results for <?= e($ex['name']) ?>? Students and parents will see them immediately.">
                                    <?= Auth::csrfField() ?>
                                    <input type="hidden" name="action" value="publish">
                                    <input type="hidden" name="id" value="<?= (int)$ex['id'] ?>">
                                    <button class="btn btn-sm btn-outline-success">Publish Results</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($exams)): ?><tr><td colspan="4" class="empty-state">No examinations created yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Recent Exam Schedule</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Exam</th><th>Class</th><th>Subject</th><th>Date</th><th>Marks</th></tr></thead>
                    <tbody>
                    <?php foreach ($schedules as $s): ?>
                        <tr>
                            <td><?= e($s['exam_name']) ?></td>
                            <td><?= e($s['class_name'].' - '.$s['section_name']) ?></td>
                            <td><?= e($s['subject_name']) ?></td>
                            <td><?= format_date($s['exam_date']) ?></td>
                            <td><?= e($s['full_marks']) ?> (pass <?= e($s['pass_marks']) ?>)</td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($schedules)): ?><tr><td colspan="5" class="empty-state">No exam schedule yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
