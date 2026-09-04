<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireArea('admin');

$pageTitle = 'Reports';
$activeMenu = 'reports';
$db = Database::connection();
$activeYear = $db->query("SELECT * FROM academic_years WHERE is_active=1 LIMIT 1")->fetch();
$report = $_GET['report'] ?? 'students';

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

$rows = [];
$columns = [];

if ($report === 'students') {
    $columns = ['Admission #','Name','Class','Roll #','Status'];
    $stmt = $db->prepare("
        SELECT s.admission_number, CONCAT(s.first_name,' ',s.last_name) name,
               CONCAT(c.name,' - ',sec.name) class, se.roll_number, s.status
        FROM students s
        LEFT JOIN student_enrollments se ON se.student_id=s.id AND se.academic_year_id=:y
        LEFT JOIN class_sections cs ON cs.id = se.class_section_id
        LEFT JOIN classes c ON c.id = cs.class_id
        LEFT JOIN sections sec ON sec.id = cs.section_id
        ORDER BY s.first_name
    ");
    $stmt->execute(['y' => $activeYear['id'] ?? 0]);
    $rows = $stmt->fetchAll();
} elseif ($report === 'fees_outstanding') {
    $columns = ['Student','Fee Type','Amount','Paid','Outstanding','Due Date'];
    $rows = $db->query("
        SELECT CONCAT(s.first_name,' ',s.last_name) student, ft.name fee_type,
               (sf.amount-sf.discount+sf.fine) amount,
               (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.student_fee_id=sf.id) paid,
               (sf.amount-sf.discount+sf.fine) - (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.student_fee_id=sf.id) outstanding,
               sf.due_date due
        FROM student_fees sf
        JOIN students s ON s.id = sf.student_id
        JOIN fee_types ft ON ft.id = sf.fee_type_id
        HAVING outstanding > 0
        ORDER BY due ASC
    ")->fetchAll();
} elseif ($report === 'fees_collection') {
    $columns = ['Receipt #','Student','Amount','Date','Method','Received By'];
    $rows = $db->query("
        SELECT p.receipt_number, CONCAT(s.first_name,' ',s.last_name) student, p.amount, p.payment_date, p.payment_method, u.username received_by
        FROM payments p JOIN students s ON s.id=p.student_id JOIN users u ON u.id = p.received_by
        ORDER BY p.payment_date DESC LIMIT 100
    ")->fetchAll();
} elseif ($report === 'exam_results') {
    $columns = ['Exam','Student','Subject','Marks','Grade','Result'];
    $rows = $db->query("
        SELECT e.name exam, CONCAT(s.first_name,' ',s.last_name) student, subj.name subject,
               CONCAT(m.obtained_marks,' / ',es.full_marks) marks, m.grade,
               IF(m.obtained_marks >= es.pass_marks,'Pass','Fail') result
        FROM marks m
        JOIN exam_schedule es ON es.id = m.exam_schedule_id
        JOIN exams e ON e.id = es.exam_id
        JOIN students s ON s.id = m.student_id
        JOIN subjects subj ON subj.id = es.subject_id
        ORDER BY e.start_date DESC, s.first_name LIMIT 200
    ")->fetchAll();
} elseif ($report === 'attendance_summary') {
    $columns = ['Student','Class','Present','Absent','Late','Attendance %'];
    $rows = $db->query("
        SELECT CONCAT(s.first_name,' ',s.last_name) student, CONCAT(c.name,' - ',sec.name) class,
               SUM(a.status='present') present, SUM(a.status='absent') absent, SUM(a.status='late') late,
               ROUND(SUM(a.status IN ('present','late'))/COUNT(*)*100,1) pct
        FROM attendance a
        JOIN students s ON s.id = a.student_id
        JOIN class_sections cs ON cs.id = a.class_section_id
        JOIN classes c ON c.id = cs.class_id
        JOIN sections sec ON sec.id = cs.section_id
        GROUP BY s.id ORDER BY s.first_name
    ")->fetchAll();
}

include __DIR__ . '/../includes/layout_start.php';
$reportList = [
    'students' => 'Student List',
    'attendance_summary' => 'Attendance Summary',
    'fees_outstanding' => 'Outstanding Fees',
    'fees_collection' => 'Fee Collection History',
    'exam_results' => 'Examination Results',
];
?>
<div class="row g-3">
    <div class="col-lg-3">
        <div class="card">
            <div class="card-header">Report Type</div>
            <div class="list-group list-group-flush">
                <?php foreach ($reportList as $key => $label): ?>
                    <a href="?report=<?= $key ?>" class="list-group-item list-group-item-action <?= $report===$key?'active':'' ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <?= e($reportList[$report] ?? 'Report') ?>
                <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-print me-1"></i>Print</button>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><?php foreach ($columns as $c): ?><th><?= e($c) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr><?php foreach ($r as $k => $v): if (is_int($k)) continue; ?><td><?= e((string)$v) ?></td><?php endforeach; ?></tr>
                    <?php endforeach; ?>
                    <?php if (empty($rows)): ?><tr><td colspan="<?= count($columns) ?>" class="empty-state">No data for this report yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
