<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireArea('admin');

$pageTitle = 'Notices';
$activeMenu = 'notices';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf()) {
    $v = new Validator($_POST);
    $v->required('title', 'Title')->required('description', 'Description')->date('notice_date', 'Date')
      ->in('audience', ['everyone','teachers','students','parents','class'], 'Audience');

    if (!$v->fails()) {
        $classSectionId = $_POST['audience'] === 'class' ? (int)($_POST['class_section_id'] ?? 0) : null;
        $stmt = $db->prepare("
            INSERT INTO notices (title, description, notice_date, audience, class_section_id, created_by)
            VALUES (:t,:d,:dt,:a,:cs,:u)
        ");
        $stmt->execute([
            't' => $_POST['title'], 'd' => $_POST['description'], 'dt' => $_POST['notice_date'],
            'a' => $_POST['audience'], 'cs' => $classSectionId, 'u' => Auth::id(),
        ]);
        $noticeId = $db->lastInsertId();

        // Notify relevant users in-app (design allows SMS/push to hook in here later)
        $targetSql = match ($_POST['audience']) {
            'teachers' => "SELECT id FROM users WHERE role='teacher' AND status='active'",
            'students' => "SELECT id FROM users WHERE role='student' AND status='active'",
            'parents'  => "SELECT id FROM users WHERE role='parent' AND status='active'",
            'class'    => "SELECT u.id FROM users u
                           JOIN students s ON s.user_id = u.id
                           JOIN student_enrollments se ON se.student_id = s.id AND se.class_section_id = " . (int)$classSectionId . "
                           WHERE u.status='active'",
            default    => "SELECT id FROM users WHERE status='active'",
        };
        foreach ($db->query($targetSql)->fetchAll() as $u) {
            $db->prepare('INSERT INTO notifications (user_id, title, message, type, link) VALUES (:u,:t,:m,"notice",:l)')
               ->execute(['u' => $u['id'], 't' => 'New Notice: ' . $_POST['title'], 'm' => mb_strimwidth($_POST['description'], 0, 100, '...'), 'l' => base_url('notices.php')]);
        }

        Auth::log(Auth::id(), 'Published notice: ' . $_POST['title']);
        flash('success', 'Notice published.');
    } else {
        flash('error', implode(' ', $v->errors()));
    }
    redirect(base_url('admin/notices.php'));
}

$notices = $db->query("
    SELECT n.*, u.username AS created_by_name, c.name AS class_name, sec.name AS section_name
    FROM notices n
    JOIN users u ON u.id = n.created_by
    LEFT JOIN class_sections cs ON cs.id = n.class_section_id
    LEFT JOIN classes c ON c.id = cs.class_id
    LEFT JOIN sections sec ON sec.id = cs.section_id
    ORDER BY n.notice_date DESC, n.id DESC
")->fetchAll();

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Publish Notice</div>
            <div class="card-body">
                <form method="post">
                    <?= Auth::csrfField() ?>
                    <div class="mb-2"><label class="form-label small">Title</label><input class="form-control" name="title" required></div>
                    <div class="mb-2"><label class="form-label small">Description</label><textarea class="form-control" name="description" rows="4" required></textarea></div>
                    <div class="mb-2"><label class="form-label small">Date</label><input class="form-control" type="date" name="notice_date" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="mb-3">
                        <label class="form-label small">Audience</label>
                        <select class="form-select" name="audience" id="audienceSelect" required onchange="document.getElementById('classPicker').style.display = this.value==='class' ? 'block':'none'">
                            <option value="everyone">Everyone</option>
                            <option value="teachers">Teachers only</option>
                            <option value="students">Students only</option>
                            <option value="parents">Parents only</option>
                            <option value="class">Specific class/section</option>
                        </select>
                    </div>
                    <div class="mb-3" id="classPicker" style="display:none">
                        <label class="form-label small">Class - Section</label>
                        <select class="form-select" name="class_section_id">
                            <?php foreach ($classSections as $cs): ?><option value="<?= (int)$cs['id'] ?>"><?= e($cs['class_name'].' - '.$cs['section_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-primary w-100"><i class="fa-solid fa-bullhorn me-1"></i>Publish</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">All Notices</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($notices as $n): ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <div class="fw-semibold"><?= e($n['title']) ?></div>
                            <small class="text-muted"><?= format_date($n['notice_date']) ?></small>
                        </div>
                        <div class="text-muted small mb-1"><?= e($n['description']) ?></div>
                        <span class="badge text-bg-light border">
                            <?= $n['audience'] === 'class' ? e($n['class_name'].' - '.$n['section_name']) : e(ucfirst($n['audience'])) ?>
                        </span>
                        <span class="small text-muted">by <?= e($n['created_by_name']) ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($notices)): ?><li class="list-group-item empty-state">No notices published yet.</li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
