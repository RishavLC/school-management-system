<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireArea('admin');

$pageTitle = 'Class Health Dashboard';
$activeMenu = 'class_health';
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

$selectedCs = (int) ($_GET['class_section_id'] ?? ($classSections[0]['id'] ?? 0));

$health = null;
$attentionList = [];
if ($selectedCs) {
    $studentIds = $db->prepare("SELECT student_id FROM student_enrollments WHERE class_section_id = :cs");
    $studentIds->execute(['cs' => $selectedCs]);
    $studentIds = $studentIds->fetchAll(PDO::FETCH_COLUMN);
    $studentCount = count($studentIds);

    // Average attendance % across the class
    $att = $db->prepare("SELECT status, COUNT(*) c FROM attendance WHERE class_section_id = :cs GROUP BY status");
    $att->execute(['cs' => $selectedCs]);
    $counts = ['present' => 0, 'absent' => 0, 'late' => 0];
    foreach ($att->fetchAll() as $r) { $counts[$r['status']] = (int) $r['c']; }
    $attTotal = array_sum($counts);
    $avgAttendance = $attTotal > 0 ? round(($counts['present'] + $counts['late']) / $attTotal * 100, 1) : null;

    // Average marks %
    $avgMarks = $db->prepare("
        SELECT AVG(m.obtained_marks / es.full_marks * 100) AS avg_pct
        FROM marks m
        JOIN exam_schedule es ON es.id = m.exam_schedule_id
        JOIN exams e ON e.id = es.exam_id
        WHERE es.class_section_id = :cs AND m.is_absent = 0 AND e.status = 'result_published'
    ");
    $avgMarks->execute(['cs' => $selectedCs]);
    $avgMarksPct = $avgMarks->fetchColumn();
    $avgMarksPct = $avgMarksPct !== null ? round((float) $avgMarksPct, 1) : null;

    // Homework completion %
    $hwTotal = $db->prepare("SELECT COUNT(*) FROM homework WHERE class_section_id = :cs");
    $hwTotal->execute(['cs' => $selectedCs]);
    $hwCount = (int) $hwTotal->fetchColumn();
    $hwCompletion = null;
    if ($hwCount > 0 && $studentCount > 0) {
        $possible = $hwCount * $studentCount;
        $submitted = $db->prepare("
            SELECT COUNT(*) FROM homework_submissions hs
            JOIN homework h ON h.id = hs.homework_id
            WHERE h.class_section_id = :cs
        ");
        $submitted->execute(['cs' => $selectedCs]);
        $hwCompletion = round((int) $submitted->fetchColumn() / $possible * 100, 1);
    }

    // Pending fees for this class
    $pendingFees = $db->prepare("
        SELECT COALESCE(SUM(sf.amount - sf.discount + sf.fine - COALESCE(p.paid,0)), 0) AS due
        FROM student_fees sf
        JOIN student_enrollments se ON se.student_id = sf.student_id AND se.class_section_id = :cs
        LEFT JOIN (SELECT student_fee_id, SUM(amount) paid FROM payments GROUP BY student_fee_id) p ON p.student_fee_id = sf.id
    ");
    $pendingFees->execute(['cs' => $selectedCs]);
    $pendingDue = (float) $pendingFees->fetchColumn();

    // Students needing attention + low attendance count
    $lowAttendanceCount = 0;
    foreach ($studentIds as $sid) {
        $flags = student_attention_flags((int) $sid);
        if (!empty($flags)) { $attentionList[] = $sid; }
        foreach ($flags as $f) { if ($f['type'] === 'attendance') { $lowAttendanceCount++; } }
    }

    $health = [
        'student_count' => $studentCount,
        'avg_attendance' => $avgAttendance,
        'avg_marks' => $avgMarksPct,
        'homework_completion' => $hwCompletion,
        'pending_fees' => $pendingDue,
        'needs_attention' => count($attentionList),
        'low_attendance' => $lowAttendanceCount,
    ];
}

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="card mb-3">
    <div class="card-body">
        <form method="get">
            <select class="form-select" name="class_section_id" onchange="this.form.submit()" style="max-width:320px">
                <?php foreach ($classSections as $cs): ?>
                    <option value="<?= (int)$cs['id'] ?>" <?= $selectedCs===(int)$cs['id']?'selected':'' ?>><?= e($cs['class_name'].' - '.$cs['section_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<?php if ($health): ?>
<div class="row g-3">
    <div class="col-md-6 col-lg-3"><div class="stat-card"><div class="stat-icon" style="background:#e8ecfb;color:#2f5fdc"><i class="fa-solid fa-user-group"></i></div><div><div class="stat-value"><?= $health['student_count'] ?></div><div class="stat-label">Students</div></div></div></div>
    <div class="col-md-6 col-lg-3"><div class="stat-card"><div class="stat-icon" style="background:#e6f7ee;color:#1aa260"><i class="fa-solid fa-clipboard-check"></i></div><div><div class="stat-value"><?= $health['avg_attendance'] !== null ? $health['avg_attendance'].'%' : '-' ?></div><div class="stat-label">Avg Attendance</div></div></div></div>
    <div class="col-md-6 col-lg-3"><div class="stat-card"><div class="stat-icon" style="background:#fff2e0;color:#d9822b"><i class="fa-solid fa-file-lines"></i></div><div><div class="stat-value"><?= $health['avg_marks'] !== null ? $health['avg_marks'].'%' : '-' ?></div><div class="stat-label">Avg Result</div></div></div></div>
    <div class="col-md-6 col-lg-3"><div class="stat-card"><div class="stat-icon" style="background:#e8ecfb;color:#2f5fdc"><i class="fa-solid fa-book-open"></i></div><div><div class="stat-value"><?= $health['homework_completion'] !== null ? $health['homework_completion'].'%' : '-' ?></div><div class="stat-label">Homework Completion</div></div></div></div>
</div>
<div class="row g-3 mt-1">
    <div class="col-md-4"><div class="stat-card"><div class="stat-icon" style="background:#fdeaea;color:#d9364a"><i class="fa-solid fa-triangle-exclamation"></i></div><div><div class="stat-value"><?= $health['needs_attention'] ?></div><div class="stat-label">Students Needing Attention</div></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-icon" style="background:#fff2e0;color:#d9822b"><i class="fa-solid fa-user-clock"></i></div><div><div class="stat-value"><?= $health['low_attendance'] ?></div><div class="stat-label">Low Attendance</div></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-icon" style="background:#fdeaea;color:#d9364a"><i class="fa-solid fa-money-bill-wave"></i></div><div><div class="stat-value"><?= format_money($health['pending_fees']) ?></div><div class="stat-label">Pending Fees</div></div></div></div>
</div>
<div class="mt-3">
    <a href="<?= base_url('admin/attention.php?class_section_id='.$selectedCs) ?>" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-triangle-exclamation me-1"></i>View Flagged Students</a>
</div>
<?php else: ?>
<div class="card"><div class="empty-state py-5">No class-sections offered for the active academic year yet.</div></div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
