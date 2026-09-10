<?php
/**
 * Reusable "My Children" selector strip for parent pages.
 * Expects $children (array) and $child (currently selected, or null) in scope.
 */
if (count($children) > 1): ?>
<div class="card mb-3">
    <div class="card-body">
        <div class="text-muted small mb-2">My Children</div>
        <div class="d-flex gap-2 flex-wrap">
            <?php foreach ($children as $c): ?>
                <a href="?student_id=<?= (int)$c['id'] ?>" class="btn btn-sm <?= $c['id'] === ($child['id'] ?? null) ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= e($c['first_name'] . ' ' . $c['last_name']) ?>
                    <?php if ($c['class_name']): ?><span class="opacity-75">(<?= e($c['class_name'] . '-' . $c['section_name']) ?>)</span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif;
