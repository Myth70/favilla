<?php

/**
 * Auth module routes.
 * $router is an instance of App\Core\Router.
 */

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Auth\Controllers\AvatarCropController;
use App\Modules\Auth\Controllers\ForgotPasswordController;
use App\Modules\Auth\Controllers\LocaleController;
use App\Modules\Auth\Controllers\RegistrazioneController;
use App\Modules\Auth\Controllers\ResetPasswordController;
use App\Modules\Auth\Controllers\TotpController;

// --- Public routes (CSRF on POST) ---
$router->group(['middleware' => [CsrfMiddleware::class]], function ($router) {

    $router->get('/login', [AuthController::class, 'showLogin'])->name('login');
    $router->post('/login', [AuthController::class, 'login'])->name('login.post');
    $router->post('/logout', [AuthController::class, 'logout'])->name('logout');

    // i18n: public language switch (anonymous + authenticated).
    $router->get('/lang/{code}', [LocaleController::class, 'switch'])->name('lang.switch');

    // Registration
    $router->get('/registrazione', [RegistrazioneController::class, 'showForm'])->name('registrazione');
    $router->post('/registrazione', [RegistrazioneController::class, 'register'])->name('registrazione.post');
    $router->get('/registrazione/completata', [RegistrazioneController::class, 'showCompletata'])->name('registrazione.completata');

    // Forgot password
    $router->get('/password/forgot', [ForgotPasswordController::class, 'showForm'])->name('password.forgot');
    $router->post('/password/forgot', [ForgotPasswordController::class, 'sendReset'])->name('password.forgot.post');
    $router->get('/password/reset/{token}', [ResetPasswordController::class, 'showForm'])->name('password.reset.form');
    $router->post('/password/reset/{token}', [ResetPasswordController::class, 'reset'])->name('password.reset.post');

});

// --- Authenticated routes ---
$router->group(['middleware' => [CsrfMiddleware::class, AuthMiddleware::class]], function ($router) {

    // Forced password change
    $router->get('/password/change', [AuthController::class, 'showChangePassword'])->name('password.change');
    $router->post('/password/change', [AuthController::class, 'changePassword'])->name('password.change.post');

    // ISO 27001 A.9.4.2 — MFA TOTP challenge (during login)
    $router->get('/mfa/challenge', [TotpController::class, 'showChallenge'])->name('mfa.challenge');
    $router->post('/mfa/challenge', [TotpController::class, 'verifyChallenge'])->name('mfa.challenge.verify');

    // MFA forced setup (admin policy requires MFA)
    $router->get('/mfa/setup/forced', [TotpController::class, 'showForcedSetup'])->name('mfa.setup.forced');
    $router->post('/mfa/setup/forced', [TotpController::class, 'verifyForcedSetup'])->name('mfa.setup.forced.verify');

    // MFA backup codes display
    $router->get('/mfa/backup', [TotpController::class, 'showBackupCodes'])->name('mfa.backup.show');

    // MFA setup from profile
    $router->get('/mfa/setup', [TotpController::class, 'showSetup'])->name('mfa.setup');
    $router->post('/mfa/setup', [TotpController::class, 'verifySetup'])->name('mfa.setup.verify');

    // MFA management from profile
    $router->post('/mfa/disable', [TotpController::class, 'disable'])->name('mfa.disable');
    $router->post('/mfa/backup/regenerate', [TotpController::class, 'regenerateBackupCodes'])->name('mfa.backup.regenerate');
    $router->get('/mfa/status', [TotpController::class, 'status'])->name('mfa.status');

    // User profile
    $router->get('/profile', [AuthController::class, 'showProfile'])->name('profile');
    $router->post('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');
    $router->post('/profile/password', [AuthController::class, 'updatePassword'])->name('profile.password.update');
    $router->post('/profile/avatar', [AuthController::class, 'uploadAvatar'])->name('profile.avatar.upload');
    $router->post('/profile/avatar/remove', [AuthController::class, 'removeAvatar'])->name('profile.avatar.remove');

    // Reusable avatar crop endpoint (profile + teams)
    $router->post('/api/avatar/crop', [AvatarCropController::class, 'crop'])->name('api.avatar.crop');

    // Profile HTMX partials
    $router->get('/profile/sessions', [AuthController::class, 'listSessions'])->name('profile.sessions');
    $router->post('/profile/sessions/{id}/revoke', [AuthController::class, 'revokeSession'])->name('profile.sessions.revoke');
    $router->get('/profile/login-history', [AuthController::class, 'loginHistory'])->name('profile.login-history');
    $router->get('/profile/activity', [AuthController::class, 'recentActivity'])->name('profile.activity');

});

// NOTE: POST /admin/users/{id}/reset-password is now handled by
// App\Modules\Admin\Controllers\UserController::resetPassword (Admin module).
