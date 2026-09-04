<?php
/**
 * Shared helper functions. Kept framework-free and dependency-free on purpose
 * so the whole app stays "Core PHP".
 */

function e(?string $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function base_url(string $path = ''): string
{
    static $cfg = null;
    if ($cfg === null) $cfg = require __DIR__ . '/../config/app.php';
    return rtrim($cfg['base_url'], '/') . '/' . ltrim($path, '/');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $key, ?string $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    if (!empty($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

function format_date(?string $date, string $fmt = 'd M Y'): string
{
    if (!$date) return '-';
    $d = DateTime::createFromFormat('Y-m-d', substr($date, 0, 10));
    return $d ? $d->format($fmt) : $date;
}

function format_money(float $amount): string
{
    return 'Rs. ' . number_format($amount, 2);
}

/** Human label for role codes */
function role_label(string $role): string
{
    $labels = [
        'super_admin' => 'Super Admin',
        'admin'       => 'School Admin',
        'principal'   => 'Principal',
        'teacher'     => 'Teacher',
        'student'     => 'Student',
        'parent'      => 'Parent',
        'accountant'  => 'Accountant',
    ];
    return $labels[$role] ?? ucfirst($role);
}

/**
 * Loads the students row + current-year enrollment (class/section) for the
 * logged-in student. Used at the top of every page under /student/.
 * Returns null if no student profile is linked to this account.
 */
function current_student(): ?array
{
    $db = Database::connection();
    $stmt = $db->prepare('SELECT * FROM students WHERE user_id = :u');
    $stmt->execute(['u' => Auth::id()]);
    $student = $stmt->fetch();
    if (!$student) return null;

    $enr = $db->prepare("
        SELECT se.*, cs.id AS class_section_id, c.name AS class_name, sec.name AS section_name,
               ay.name AS year_name, ay.id AS academic_year_id
        FROM student_enrollments se
        JOIN class_sections cs ON cs.id = se.class_section_id
        JOIN classes c ON c.id = cs.class_id
        JOIN sections sec ON sec.id = cs.section_id
        JOIN academic_years ay ON ay.id = se.academic_year_id
        WHERE se.student_id = :s AND ay.is_active = 1
        LIMIT 1
    ");
    $enr->execute(['s' => $student['id']]);
    $student['enrollment'] = $enr->fetch() ?: null;
    return $student;
}

/**
 * Guard used at the top of every /student/*.php page: enforces the
 * student area role AND that a student profile is actually linked.
 * Renders a friendly empty state and stops execution if not.
 */
function require_student_profile(): array
{
    Auth::requireArea('student');
    $student = current_student();
    if (!$student) {
        $pageTitle = 'Student Portal';
        include __DIR__ . '/../includes/layout_start.php';
        echo '<div class="empty-state"><i class="fa-solid fa-user-slash"></i><br>No student profile is linked to your account. Contact the school admin.</div>';
        include __DIR__ . '/../includes/layout_end.php';
        exit;
    }
    return $student;
}

/** Bootstrap badge color per status word, used across the app for consistency */
function status_badge(string $status): string
{
    $map = [
        'active' => 'success', 'present' => 'success', 'paid' => 'success', 'pass' => 'success',
        'inactive' => 'secondary', 'left' => 'secondary',
        'absent' => 'danger', 'unpaid' => 'danger', 'fail' => 'danger',
        'late' => 'warning', 'partial' => 'warning', 'pending' => 'warning',
        'transferred' => 'info', 'graduated' => 'primary',
        'submitted' => 'info', 'checked' => 'primary',
        'approved' => 'success', 'rejected' => 'danger',
        'upcoming' => 'info', 'ongoing' => 'warning', 'completed' => 'secondary', 'result_published' => 'success',
    ];
    $color = $map[$status] ?? 'secondary';
    return '<span class="badge text-bg-' . $color . '">' . e(ucfirst($status)) . '</span>';
}
