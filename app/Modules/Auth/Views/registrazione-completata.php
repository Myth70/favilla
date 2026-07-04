<?php
/**
 * Registration completion page — uses auth layout.
 * Variables: $view, $layout, $authPage
 */
$view->layout($layout ?? 'auth');
$view->start('content');
?>

<div class="auth-card">

    <!-- Brand / success icon -->
    <div class="auth-brand">
        <?php $view->include('Auth/Views/partials/logo') ?>
        <p class="auth-brand-name"><?= e(t('auth.reg_completed.title')) ?></p>
        <p class="auth-brand-sub"><?= e(t('auth.reg_completed.subtitle')) ?></p>
    </div>

    <hr class="auth-divider">

    <div class="auth-pending-body">
        <p>
            <?= e(t('auth.reg_completed.body_received')) ?><br>
            <?= e(t('auth.reg_completed.body_review')) ?>
        </p>
        <p>
            <?= e(t('auth.reg_completed.body_notify')) ?>
        </p>

        <div class="auth-req-box p-3 mt-3">
            <p class="auth-req-title mb-2"><?= e(t('auth.reg_completed.what_next')) ?></p>
            <ul class="list-unstyled auth-req-list mb-0">
                <li class="auth-req-item mb-1">
                    <i class="fa-solid fa-check auth-step-icon me-2"></i>
                    <?= e(t('auth.reg_completed.step_registered')) ?>
                </li>
                <li class="auth-req-item mb-1">
                    <i class="fa-solid fa-hourglass-half auth-step-icon-pending me-2"></i>
                    <?= e(t('auth.reg_completed.step_review')) ?>
                </li>
                <li class="auth-req-item">
                    <i class="fa-solid fa-lock-open auth-step-icon-locked me-2"></i>
                    <?= e(t('auth.reg_completed.step_activation')) ?>
                </li>
            </ul>
        </div>
    </div>

    <div class="mt-4">
        <a href="<?= e(route('login')) ?>" class="btn btn-auth-outline w-100">
            <i class="fa-solid fa-arrow-left me-2"></i><?= e(t('auth.reg_completed.back_login')) ?>
        </a>
    </div>

</div>

<p class="text-center mt-4 auth-footer-meta">
    <?= e(config('app.name', 'Favilla')) ?> &copy; <?= date('Y') ?>
</p>

<?php $view->end(); ?>
