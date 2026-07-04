<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Core\Controller;
use App\Services\SessionManager;
use App\Services\TotpService;
use App\Services\UserService;
use App\Traits\ControllerHelpers;

/**
 * ISO 27001 A.9.4.2 — TOTP MFA controller.
 *
 * Handles: setup QR, verify setup, login challenge, disable, backup codes.
 */
class TotpController extends Controller
{
    use ControllerHelpers;

    private TotpService $totp;
    private UserService $userService;

    public function __construct()
    {
        $this->totp = app(TotpService::class);
        $this->userService = app(UserService::class);
    }

    // ------------------------------------------------------------------
    // Login MFA challenge (public — user is in "mfa_pending" state)
    // ------------------------------------------------------------------

    /**
     * Show the MFA verification form during login.
     */
    public function showChallenge(): void
    {
        if (empty($_SESSION['_mfa_pending_user_id'])) {
            $this->redirect(route('login'));
            return;
        }

        $error = $_SESSION['_mfa_error'] ?? null;
        unset($_SESSION['_mfa_error']);

        $this->render('Auth/Views/totp-challenge', [
            'layout'    => 'auth',
            'authPage'  => true,
            'error'     => $error,
            'pageTitle' => 'Verifica MFA',
        ]);
    }

    /**
     * Verify the MFA code during login.
     */
    public function verifyChallenge(): void
    {
        $userId = $_SESSION['_mfa_pending_user_id'] ?? null;
        if (!$userId) {
            $this->redirect(route('login'));
            return;
        }

        $code = trim($_POST['totp_code'] ?? '');
        if ($code === '') {
            $_SESSION['_mfa_error'] = 'Inserisci il codice di verifica.';
            $this->redirect(route('mfa.challenge'));
            return;
        }

        if (!$this->totp->verifyLogin((int) $userId, $code)) {
            // Rate-limit MFA attempts
            $attempts = ($_SESSION['_mfa_attempts'] ?? 0) + 1;
            $_SESSION['_mfa_attempts'] = $attempts;

            if ($attempts >= 5) {
                // Too many failures — destroy session entirely
                $loginError = 'Troppi tentativi MFA falliti. Effettua di nuovo il login.';
                session_unset();
                session_destroy();
                app(SessionManager::class)->start();
                $_SESSION['_login_error'] = $loginError;
                $this->redirect(route('login'));
                return;
            }

            $_SESSION['_mfa_error'] = 'Codice non valido. Riprova. (' . (5 - $attempts) . ' tentativi rimasti)';
            $this->redirect(route('mfa.challenge'));
            return;
        }

        // MFA verified — complete login
        $this->completeMfaLogin();
    }

    /**
     * Complete login after successful MFA verification.
     */
    private function completeMfaLogin(): void
    {
        $redirectTo = $_SESSION['_mfa_pending_redirect'] ?? '';

        // Clean up MFA session state
        unset(
            $_SESSION['_mfa_pending_user_id'],
            $_SESSION['_mfa_pending_redirect'],
            $_SESSION['_mfa_attempts']
        );

        // Mark MFA as passed
        $_SESSION['_mfa_verified'] = true;

        // Redirect — riusa la validazione centralizzata di AuthController
        // (controlla raw+decoded: //, \, .., varianti percent-encoded).
        if ($redirectTo !== '' && AuthController::isSafeRedirectTarget((string) $redirectTo)) {
            $this->redirect((string) $redirectTo);
            return;
        }

        if (!empty($_SESSION['_intended_url'])) {
            $url = (string) $_SESSION['_intended_url'];
            unset($_SESSION['_intended_url']);
            if (AuthController::isSafeRedirectTarget($url)) {
                $this->redirect($url);
                return;
            }
        }

        $this->redirect(route('home'));
    }

    // ------------------------------------------------------------------
    // MFA forced setup (user must configure before proceeding)
    // ------------------------------------------------------------------

    /**
     * Show forced MFA setup page.
     */
    public function showForcedSetup(): void
    {
        if (empty($_SESSION['user_id'])) {
            $this->redirect(route('login'));
            return;
        }

        $userId = (int) $_SESSION['user_id'];

        // If already enabled, skip
        if ($this->totp->isEnabled($userId)) {
            $this->redirect(route('home'));
            return;
        }

        $base32Secret = $this->totp->generateSecret($userId);
        $_SESSION['_totp_setup_secret'] = $base32Secret;

        $uri = $this->totp->getProvisioningUri($base32Secret, $_SESSION['user_email'] ?? '');

        $this->render('Auth/Views/totp-setup', [
            'layout'      => 'auth',
            'authPage'    => true,
            'pageTitle'   => 'Configura MFA',
            'secret'      => $base32Secret,
            'qrUri'       => $uri,
            'forced'      => true,
            'error'       => $_SESSION['_mfa_setup_error'] ?? null,
        ]);
        unset($_SESSION['_mfa_setup_error']);
    }

