<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireArea('teacher');

$pageTitle = 'Take Attendance';
$activeMenu = 'attendance';
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

$myClasses = $db->prepare("
    SELECT DISTINCT cs.id, c.name AS class_name, sec.name AS section_name
    FROM teacher_assignments ta
    JOIN class_sections cs ON cs.id = ta.class_section_id
    JOIN classes c ON c.id = cs.class_id
    JOIN sections sec ON sec.id = cs.section_id
    WHERE ta.teacher_id = :t
    ORDER BY c.sort_order, sec.name
");
$myClasses->execute(['t' => $teacher['id']]);
$myClasses = $myClasses->fetchAll();

$classSectionId = (int) ($_GET['class_section_id'] ?? ($myClasses[0]['id'] ?? 0));
$date = $_GET['date'] ?? date('Y-m-d');

// Server-side check: this teacher must actually be assigned to the requested class
$allowed = array_column($myClasses, 'id');
if ($classSectionId && !in_array($classSectionId, $allowed, true)) {
    $classSectionId = $myClasses[0]['id'] ?? 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf()) {
    $postClassSection = (int) $_POST['class_section_id'];
    $postDate = $_POST['date'];
    if (!in_array($postClassSection, $allowed, true)) {
        flash('error', 'You are not assigned to that class.');
        redirect(base_url('teacher/attendance.php'));
    }
    $statuses = $_POST['status'] ?? [];
    $db->beginTransaction();
    try {
        foreach ($statuses as $studentId => $status) {
            if (!in_array($status, ['present', 'absent', 'late'], true)) continue;
            $stmt = $db->prepare("
                INSERT INTO attendance (student_id, class_section_id, attendance_date, status, recorded_by)
                VALUES (:s, :cs, :d, :st, :u)
                ON DUPLICATE KEY UPDATE status = VALUES(status), class_section_id = VALUES(class_section_id), recorded_by = VALUES(recorded_by)
            ");
            $stmt->execute(['s' => (int) $studentId, 'cs' => $postClassSection, 'd' => $postDate, 'st' => $status, 'u' => Auth::id()]);
        }
        $db->commit();
        Auth::log(Auth::id(), 'Marked attendance for class-section #' . $postClassSection . ' on ' . $postDate);
        flash('success', 'Attendance saved.');
    } catch (Throwable $e) {
        $db->rollBack();
        flash('error', 'Could not save attendance.');
    }
    redirect(base_url('teacher/attendance.php?class_section_id=' . $postClassSection . '&date=' . $postDate));
}

$students = [];
$existing = [];
if ($classSectionId) {
    $stmt = $db->prepare("
        SELECT s.id, s.first_name, s.last_name, s.admission_number, se.roll_number
        FROM student_enrollments se
        JOIN students s ON s.id = se.student_id
        WHERE se.class_section_id = :cs
        ORDER BY CAST(se.roll_number AS UNSIGNED), s.first_name
    ");
    $stmt->execute(['cs' => $classSectionId]);
    $students = $stmt->fetchAll();

    $existingStmt = $db->prepare("SELECT student_id, status FROM attendance WHERE class_section_id = :cs AND attendance_date = :d");
    $existingStmt->execute(['cs' => $classSectionId, 'd' => $date]);
    foreach ($existingStmt->fetchAll() as $row) { $existing[$row['student_id']] = $row['status']; }
}

include __DIR__ . '/../includes/layout_start.php';
?>
<form class="row g-2 align-items-end mb-3" method="get">
    <div class="col-md-4">
        <label class="form-label small">Class - Section</label>
        <select name="class_section_id" class="form-select" onchange="this.form.submit()">
            <?php foreach ($myClasses as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $classSectionId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['class_name'].' - '.$c['section_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label small">Date</label>
        <input type="date" name="date" class="form-control" value="<?= e($date) ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
    </div>
</form>

<?php if (empty($myClasses)): ?>
    <div class="card"><div class="empty-state py-5">You are not assigned to any class yet.</div></div>
<?php else: ?>
<form method="post" id="attendanceForm">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="class_section_id" value="<?= $classSectionId ?>">
    <input type="hidden" name="date" value="<?= e($date) ?>">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Mark Attendance — <?= e($date) ?></span>
            <button type="button" class="btn btn-sm btn-outline-success" onclick="markAll('present')">Mark All Present</button>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Roll</th><th>Student</th><th style="width:320px">Status</th></tr></thead>
                <tbody>
                <?php foreach ($students as $s): $current = $existing[$s['id']] ?? 'present'; ?>
                    <tr>
                        <td><?= e($s['roll_number'] ?? '-') ?></td>
                        <td><?= e($s['first_name'].' '.$s['last_name']) ?> <span class="text-muted small">(<?= e($s['admission_number']) ?>)</span></td>
                        <td>
                            <div class="btn-group att-group" role="group" data-student="<?= (int)$s['id'] ?>">
                                <input type="radio" class="btn-check" name="status[<?= (int)$s['id'] ?>]" id="p<?= (int)$s['id'] ?>" value="present" <?= $current==='present'?'checked':'' ?>>
                                <label class="btn btn-outline-success btn-sm" for="p<?= (int)$s['id'] ?>">Present</label>

                                <input type="radio" class="btn-check" name="status[<?= (int)$s['id'] ?>]" id="a<?= (int)$s['id'] ?>" value="absent" <?= $current==='absent'?'checked':'' ?>>
                                <label class="btn btn-outline-danger btn-sm" for="a<?= (int)$s['id'] ?>">Absent</label>

                                <input type="radio" class="btn-check" name="status[<?= (int)$s['id'] ?>]" id="l<?= (int)$s['id'] ?>" value="late" <?= $current==='late'?'checked':'' ?>>
                                <label class="btn btn-outline-warning btn-sm" for="l<?= (int)$s['id'] ?>">Late</label>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($students)): ?><tr><td colspan="3" class="empty-state">No students enrolled in this class yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($students)): ?>
        <div class="card-body">
            <button class="btn btn-primary px-4"><i class="fa-solid fa-floppy-disk me-1"></i>Save Attendance</button>
        </div>
        <?php endif; ?>
    </div>
</form>
<script>
function markAll(status) {
    document.querySelectorAll('.att-group').forEach(function(g) {
        var input = g.querySelector('input[value="' + status + '"]');
        if (input) input.checked = true;
    });
}
</script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
