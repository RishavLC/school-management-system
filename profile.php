<?php
require_once __DIR__ . '/core/bootstrap.php';
if (!Auth::check()) redirect(base_url('login.php'));

$pageTitle = 'My Profile';
$activeMenu = 'profile';
$db = Database::connection();
$role = Auth::role();

$user = $db->prepare('SELECT * FROM users WHERE id = :id');
$user->execute(['id' => Auth::id()]);
$user = $user->fetch();

$profile = null;
if (in_array($role, ['teacher'], true)) {
    $stmt = $db->prepare('SELECT * FROM teachers WHERE user_id = :id');
    $stmt->execute(['id' => Auth::id()]);
    $profile = $stmt->fetch();
} elseif ($role === 'student') {
    $stmt = $db->prepare('SELECT * FROM students WHERE user_id = :id');
    $stmt->execute(['id' => Auth::id()]);
    $profile = $stmt->fetch();
} elseif ($role === 'parent') {
    $stmt = $db->prepare('SELECT * FROM guardians WHERE user_id = :id');
    $stmt->execute(['id' => Auth::id()]);
    $profile = $stmt->fetch();
}

$studentGuardians = [];
$studentEnrollment = null;
if ($role === 'student' && $profile) {
    $g = $db->prepare("
        SELECT g.*, sg.relationship FROM student_guardians sg
        JOIN guardians g ON g.id = sg.guardian_id WHERE sg.student_id = :id
    ");
    $g->execute(['id' => $profile['id']]);
    $studentGuardians = $g->fetchAll();

    $en = $db->prepare("
        SELECT se.*, c.name AS class_name, sec.name AS section_name, ay.name AS year_name
        FROM student_enrollments se
        JOIN class_sections cs ON cs.id = se.class_section_id
        JOIN classes c ON c.id = cs.class_id
        JOIN sections sec ON sec.id = cs.section_id
        JOIN academic_years ay ON ay.id = se.academic_year_id
        WHERE se.student_id = :id AND ay.is_active = 1 LIMIT 1
    ");
    $en->execute(['id' => $profile['id']]);
    $studentEnrollment = $en->fetch() ?: null;
}

include __DIR__ . '/includes/layout_start.php';
?>
<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">Account Information</div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr><th style="width:180px">Username</th><td><?= e($user['username']) ?></td></tr>
                    <tr><th>Email</th><td><?= e($user['email'] ?? '-') ?></td></tr>
                    <tr><th>Role</th><td><?= e(role_label($user['role'])) ?></td></tr>
                    <tr><th>Status</th><td><?= status_badge($user['status']) ?></td></tr>
                    <tr><th>Last Login</th><td><?= $user['last_login_at'] ? e($user['last_login_at']) : 'This is your first login' ?></td></tr>
                </table>
            </div>
        </div>

        <?php if ($profile): ?>
        <div class="card mt-3">
            <div class="card-header">Profile Details</div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <?php if ($role === 'teacher'): ?>
                        <tr><th style="width:180px">Employee ID</th><td><?= e($profile['employee_id']) ?></td></tr>
                        <tr><th>Full Name</th><td><?= e($profile['first_name'] . ' ' . $profile['last_name']) ?></td></tr>
                        <tr><th>Qualification</th><td><?= e($profile['qualification'] ?? '-') ?></td></tr>
                        <tr><th>Phone</th><td><?= e($profile['phone'] ?? '-') ?></td></tr>
                        <tr><th>Joining Date</th><td><?= format_date($profile['joining_date']) ?></td></tr>
                    <?php elseif ($role === 'student'): ?>
                        <tr><th style="width:180px">Admission Number</th><td><?= e($profile['admission_number']) ?></td></tr>
                        <tr><th>Full Name</th><td><?= e($profile['first_name'] . ' ' . ($profile['middle_name'] ? $profile['middle_name'] . ' ' : '') . $profile['last_name']) ?></td></tr>
                        <tr><th>Date of Birth</th><td><?= format_date($profile['dob']) ?></td></tr>
                        <tr><th>Gender</th><td><?= e(ucfirst($profile['gender'])) ?></td></tr>
                        <tr><th>Blood Group</th><td><?= e($profile['blood_group'] ?? '-') ?></td></tr>
                        <tr><th>Class &amp; Section</th><td><?= $studentEnrollment ? e($studentEnrollment['class_name'] . ' - ' . $studentEnrollment['section_name'] . ' (Roll ' . ($studentEnrollment['roll_number'] ?? '-') . ')') : 'Not enrolled yet' ?></td></tr>
                        <tr><th>Academic Year</th><td><?= $studentEnrollment ? e($studentEnrollment['year_name']) : '-' ?></td></tr>
                        <tr><th>Admission Date</th><td><?= $studentEnrollment ? format_date($studentEnrollment['admission_date']) : '-' ?></td></tr>
                        <tr><th>Phone</th><td><?= e($profile['phone'] ?? '-') ?></td></tr>
                        <tr><th>Email</th><td><?= e($profile['email'] ?? '-') ?></td></tr>
                        <tr><th>Address</th><td><?= e($profile['address'] ?? '-') ?></td></tr>
                        <tr><th>Emergency Contact</th><td><?= $profile['emergency_contact_name'] ? e($profile['emergency_contact_name'] . ' (' . $profile['emergency_contact_relation'] . ') — ' . $profile['emergency_contact_phone']) : '-' ?></td></tr>
                    <?php elseif ($role === 'parent'): ?>
                        <tr><th style="width:180px">Full Name</th><td><?= e($profile['first_name'] . ' ' . $profile['last_name']) ?></td></tr>
                        <tr><th>Phone</th><td><?= e($profile['phone'] ?? '-') ?></td></tr>
                        <tr><th>Address</th><td><?= e($profile['address'] ?? '-') ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($role === 'student'): ?>
        <div class="card mt-3">
            <div class="card-header">Parent / Guardian Information</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($studentGuardians as $g): ?>
                    <li class="list-group-item">
                        <div class="fw-semibold"><?= e($g['first_name'] . ' ' . $g['last_name']) ?> <span class="text-muted small">(<?= e($g['relationship']) ?>)</span></div>
                        <div class="small text-muted"><i class="fa-solid fa-phone me-1"></i><?= e($g['phone'] ?? '-') ?> &nbsp; <i class="fa-solid fa-envelope me-1"></i><?= e($g['email'] ?? '-') ?></div>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($studentGuardians)): ?><li class="list-group-item empty-state">No guardian on record.</li><?php endif; ?>
            </ul>
        </div>

        <div class="alert alert-secondary mt-3 mb-0">
            <i class="fa-solid fa-circle-info me-1"></i>
            Your name, date of birth, class, and admission details cannot be edited here to keep school records accurate.
            If any of this information is incorrect, please contact the school office to request a change.
        </div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/includes/layout_end.php'; ?>
