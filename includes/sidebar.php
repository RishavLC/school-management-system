<?php
/**
 * Sidebar navigation, rendered differently per role.
 * $activeMenu should be set by the including page (e.g. 'dashboard', 'students').
 */
$role = Auth::role();
$activeMenu = $activeMenu ?? '';

function nav_item(string $key, string $active, string $url, string $icon, string $label): void
{
    $isActive = $key === $active ? 'active' : '';
    echo '<li class="nav-item">';
    echo '<a class="nav-link ' . $isActive . '" href="' . e($url) . '"><i class="fa-solid ' . $icon . '"></i><span>' . e($label) . '</span></a>';
    echo '</li>';
}
?>
<nav class="sidebar">
    <div class="sidebar-brand">
        <i class="fa-solid fa-graduation-cap"></i>
        <span>Lilliput School</span>
    </div>
    <ul class="nav flex-column sidebar-nav">
    <?php if (in_array($role, ['super_admin','admin','principal'], true)): ?>
        <?php nav_item('dashboard', $activeMenu, base_url('admin/dashboard.php'), 'fa-gauge', 'Dashboard'); ?>
        <li class="nav-section">Academics</li>
        <?php nav_item('academic_years', $activeMenu, base_url('admin/academic_years.php'), 'fa-calendar', 'Academic Years'); ?>
        <?php nav_item('classes', $activeMenu, base_url('admin/classes.php'), 'fa-layer-group', 'Classes & Sections'); ?>
        <?php nav_item('subjects', $activeMenu, base_url('admin/subjects.php'), 'fa-book', 'Subjects'); ?>
        <?php nav_item('assignments', $activeMenu, base_url('admin/assignments.php'), 'fa-chalkboard-user', 'Teacher Assignments'); ?>
        <li class="nav-section">People</li>
        <?php nav_item('students', $activeMenu, base_url('admin/students.php'), 'fa-user-graduate', 'Students'); ?>
        <?php nav_item('teachers', $activeMenu, base_url('admin/teachers.php'), 'fa-chalkboard-teacher', 'Teachers'); ?>
        <li class="nav-section">School Operations</li>
        <?php nav_item('attendance', $activeMenu, base_url('admin/attendance_reports.php'), 'fa-clipboard-check', 'Attendance Reports'); ?>
        <?php nav_item('exams', $activeMenu, base_url('admin/exams.php'), 'fa-file-lines', 'Examinations'); ?>
        <?php nav_item('fees', $activeMenu, base_url('admin/fees.php'), 'fa-money-bill', 'Fees'); ?>
        <?php nav_item('notices', $activeMenu, base_url('admin/notices.php'), 'fa-bullhorn', 'Notices'); ?>
        <?php nav_item('reports', $activeMenu, base_url('admin/reports.php'), 'fa-chart-column', 'Reports'); ?>
    <?php elseif ($role === 'teacher'): ?>
        <?php nav_item('dashboard', $activeMenu, base_url('teacher/dashboard.php'), 'fa-gauge', 'Dashboard'); ?>
        <?php nav_item('attendance', $activeMenu, base_url('teacher/attendance.php'), 'fa-clipboard-check', 'Take Attendance'); ?>
        <?php nav_item('marks', $activeMenu, base_url('teacher/marks.php'), 'fa-pen-to-square', 'Enter Marks'); ?>
        <?php nav_item('homework', $activeMenu, base_url('teacher/homework.php'), 'fa-book-open', 'Homework'); ?>
        <?php nav_item('timetable', $activeMenu, base_url('teacher/timetable.php'), 'fa-calendar-days', 'My Timetable'); ?>
        <?php nav_item('notices', $activeMenu, base_url('teacher/notices.php'), 'fa-bullhorn', 'Notices'); ?>
    <?php elseif ($role === 'student'): ?>
        <?php nav_item('dashboard', $activeMenu, base_url('student/dashboard.php'), 'fa-gauge', 'Dashboard'); ?>
        <?php nav_item('timetable', $activeMenu, base_url('student/timetable.php'), 'fa-calendar-days', 'Timetable'); ?>
        <?php nav_item('attendance', $activeMenu, base_url('student/attendance.php'), 'fa-clipboard-check', 'Attendance'); ?>
        <?php nav_item('subjects', $activeMenu, base_url('student/subjects.php'), 'fa-book', 'My Subjects'); ?>
        <?php nav_item('homework', $activeMenu, base_url('student/homework.php'), 'fa-book-open', 'Homework'); ?>
        <?php nav_item('exams', $activeMenu, base_url('student/exams.php'), 'fa-file-pen', 'Exams'); ?>
        <?php nav_item('results', $activeMenu, base_url('student/results.php'), 'fa-trophy', 'Results'); ?>
        <?php nav_item('performance', $activeMenu, base_url('student/performance.php'), 'fa-chart-line', 'My Performance'); ?>
        <?php nav_item('fees', $activeMenu, base_url('student/fees.php'), 'fa-money-bill', 'Fees'); ?>
        <?php nav_item('notices', $activeMenu, base_url('student/notices.php'), 'fa-bullhorn', 'Notices'); ?>
        <?php nav_item('events', $activeMenu, base_url('student/events.php'), 'fa-star', 'School Events'); ?>
        <?php nav_item('notifications', $activeMenu, base_url('student/notifications.php'), 'fa-bell', 'Notifications'); ?>
        <?php nav_item('materials', $activeMenu, base_url('student/materials.php'), 'fa-book-open-reader', 'Learning Materials'); ?>
        <?php nav_item('leave', $activeMenu, base_url('student/leave.php'), 'fa-person-walking-arrow-right', 'Leave Request'); ?>
    <?php elseif ($role === 'parent'): ?>
        <?php nav_item('dashboard', $activeMenu, base_url('parent/dashboard.php'), 'fa-gauge', 'Dashboard'); ?>
        <?php nav_item('attendance', $activeMenu, base_url('parent/attendance.php'), 'fa-clipboard-check', 'Attendance'); ?>
        <?php nav_item('results', $activeMenu, base_url('parent/results.php'), 'fa-file-lines', 'Results'); ?>
        <?php nav_item('fees', $activeMenu, base_url('parent/fees.php'), 'fa-money-bill', 'Fees & Payments'); ?>
        <?php nav_item('homework', $activeMenu, base_url('parent/homework.php'), 'fa-book-open', 'Homework'); ?>
        <?php nav_item('timetable', $activeMenu, base_url('parent/timetable.php'), 'fa-calendar-days', 'Timetable'); ?>
        <?php nav_item('notices', $activeMenu, base_url('parent/notices.php'), 'fa-bullhorn', 'Notices'); ?>
    <?php elseif ($role === 'accountant'): ?>
        <?php nav_item('dashboard', $activeMenu, base_url('accountant/dashboard.php'), 'fa-gauge', 'Dashboard'); ?>
        <?php nav_item('fees', $activeMenu, base_url('accountant/fees.php'), 'fa-money-bill', 'Student Fees'); ?>
        <?php nav_item('payments', $activeMenu, base_url('accountant/payments.php'), 'fa-receipt', 'Payments'); ?>
    <?php endif; ?>
        <li class="nav-section">Account</li>
        <?php nav_item('profile', $activeMenu, base_url('profile.php'), 'fa-user', 'My Profile'); ?>
        <?php nav_item('change_password', $activeMenu, base_url('change_password.php'), 'fa-key', 'Change Password'); ?>
        <li class="nav-item">
            <a class="nav-link" href="<?= base_url('logout.php') ?>"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
        </li>
    </ul>
</nav>
