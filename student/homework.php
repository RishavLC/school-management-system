<?php
require_once __DIR__ . '/../core/bootstrap.php';
$student = require_student_profile();

$pageTitle = 'Homework';
$activeMenu = 'homework';
$db = Database::connection();
$csId = $student['enrollment']['class_section_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_homework'])) {
    if (!Auth::verifyCsrf()) {
        flash('error', 'Your session expired. Please try again.');
    } else {
        $homeworkId = (int)($_POST['homework_id'] ?? 0);
        $text = trim((string)($_POST['submission_text'] ?? ''));

        $hw = $db->prepare('SELECT * FROM homework WHERE id = :id AND class_section_id = :cs');
        $hw->execute(['id' => $homeworkId, 'cs' => $csId]);
        $hw = $hw->fetch();

        if (!$hw) {
            flash('error', 'Homework not found.');
        } elseif ($text === '') {
            flash('error', 'Please write a short submission note before submitting.');
        } else {
            $status = (strtotime(date('Y-m-d')) > strtotime($hw['due_date'])) ? 'late' : 'submitted';
            $db->prepare("
                INSERT INTO homework_submissions (homework_id, student_id, submission_text, status)
                VALUES (:h, :s, :t, :st)
                ON DUPLICATE KEY UPDATE submission_text = :t2, status = :st2, submitted_at = NOW()
            ")->execute(['h' => $homeworkId, 's' => $student['id'], 't' => $text, 'st' => $status, 't2' => $text, 'st2' => $status]);
            flash('success', 'Homework submitted successfully.');
        }
    }
    redirect(base_url('student/homework.php'));
}

$homework = [];
if ($csId) {
    $stmt = $db->prepare("
        SELECT h.*, subj.name AS subject_name, CONCAT(t.first_name,' ',t.last_name) AS teacher_name,
            hs.status AS sub_status, hs.submission_text, hs.submitted_at, hs.marks_obtained, hs.teacher_remarks
        FROM homework h
        JOIN subjects subj ON subj.id = h.subject_id
        JOIN teachers t ON t.id = h.teacher_id
        LEFT JOIN homework_submissions hs ON hs.homework_id = h.id AND hs.student_id = :s
        WHERE h.class_section_id = :cs
        ORDER BY h.due_date DESC
    ");
    $stmt->execute(['s' => $student['id'], 'cs' => $csId]);
    $homework = $stmt->fetchAll();
}

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="row g-3">
    <?php foreach ($homework as $h): $status = $h['sub_status'] ?: 'pending'; $overdue = !$h['sub_status'] && strtotime(date('Y-m-d')) > strtotime($h['due_date']); ?>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <h6 class="fw-bold mb-0"><?= e($h['title']) ?></h6>
                        <?= status_badge($overdue ? 'absent' : $status) ?>
                    </div>
                    <div class="small text-muted mb-2"><?= e($h['subject_name']) ?> &middot; <?= e($h['teacher_name']) ?></div>
                    <p class="small mb-2"><?= nl2br(e($h['description'] ?? '')) ?></p>
                    <div class="small text-muted mb-2">
                        Assigned <?= format_date($h['created_at']) ?> &middot; Due <?= format_date($h['due_date']) ?>
                        <?php if ($h['attachment']): ?> &middot; <a href="<?= e(base_url('assets/uploads/homework/' . $h['attachment'])) ?>" target="_blank">Download attachment</a><?php endif; ?>
                    </div>

                    <?php if ($h['sub_status']): ?>
                        <div class="border rounded p-2 bg-light">
                            <div class="small fw-semibold mb-1">Your submission (<?= format_date($h['submitted_at']) ?>)</div>
                            <div class="small mb-1"><?= nl2br(e($h['submission_text'])) ?></div>
                            <?php if ($h['sub_status'] === 'checked'): ?>
                                <div class="small text-success">Marks: <?= e($h['marks_obtained'] ?? '-') ?><?php if ($h['teacher_remarks']): ?> &middot; <?= e($h['teacher_remarks']) ?><?php endif; ?></div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <form method="post" class="mt-2">
                            <?= Auth::csrfField() ?>
                            <input type="hidden" name="homework_id" value="<?= (int)$h['id'] ?>">
                            <textarea name="submission_text" class="form-control form-control-sm mb-2" rows="2" placeholder="Describe your submission..." required></textarea>
                            <button type="submit" name="submit_homework" value="1" class="btn btn-sm btn-primary">Submit Homework</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($homework)): ?><div class="col-12"><div class="empty-state">No homework assigned yet.</div></div><?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
