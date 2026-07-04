<?php
/**
 * Oggi feed — sezione collassabile "Completate oggi".
 * Variables: $completedToday (array)
 */
$completedToday = is_array($completedToday ?? null) ? $completedToday : [];
$n = count($completedToday);
?>
<div class="hm-today-completed border-top">
    <button class="btn w-100 d-flex justify-content-between align-items-center text-decoration-none hm-today-completed-toggle"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#hm-today-completed-list"
            aria-expanded="false"
            aria-controls="hm-today-completed-list">
        <span class="text-body">
            <i class="fa-regular fa-circle-check me-1 text-success"></i>
            <?= e(t('home.completed.title')) ?>
            <?php if ($n > 0): ?>
                <span class="badge bg-success ms-1"><?= (int) $n ?></span>
            <?php endif; ?>
        </span>
        <i class="fa-solid fa-chevron-down hm-today-completed-chevron"></i>
    </button>

    <div id="hm-today-completed-list" class="collapse">
        <div class="px-3 pb-3">
            <?php if ($n === 0): ?>
                <div class="text-body-secondary small py-2">
                    <?= e(t('home.completed.empty')) ?>
                </div>
            <?php else: ?>
                <ul class="list-unstyled mb-0 hm-today-completed-items">
                    <?php foreach ($completedToday as $row): ?>
                        <?php
                        $completedTs = strtotime((string) ($row['completed_at'] ?? '')) ?: time();
                        ?>
                        <li class="d-flex justify-content-between align-items-center py-1 small gap-2">
                            <span class="text-decoration-line-through text-body-secondary text-truncate">
                                <?= e((string) ($row['title'] ?? '')) ?>
                            </span>
                            <span class="text-body-secondary flex-shrink-0">
                                <?= e(date('H:i', $completedTs)) ?>
                                <a href="<?= e((string) ($row['link'] ?? '#')) ?>"
                                   class="ms-1 text-decoration-none"
                                   title="<?= e(t('home.completed.open_detail')) ?>"
                                   data-bs-toggle="tooltip">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                </a>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
