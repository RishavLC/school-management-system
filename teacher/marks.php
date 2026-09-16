<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireArea('teacher');

$pageTitle = 'Enter Marks';
$activeMenu = 'marks';
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

// Exam schedules this teacher is actually responsible for (their subject + class assignment)
$mySchedules = $db->prepare("
    SELECT es.id, e.name AS exam_name, c.name AS class_name, sec.name AS section_name, subj.name AS subject_name,
           es.full_marks, es.pass_marks, es.class_section_id, es.subject_id
    FROM exam_schedule es
    JOIN exams e ON e.id = es.exam_id
    JOIN class_sections cs ON cs.id = es.class_section_id
    JOIN classes c ON c.id = cs.class_id
    JOIN sections sec ON sec.id = cs.section_id
    JOIN subjects subj ON subj.id = es.subject_id
    JOIN teacher_assignments ta ON ta.class_section_id = es.class_section_id AND ta.subject_id = es.subject_id
    WHERE ta.teacher_id = :t
    ORDER BY e.start_date DESC
");
$mySchedules->execute(['t' => $teacher['id']]);
$mySchedules = $mySchedules->fetchAll();
$allowedIds = array_column($mySchedules, 'id');

$scheduleId = (int) ($_GET['exam_schedule_id'] ?? ($mySchedules[0]['id'] ?? 0));
if ($scheduleId && !in_array($scheduleId, $allowedIds, true)) {
    $scheduleId = $mySchedules[0]['id'] ?? 0;
}
$current = null;
foreach ($mySchedules as $s) { if ($s['id'] === $scheduleId) { $current = $s; break; } }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf()) {
    $postSchedule = (int) $_POST['exam_schedule_id'];
    if (!in_array($postSchedule, $allowedIds, true)) {
        flash('error', 'You are not assigned to enter marks for that exam.');
        redirect(base_url('teacher/marks.php'));
    }
    $fullMarks = 0;
    foreach ($mySchedules as $s) { if ($s['id'] === $postSchedule) { $fullMarks = $s['full_marks']; break; } }

    $obtained = $_POST['marks'] ?? [];
    $absent = $_POST['absent'] ?? [];
    $db->beginTransaction();
    try {
        foreach ($obtained as $studentId => $val) {
            $isAbsent = isset($absent[$studentId]) ? 1 : 0;
            $val = $isAbsent ? null : ($val === '' ? null : (float) $val);
            $grade = null;
            if (!$isAbsent && $val !== null && $fullMarks > 0) {
                $pct = $val / $fullMarks * 100;
                $g = $db->prepare('SELECT grade FROM grade_scale WHERE :p BETWEEN min_percent AND max_percent LIMIT 1');
                $g->execute(['p' => $pct]);
                $grade = $g->fetchColumn() ?: null;
            }
            $stmt = $db->prepare("
                INSERT INTO marks (exam_schedule_id, student_id, obtained_marks, is_absent, grade, entered_by)
                VALUES (:es, :s, :m, :ab, :g, :u)
                ON DUPLICATE KEY UPDATE obtained_marks = VALUES(obtained_marks), is_absent = VALUES(is_absent), grade = VALUES(grade), entered_by = VALUES(entered_by)
            ");
            $stmt->execute(['es' => $postSchedule, 's' => (int) $studentId, 'm' => $val, 'ab' => $isAbsent, 'g' => $grade, 'u' => Auth::id()]);
        }
        $db->commit();
        Auth::log(Auth::id(), 'Entered marks for exam schedule #' . $postSchedule);
        flash('success', 'Marks saved.');
    } catch (Throwable $e) {
        $db->rollBack();
        flash('error', 'Could not save marks.');
    }
    redirect(base_url('teacher/marks.php?exam_schedule_id=' . $postSchedule));
}

$students = [];
$existingMarks = [];
if ($current) {
    $stmt = $db->prepare("
        SELECT s.id, s.first_name, s.last_name, se.roll_number
        FROM student_enrollments se JOIN students s ON s.id = se.student_id
        WHERE se.class_section_id = :cs
        ORDER BY CAST(se.roll_number AS UNSIGNED), s.first_name
    ");
    $stmt->execute(['cs' => $current['class_section_id']]);
    $students = $stmt->fetchAll();

    $em = $db->prepare('SELECT student_id, obtained_marks, is_absent FROM marks WHERE exam_schedule_id = :es');
    $em->execute(['es' => $scheduleId]);
    foreach ($em->fetchAll() as $row) { $existingMarks[$row['student_id']] = $row; }
}

include __DIR__ . '/../includes/layout_start.php';
?>
<form class="row g-2 align-items-end mb-3" method="get">
    <div class="col-md-8">
        <label class="form-label small">Exam / Class / Subject</label>
        <select name="exam_schedule_id" class="form-select" onchange="this.form.submit()">
            <?php foreach ($mySchedules as $s): ?>
                <option value="<?= (int)$s['id'] ?>" <?= $scheduleId === (int)$s['id'] ? 'selected' : '' ?>>
                    <?= e($s['exam_name'].' — '.$s['class_name'].' '.$s['section_name'].' — '.$s['subject_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<?php if (empty($mySchedules)): ?>
    <div class="card"><div class="empty-state py-5">No exam schedules assigned to you yet.</div></div>
<?php elseif ($current): ?>
<form method="post">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="exam_schedule_id" value="<?= $scheduleId ?>">
    <div class="card">
        <div class="card-header">
            <?= e($current['exam_name']) ?> — <?= e($current['subject_name']) ?>
            <span class="text-muted small">(Full marks: <?= e($current['full_marks']) ?>, Pass: <?= e($current['pass_marks']) ?>)</span>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Roll</th><th>Student</th><th style="width:140px">Marks</th><th style="width:100px">Absent</th></tr></thead>
                <tbody>
                <?php foreach ($students as $s): $ex = $existingMarks[$s['id']] ?? null; ?>
                    <tr>
                        <td><?= e($s['roll_number'] ?? '-') ?></td>
                        <td><?= e($s['first_name'].' '.$s['last_name']) ?></td>
                        <td><input type="number" step="0.5" min="0" max="<?= e($current['full_marks']) ?>" class="form-control form-control-sm" name="marks[<?= (int)$s['id'] ?>]" value="<?= $ex && !$ex['is_absent'] ? e($ex['obtained_marks']) : '' ?>"></td>
                        <td class="text-center"><input type="checkbox" class="form-check-input" name="absent[<?= (int)$s['id'] ?>]" <?= $ex && $ex['is_absent'] ? 'checked' : '' ?>></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($students)): ?><tr><td colspan="4" class="empty-state">No students enrolled in this class.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($students)): ?>
        <div class="card-body"><button class="btn btn-primary px-4"><i class="fa-solid fa-floppy-disk me-1"></i>Save Marks</button></div>
        <?php endif; ?>
    </div>
</form>
<?php endif; ?>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
