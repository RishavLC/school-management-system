<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::requireArea('admin');

$pageTitle = 'Add Student';
$activeMenu = 'students';
$db = Database::connection();
$activeYear = $db->query("SELECT * FROM academic_years WHERE is_active=1 LIMIT 1")->fetch();

if (!$activeYear) {
    flash('error', 'Set an active academic year before admitting students.');
    redirect(base_url('admin/academic_years.php'));
}

$classSections = $db->prepare("
    SELECT cs.id, c.name AS class_name, sec.name AS section_name
    FROM class_sections cs JOIN classes c ON c.id=cs.class_id JOIN sections sec ON sec.id=cs.section_id
    WHERE cs.academic_year_id = :y ORDER BY c.sort_order, sec.name
");
$classSections->execute(['y' => $activeYear['id']]);
$classSections = $classSections->fetchAll();

$errors = [];
$old = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf()) {
    $v = new Validator($_POST);
    $v->required('admission_number', 'Admission number')->maxLength('admission_number', 30, 'Admission number')
      ->required('first_name', 'First name')->required('last_name', 'Last name')
      ->required('gender', 'Gender')->in('gender', ['male','female','other'], 'Gender')
      ->date('dob', 'Date of birth', false)
      ->required('class_section_id', 'Class')
      ->required('guardian_first_name', 'Guardian first name')
      ->required('guardian_last_name', 'Guardian last name')
      ->required('guardian_phone', 'Guardian phone')
      ->email('guardian_email', 'Guardian email', false);

    if (!$v->fails()) {
        try {
            $db->beginTransaction();

            // 1. Student record
            $stmt = $db->prepare("
                INSERT INTO students (admission_number, first_name, middle_name, last_name, dob, gender, blood_group, address, phone, email, status)
                VALUES (:adm,:fn,:mn,:ln,:dob,:gd,:bg,:ad,:ph,:em,'active')
            ");
            $stmt->execute([
                'adm' => $_POST['admission_number'], 'fn' => $_POST['first_name'], 'mn' => $_POST['middle_name'] ?: null,
                'ln' => $_POST['last_name'], 'dob' => $_POST['dob'] ?: null, 'gd' => $_POST['gender'],
                'bg' => $_POST['blood_group'] ?: null, 'ad' => $_POST['address'] ?: null,
                'ph' => $_POST['phone'] ?: null, 'em' => $_POST['email'] ?: null,
            ]);
            $studentId = (int) $db->lastInsertId();

            // 2. Guardian — reuse an existing guardian by phone if one already exists, else create
            $g = $db->prepare('SELECT id FROM guardians WHERE phone = :p LIMIT 1');
            $g->execute(['p' => $_POST['guardian_phone']]);
            $guardianId = $g->fetchColumn();

            if (!$guardianId) {
                $db->prepare('INSERT INTO guardians (first_name, last_name, phone, email, address) VALUES (:fn,:ln,:ph,:em,:ad)')
                   ->execute([
                       'fn' => $_POST['guardian_first_name'], 'ln' => $_POST['guardian_last_name'],
                       'ph' => $_POST['guardian_phone'], 'em' => $_POST['guardian_email'] ?: null,
                       'ad' => $_POST['guardian_address'] ?: ($_POST['address'] ?: null),
                   ]);
                $guardianId = $db->lastInsertId();
            }
            $db->prepare('INSERT INTO student_guardians (student_id, guardian_id, relationship, is_primary) VALUES (:s,:g,:r,1)')
               ->execute(['s' => $studentId, 'g' => $guardianId, 'r' => $_POST['guardian_relationship'] ?: 'Guardian']);

            // 3. Enrollment for the active academic year — this is what "assigns class & section"
            $db->prepare("
                INSERT INTO student_enrollments (student_id, academic_year_id, class_section_id, roll_number, admission_date, status)
                VALUES (:s,:y,:cs,:r,:ad,'active')
            ")->execute([
                's' => $studentId, 'y' => $activeYear['id'], 'cs' => (int)$_POST['class_section_id'],
                'r' => $_POST['roll_number'] ?: null, 'ad' => date('Y-m-d'),
            ]);

            $db->commit();
            Auth::log(Auth::id(), 'Admitted student ' . $_POST['first_name'] . ' ' . $_POST['last_name'] . ' (' . $_POST['admission_number'] . ')');
            flash('success', 'Student admitted and enrolled successfully.');
            redirect(base_url('admin/students.php'));
        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = str_contains($e->getMessage(), 'admission_number')
                ? 'That admission number is already used by another student.'
                : 'Could not save the student. Please check the details and try again.';
        }
    } else {
        $errors = array_values($v->errors());
    }
}

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="row justify-content-center">
  <div class="col-lg-9">
    <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger py-2"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post">
      <?= Auth::csrfField() ?>

      <div class="card mb-3">
        <div class="card-header">1. Personal Information</div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label">Admission Number</label>
                <input class="form-control" name="admission_number" required value="<?= e($old['admission_number'] ?? '') ?>" placeholder="LIL-2026-008">
            </div>
            <div class="col-md-4">
                <label class="form-label">First Name</label>
                <input class="form-control" name="first_name" required value="<?= e($old['first_name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Middle Name</label>
                <input class="form-control" name="middle_name" value="<?= e($old['middle_name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Last Name</label>
                <input class="form-control" name="last_name" required value="<?= e($old['last_name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Date of Birth</label>
                <input class="form-control" type="date" name="dob" value="<?= e($old['dob'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Gender</label>
                <select class="form-select" name="gender" required>
                    <option value="">Select...</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Blood Group</label>
                <input class="form-control" name="blood_group" placeholder="e.g. B+" value="<?= e($old['blood_group'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Phone</label>
                <input class="form-control" name="phone" value="<?= e($old['phone'] ?? '') ?>">
            </div>
            <div class="col-md-8">
                <label class="form-label">Address</label>
                <input class="form-control" name="address" value="<?= e($old['address'] ?? '') ?>">
            </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">2. Parent / Guardian Information</div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label">Guardian First Name</label>
                <input class="form-control" name="guardian_first_name" required value="<?= e($old['guardian_first_name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Guardian Last Name</label>
                <input class="form-control" name="guardian_last_name" required value="<?= e($old['guardian_last_name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Relationship</label>
                <select class="form-select" name="guardian_relationship">
                    <option>Mother</option><option>Father</option><option>Guardian</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Guardian Phone</label>
                <input class="form-control" name="guardian_phone" required value="<?= e($old['guardian_phone'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Guardian Email</label>
                <input class="form-control" type="email" name="guardian_email" value="<?= e($old['guardian_email'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Guardian Address</label>
                <input class="form-control" name="guardian_address" placeholder="Same as student if left blank">
            </div>
            <div class="col-12 text-muted small">If a guardian with this phone number already exists, the student will be linked to that guardian instead of creating a duplicate.</div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">3. Academic Information — <?= e($activeYear['name']) ?></div>
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">Class - Section</label>
                <select class="form-select" name="class_section_id" required>
                    <option value="">Select...</option>
                    <?php foreach ($classSections as $cs): ?>
                        <option value="<?= (int)$cs['id'] ?>"><?= e($cs['class_name'] . ' - ' . $cs['section_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($classSections)): ?>
                    <div class="form-text text-danger">No class-sections offered yet for this year — <a href="<?= base_url('admin/classes.php') ?>">offer one first</a>.</div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">Roll Number</label>
                <input class="form-control" name="roll_number" placeholder="Optional">
            </div>
        </div>
      </div>

      <button class="btn btn-primary px-4" <?= empty($classSections) ? 'disabled' : '' ?>><i class="fa-solid fa-user-plus me-1"></i> Admit Student</button>
      <a href="<?= base_url('admin/students.php') ?>" class="btn btn-outline-secondary px-4">Cancel</a>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
