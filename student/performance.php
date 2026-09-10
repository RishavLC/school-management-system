<?php
require_once __DIR__ . '/../core/bootstrap.php';
$student = require_student_profile();

$pageTitle = 'My Performance';
$activeMenu = 'performance';

$perf = compute_student_performance((int) $student['id']);

function perf_bar_color(?float $pct): string
{
    if ($pct === null) return 'secondary';
    if ($pct >= 80) return 'success';
    if ($pct >= 60) return 'primary';
    if ($pct >= 40) return 'warning';
    return 'danger';
}

function trend_icon(string $trend): string
{
    return match ($trend) {
        'up' => '<i class="fa-solid fa-arrow-up text-success"></i>',
        'down' => '<i class="fa-solid fa-arrow-down text-danger"></i>',
        default => '<i class="fa-solid fa-arrow-right text-muted"></i>',
    };
}

include __DIR__ . '/../includes/layout_start.php';
?>
<div class="row g-3 mb-1">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e6f7ee;color:#1aa260"><i class="fa-solid fa-clipboard-check"></i></div>
            <div><div class="stat-value"><?= $perf['attendance_pct'] !== null ? $perf['attendance_pct'].'%' : '-' ?></div><div class="stat-label">Attendance</div></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e8ecfb;color:#2f5fdc"><i class="fa-solid fa-trophy"></i></div>
            <div><div class="stat-value"><?= $perf['avg_result_pct'] !== null ? $perf['avg_result_pct'].'%' : '-' ?></div><div class="stat-label">Average Result</div></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff2e0;color:#d9822b"><i class="fa-solid fa-book-open"></i></div>
            <div><div class="stat-value"><?= $perf['homework_pct'] !== null ? $perf['homework_pct'].'%' : '-' ?></div><div class="stat-label">Homework Completion</div></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Subject-wise Performance</div>
    <?php if (empty($perf['subjects'])): ?>
        <div class="empty-state py-5"><i class="fa-solid fa-chart-line"></i><p class="mb-0">No published exam results yet — this fills in automatically once results are published.</p></div>
    <?php else: ?>
        <div class="list-group list-group-flush">
            <?php foreach ($perf['subjects'] as $s): ?>
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-semibold"><?= e($s['subject']) ?></span>
                        <span>
                            <?= e((string) $s['latest']) ?>%
                            <?= trend_icon($s['trend']) ?>
                            <?php if ($s['previous'] !== null): ?>
                                <small class="text-muted">(prev <?= e((string) $s['previous']) ?>%)</small>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="progress" style="height:8px">
                        <div class="progress-bar bg-<?= perf_bar_color($s['latest']) ?>" style="width: <?= min(100, max(0, $s['latest'])) ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<p class="text-muted small mt-3">
    Attendance and homework completion are calculated from the current academic year.
    Average result and subject trends are based on all published examinations, comparing your most recent exam
    to the one before it for each subject.
</p>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
