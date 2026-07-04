<span class="tm-sidebar-badge badge rounded-pill bg-danger <?= $count > 0 ? '' : 'd-none' ?>"
      id="tm-unread-badge"
      hx-get="<?= e(route('teams.unread-count')) ?>"
      hx-trigger="every 60s"
      hx-target="#tm-unread-badge"
      hx-swap="outerHTML"><?= $count > 99 ? '99+' : (int) $count ?></span>