    /**
     * Verify forced setup code.
     */
    public function verifyForcedSetup(): void
    {
        if (empty($_SESSION['user_id'])) {
            $this->redirect(route('login'));
            return;
        }

        $code = trim($_POST['totp_code'] ?? '');
        if ($code === '') {
            $_SESSION['_mfa_setup_error'] = 'Inserisci il codice generato dalla tua app.';
            $this->redirect(route('mfa.setup.forced'));
            return;
        }

        $result = $this->totp->verifySetup((int) $_SESSION['user_id'], $code);

        if (!$result['valid']) {
            $_SESSION['_mfa_setup_error'] = 'Codice non valido. Assicurati di copiare il codice dall\'app correttamente.';
            $this->redirect(route('mfa.setup.forced'));
            return;
        }

        // Show backup codes
        $_SESSION['_mfa_backup_codes'] = $result['backup_codes'];
        $_SESSION['_mfa_forced_setup'] = true;
        $_SESSION['_mfa_verified'] = true;
        flash_success('MFA attivato con successo!');
        $this->redirect(route('mfa.backup.show'));
    }

    // ------------------------------------------------------------------
    // Profile MFA management (authenticated)
    // ------------------------------------------------------------------

    /**
     * Show MFA setup page from profile.
     */
    public function showSetup(): void
    {
        $userId = (int) $_SESSION['user_id'];

        if ($this->totp->isEnabled($userId)) {
            $this->redirect(route('profile') . '#security');
            return;
        }

        $base32Secret = $this->totp->generateSecret($userId);
        $_SESSION['_totp_setup_secret'] = $base32Secret;

        $uri = $this->totp->getProvisioningUri($base32Secret, $_SESSION['user_email'] ?? '');

        $this->render('Auth/Views/totp-setup', [
            'pageTitle'   => 'Configura MFA',
            'secret'      => $base32Secret,
            'qrUri'       => $uri,
            'forced'      => false,
            'error'       => $_SESSION['_mfa_setup_error'] ?? null,
        ]);
        unset($_SESSION['_mfa_setup_error']);
    }

    /**
     * Verify setup code from profile.
     */
    public function verifySetup(): void
    {
        $code = trim($_POST['totp_code'] ?? '');
        if ($code === '') {
            $_SESSION['_mfa_setup_error'] = 'Inserisci il codice generato dalla tua app.';
            $this->redirect(route('mfa.setup'));
            return;
        }

        $result = $this->totp->verifySetup((int) $_SESSION['user_id'], $code);

        if (!$result['valid']) {
            $_SESSION['_mfa_setup_error'] = 'Codice non valido. Riprova.';
            $this->redirect(route('mfa.setup'));
            return;
        }

        $_SESSION['_mfa_backup_codes'] = $result['backup_codes'];
        $_SESSION['_mfa_verified'] = true;
        flash_success('Autenticazione a due fattori attivata!');
        $this->redirect(route('mfa.backup.show'));
    }

    /**
     * Show backup codes (once, after setup or regeneration).
     */
    public function showBackupCodes(): void
    {
        $codes = $_SESSION['_mfa_backup_codes'] ?? null;
        if (!$codes) {
            $this->redirect(route('profile'));
            return;
        }

        // Determine layout based on whether this is forced setup
        $forced = !empty($_SESSION['_mfa_forced_setup']);

        $this->render('Auth/Views/totp-backup-codes', [
            'layout'    => $forced ? 'auth' : 'main',
            'authPage'  => $forced,
            'pageTitle' => 'Codici di Backup',
            'codes'     => $codes,
        ]);

        // Codes shown — clear from session
        unset($_SESSION['_mfa_backup_codes']);
    }

    /**
     * Disable MFA from profile.
     */
    public function disable(): void
    {
        $password = $_POST['password'] ?? '';
        $userId   = (int) $_SESSION['user_id'];

        // Require password confirmation
        if (!$this->userService->verifyPassword($userId, $password)) {
            flash_error('Password non corretta. MFA non disattivato.');
            $this->redirect(route('profile'));
            return;
        }

        $this->totp->disable($userId);
        unset($_SESSION['_mfa_verified']);

        flash_success('Autenticazione a due fattori disattivata.');
        $this->redirect(route('profile'));
    }

    /**
     * Regenerate backup codes.
     */
    public function regenerateBackupCodes(): void
    {
        $userId = (int) $_SESSION['user_id'];

        if (!$this->totp->isEnabled($userId)) {
            flash_error('MFA non è attivo.');
            $this->redirect(route('profile'));
            return;
        }

        $codes = $this->totp->regenerateBackupCodes($userId);
        $_SESSION['_mfa_backup_codes'] = $codes;
        flash_success('Nuovi codici di backup generati!');
        $this->redirect(route('mfa.backup.show'));
    }

    /**
     * MFA status for profile HTMX partial.
     */
    public function status(): void
    {
        $userId = (int) $_SESSION['user_id'];
        $enabled = $this->totp->isEnabled($userId);
        $remaining = $enabled ? $this->totp->getRemainingBackupCodesCount($userId) : 0;

        $this->renderPartial('Auth/Views/partials/mfa_status', [
            'mfaEnabled'     => $enabled,
            'backupRemaining' => $remaining,
        ]);
    }
}
