<div class="row g-3 mb-4">

  <div class="col-sm-6 col-xl-3">
    <div class="card fm-admin-stat-card shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle bg-primary bg-opacity-10 p-3">
          <i class="fa-solid fa-folder-open fa-lg text-primary"></i>
        </div>
        <div>
          <div class="fs-4 fw-bold"><?= number_format($stats['total_files']) ?></div>
          <div class="text-muted small"><?= e(t('files.admin.stat_total')) ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card fm-admin-stat-card shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle bg-info bg-opacity-10 p-3">
          <i class="fa-solid fa-hard-drive fa-lg text-info"></i>
        </div>
        <div>
          <div class="fs-4 fw-bold"><?= e($stats['total_size_hr']) ?></div>
          <div class="text-muted small"><?= e(t('files.admin.stat_space')) ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card fm-admin-stat-card shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle bg-success bg-opacity-10 p-3">
          <i class="fa-solid fa-file-image fa-lg text-success"></i>
        </div>
        <div>
          <div class="fs-4 fw-bold"><?= number_format($stats['by_group']['image'] ?? 0) ?></div>
          <div class="text-muted small"><?= e(t('files.admin.stat_images')) ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card fm-admin-stat-card shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle bg-warning bg-opacity-10 p-3">
          <i class="fa-solid fa-file-lines fa-lg text-warning"></i>
        </div>
        <div>
          <div class="fs-4 fw-bold">
            <?= number_format(($stats['by_group']['document'] ?? 0) + ($stats['by_group']['text'] ?? 0)) ?>
          </div>
          <div class="text-muted small"><?= e(t('files.admin.stat_documents')) ?></div>
        </div>
      </div>
    </div>
  </div>

</div>
