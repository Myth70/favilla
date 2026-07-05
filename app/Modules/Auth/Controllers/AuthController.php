<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Core\Controller;
use App\Core\Validator;
use App\Security\CsrfToken;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\FileUploadService;
use App\Services\PasswordPolicyService;
use App\Services\ProfileService;
use App\Services\TotpService;
use App\Services\UserService;
use App\Traits\ControllerHelpers;

class AuthController extends Controller
{
    use ControllerHelpers;

    private AuthService $authService;
    private UserService $userService;
    private ProfileService $profileService;
    private PasswordPolicyService $passwordPolicy;

    public function __construct()
    {
        $this->authService    = app(AuthService::class);
        $this->userService    = app(UserService::class);
        $this->profileService = app(ProfileService::class);
        $this->passwordPolicy = app(PasswordPolicyService::class);
    }

    /**
     * Show login form.
     */
    public function showLogin(): void
    {
        // Already logged in? Go to home
        if (!empty($_SESSION['user_id'])) {
            $this->redirect(route('home'));
            return;
        }

        // Try remember-me cookie auto-login
        $user = $this->authService->loginFromRememberToken();
        if ($user) {
            // MFA pending (flag impostati da loginFromRememberToken): vai alla
            // challenge/setup come nel login POST, mai dritto alla home.
            if (!empty($_SESSION['_mfa_required']) && empty($_SESSION['_mfa_verified'])) {
                $this->redirect(!empty($_SESSION['_mfa_forced_setup'])
                    ? route('mfa.setup.forced')
                    : route('mfa.challenge'));
                return;
            }
            if (!empty($_SESSION['must_change_password'])) {
                $this->redirect(route('password.change'));
                return;
            }
            $this->redirect(route('home'));
            return;
        }

        $error = $_SESSION['_login_error'] ?? null;
        unset($_SESSION['_login_error']);

        // SSO OIDC: bottone e modalità "solo SSO". ?local=1 è il break-glass
        // che rimostra il form password (visibilità, non access control: il
        // POST /login resta protetto da rate-limit, TOTP e policy password).
        $ssoEnabled    = app(\App\Modules\Auth\Services\OidcService::class)->isEnabled();
        $ssoOnly       = $ssoEnabled && (bool) setting('sso_only', false);
        $showLocalForm = !$ssoOnly || isset($_GET['local']);
        $ssoLabel      = trim((string) setting('sso_oidc_button_label', ''));

        $this->render('Auth/Views/login', [
            'layout'         => 'auth',
            'authPage'       => true,
            'error'          => $error,
            'pageTitle'      => 'Accesso',
            'ssoEnabled'     => $ssoEnabled,
            'ssoOnly'        => $ssoOnly,
            'showLocalForm'  => $showLocalForm,
            'ssoButtonLabel' => $ssoLabel !== '' ? $ssoLabel : t('auth.login.sso_button'),
        ]);
    }

    /**
     * Handle login POST.
     */
    public function login(): void
    {
        $redirectTo = trim($_POST['redirect_to'] ?? '');

        if (!self::isSafeRedirectTarget($redirectTo)) {
            $redirectTo = '';
        }

        $validator = new Validator();
        $valid = $validator->validate($_POST, [
            'login'    => 'required',
            'password' => 'required',
        ]);

        if (!$valid) {
            $_SESSION['_login_error'] = 'Inserisci username/email e password.';
            $this->redirectAfterFailedLogin($redirectTo);
            return;
        }

        $clean = $this->cleanPost(['login']);
        $login = $clean['login'];
        $password = $_POST['password']; // Don't sanitize passwords
        $ip = $this->resolveClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $authService = $this->authService;
        $result = $authService->attempt($login, $password, $ip, $userAgent);

        if (!$result['success']) {
            $_SESSION['_login_error'] = $result['error'];
            $this->redirectAfterFailedLogin($redirectTo);
            return;
        }

        // Set remember-me token if checkbox was checked
        if (!empty($_POST['remember'])) {
            $authService->setRememberToken($result['user']['id']);
        }

        // Rigenera il token CSRF dopo autenticazione riuscita (session fixation protection)
        CsrfToken::regenerate();

        // ISO 27001 A.9.4.2 — TOTP/MFA check
        try {
            $totpService = app(TotpService::class);
            $userId = (int) $result['user']['id'];
            $hasMfa = $totpService->isEnabled($userId);
            $needsSetup = !$hasMfa && $totpService->isSetupRequired($userId);

            if ($hasMfa || $needsSetup) {
                $_SESSION['_mfa_required'] = true;
                if ($hasMfa) {
                    // Redirect to MFA challenge
                    $_SESSION['_mfa_pending_redirect'] = $redirectTo;
                    $_SESSION['_mfa_pending_user_id'] = $userId;
                    $this->redirect(route('mfa.challenge'));
                    return;
                } else {
                    // Redirect to forced setup
                    $_SESSION['_mfa_forced_setup'] = true;
                    $this->redirect(route('mfa.setup.forced'));
                    return;
                }
            }
        } catch (\Throwable $e) {
            // Non-fatal: TOTP tables may not exist yet — log per diagnostica
            app_log('error', '[AuthController] MFA check fallito: ' . $e->getMessage());
        }

        // Check if forced password change
        if (!empty($_SESSION['must_change_password'])) {
            $this->redirect(route('password.change'));
            return;
        }

        // ISO 27001 A.9.4.3 — Password rotation: check expiry
        if ($this->passwordPolicy->isPasswordExpired($result['user']['id'])) {
            $_SESSION['must_change_password'] = true;
            $_SESSION['_change_pw_error'] = 'La password è scaduta. Impostane una nuova per continuare.';
            $this->redirect(route('password.change'));
            return;
        }

        $this->redirectAfterLogin($redirectTo);
    }

    /**
     * Whether a post-login redirect target is a safe local path.
     *
     * Accepts only paths starting with `/`; rejects protocol-relative URLs,
     * backslashes (browser normalization), parent-dir sequences and their
     * percent-encoded variants. Empty string is treated as safe (no redirect).
     */
    public static function isSafeRedirectTarget(string $redirectTo): bool
    {
        if ($redirectTo === '') {
            return true;
        }

        $decoded = rawurldecode($redirectTo);

        return (bool) preg_match('~^/[a-zA-Z0-9_\-/.?&=%#]*$~', $redirectTo)
            && !str_starts_with($redirectTo, '//')
            && !str_starts_with($redirectTo, '/\\')
            && !str_contains($decoded, '\\')
            && !str_contains($decoded, '//')
            && !str_contains($decoded, '..');
    }

    /**
     * After failed login: back to /login with error in session.
     */
    private function redirectAfterFailedLogin(string $redirectTo): void
    {
        $this->redirect(route('login'));
    }

    /**
     * After successful login: redirect_to > _intended_url > /home.
     */
    private function redirectAfterLogin(string $redirectTo): void
    {
        if ($redirectTo !== '') {
            $this->redirect($redirectTo);
            return;
        }
        if (!empty($_SESSION['_intended_url'])) {
            $url = (string) $_SESSION['_intended_url'];
            unset($_SESSION['_intended_url']);

            // Riusa la validazione centralizzata (raw+decoded: //, \, .., encoded).
            if (!self::isSafeRedirectTarget($url)) {
                $url = route('home');
            }

            $this->redirect($url);
            return;
        }
        $this->redirect(route('home'));
    }

    /**
     * Handle logout POST.
     */
    public function logout(): void
    {
        $this->authService->logout();

        header('Location: ' . route('login'));
        exit;
    }

    /**
     * Show forced password change form.
     */
    public function showChangePassword(): void
    {
        $error = $_SESSION['_change_pw_error'] ?? null;
        $success = $_SESSION['_change_pw_success'] ?? null;
        unset($_SESSION['_change_pw_error'], $_SESSION['_change_pw_success']);

        $this->render('Auth/Views/change-password', [
            'layout'    => 'auth',
            'authPage'  => true,
            'error'     => $error,
            'success'   => $success,
            'pageTitle' => 'Cambio Password',
        ]);
    }

    /**
     * Handle forced password change POST.
     */
    public function changePassword(): void
    {
        $validator = new Validator();
        $valid = $validator->validate($_POST, [
            'password'              => 'required|min:8',
            'password_confirmation' => 'required',
        ]);

        if (!$valid) {
            $_SESSION['_change_pw_error'] = implode(' ', array_map(
                fn ($errs) => $errs[0],
                $validator->errors()
            ));
            $this->redirect(route('password.change'));
            return;
        }

        $pw = $_POST['password'];

        if ($_POST['password'] !== $_POST['password_confirmation']) {
            $_SESSION['_change_pw_error'] = 'Le password non corrispondono.';
            $this->redirect(route('password.change'));
            return;
        }

        // ISO 27001 A.9.4.3 — Password policy validation
        $policyErrors = $this->passwordPolicy->validate($pw, $_SESSION['user_id']);
        if (!empty($policyErrors)) {
            $_SESSION['_change_pw_error'] = implode(' ', $policyErrors);
            $this->redirect(route('password.change'));
            return;
        }

        $success = $this->userService->changePassword($_SESSION['user_id'], $_POST['password']);

        if (!$success) {
            $_SESSION['_change_pw_error'] = 'Errore durante il salvataggio. Riprova.';
            $this->redirect(route('password.change'));
            return;
        }

        // Il flag viene azzerato nel controller, non nel Service
        $_SESSION['must_change_password'] = false;

        // Rigenera CSRF dopo operazione sensibile
        CsrfToken::regenerate();

        flash_success('Password aggiornata con successo.');
        $this->redirect(route('home'));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Risolve l'IP reale del client rispettando TRUSTED_PROXIES.
     * Delega a App\Support\ClientIp per evitare duplicazione logica.
     */
    private function resolveClientIp(): string
    {
        return \App\Support\ClientIp::resolve();
    }

    // ------------------------------------------------------------------
    // User Profile
    // ------------------------------------------------------------------

    /**
     * Show user profile page.
     */
    public function showProfile(): void
    {
        $userId = $_SESSION['user_id'];
        $profileUser = $this->userService->findUserWithPermissions($userId);
        $preferences = $this->userService->getPreferences($userId);
        $stats = $this->profileService->getAccountStats($userId, $profileUser['email'] ?? '');

        $errors = $_SESSION['_errors'] ?? [];
        $old = $_SESSION['_old'] ?? [];
        unset($_SESSION['_errors'], $_SESSION['_old']);

        // Sync avatar in sessione (gestisce il caso modifica da altra sessione/Admin)
        $_SESSION['user_avatar'] = $profileUser['avatar_path'] ?? null;

        $this->render('Auth/Views/profile', [
            'pageTitle'      => 'Il mio profilo',
            'profileUser'    => $profileUser,
            'preferences'    => $preferences,
            'stats'          => $stats,
            'currentSessionId' => $_SESSION['_db_session_id'] ?? 0,
            'errors'         => $errors,
            'old'            => $old,
            'breadcrumbs'    => [
                ['label' => 'Home', 'url' => route('home')],
                ['label' => 'Profilo'],
            ],
        ]);
    }

    /**
     * Update profile info (name).
     */
    public function updateProfile(): void
    {
        $userId = $_SESSION['user_id'];
        $clean = $this->cleanPost(['name']);
        $name = trim($clean['name'] ?? '');

        $errors = [];
        if (empty($name)) {
            $errors['name'] = 'Il nome è obbligatorio.';
        } elseif (mb_strlen($name) > 100) {
            $errors['name'] = 'Il nome non può superare i 100 caratteri.';
        }

        if ($errors) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old'] = ['name' => $name];
            $this->redirect(route('profile'));
            return;
        }

        $this->userService->updateProfileName($userId, $name);

        // Update session immediately
        $_SESSION['user_name'] = $name;

        flash_success('Profilo aggiornato con successo.');
        $this->redirect(route('profile'));
    }

    /**
     * Update password from profile (requires current password verification).
     */
    public function updatePassword(): void
    {
        $userId = $_SESSION['user_id'];
        $current = $_POST['current_password'] ?? '';
        $newPw = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirmation'] ?? '';

        // Verify current password
        $user = $this->userService->findUser($userId);

        if (!$user) {
            session_destroy();
            $this->redirect(route('login'));
            return;
        }

        $errors = [];
        if (!password_verify($current, $user['password'])) {
            $errors['current_password'] = 'La password attuale non è corretta.';
        }
        if ($newPw !== $confirm) {
            $errors['password_confirmation'] = 'Le password non corrispondono.';
        }

        // ISO 27001 A.9.4.3 — Password policy validation
        if (empty($errors)) {
            $policyErrors = $this->passwordPolicy->validate($newPw, $userId);
            if (!empty($policyErrors)) {
                $errors['password'] = implode(' ', $policyErrors);
            }
        }

        if ($errors) {
            $_SESSION['_errors'] = $errors;
            $this->redirect(route('profile') . '#cambia-password');
            return;
        }

        $this->userService->changePassword($userId, $newPw);

        // Rigenera CSRF dopo cambio password
        CsrfToken::regenerate();

        flash_success('Password aggiornata con successo.');
        $this->redirect(route('profile'));
    }

    /**
     * Upload avatar — POST /profile/avatar
     * Handles two sources:
     *   1) avatar_url (POST hidden field) — path from Files library (e.g. "files/img.jpg")
     *   2) avatar    (FILE upload)        — direct file upload
     */
    public function uploadAvatar(): void
    {
        $userId    = $_SESSION['user_id'];
        $clean     = $this->cleanPost(['avatar_url']);
        $avatarUrl = trim($clean['avatar_url'] ?? '');

        // Source 1: selected from Files library
        if ($avatarUrl !== '' && preg_match('#^[a-zA-Z0-9_/-]+\.[a-zA-Z0-9]+$#', $avatarUrl)) {
            // uploads/files non è più servita da Apache (ACL via route): copia
            // l'immagine scelta nella directory pubblica degli avatar.
            try {
                $filename = $this->copyLibraryImageToAvatars($avatarUrl, (int) $userId);
            } catch (\RuntimeException $e) {
                flash_error($e->getMessage());
                $this->redirect(route('profile'));
                return;
            }
        } else {
            // Source 2: direct file upload
            try {
                $filename = FileUploadService::uploadImage(
                    $_FILES['avatar'] ?? [],
                    'avatars',
                    'avatar_' . $userId . '_'
                );
            } catch (\RuntimeException $e) {
                flash_error($e->getMessage());
                $this->redirect(route('profile'));
                return;
            }
        }

        $this->userService->updateAvatar($userId, $filename);
        $_SESSION['user_avatar'] = $filename;

        flash_success('Foto profilo aggiornata con successo.');
        $this->redirect(route('profile'));
    }

    /**
     * Copia un'immagine della libreria Files in uploads/avatars (pubblica).
     * Necessario perché le directory upload private non sono servite da Apache
     * e l'avatar deve restare visibile a tutti gli utenti autenticati.
     *
     * @return string Basename del file copiato (es. "avatar_3_a1b2c3d4.jpg")
     * @throws \RuntimeException Se il path è invalido o il file non è un'immagine.
     */
    private function copyLibraryImageToAvatars(string $relativePath, int $userId): string
    {
        $basePath    = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $uploadsRoot = realpath($basePath . '/public/uploads');
        $source      = $uploadsRoot !== false ? realpath($uploadsRoot . '/' . $relativePath) : false;

        if ($source === false || !str_starts_with($source, $uploadsRoot . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('File selezionato non valido.');
        }

        $finfo      = new \finfo(FILEINFO_MIME_TYPE);
        $mime       = (string) $finfo->file($source);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) {
            throw new \RuntimeException('Per la foto profilo seleziona un\'immagine (JPG, PNG, GIF o WebP).');
        }

        $filename = 'avatar_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
        $dest     = FileUploadService::resolveUploadDir('avatars') . $filename;
        if (!copy($source, $dest)) {
            throw new \RuntimeException('Errore durante il salvataggio della foto profilo. Riprova.');
        }

        return $filename;
    }

    /**
     * Remove avatar — POST /profile/avatar/remove
     */
    public function removeAvatar(): void
    {
        $userId = $_SESSION['user_id'];

        $this->userService->removeAvatar($userId);

        unset($_SESSION['user_avatar']);

        flash_success('Foto profilo rimossa.');
        $this->redirect(route('profile'));
    }

    // ------------------------------------------------------------------
    // Profile HTMX partials
    // ------------------------------------------------------------------

    /**
     * List active sessions — GET /profile/sessions (HTMX partial)
     */
    public function listSessions(): void
    {
        $userId = $_SESSION['user_id'];
        $sessions = $this->profileService->getActiveSessions($userId);

        $this->renderPartial('Auth/Views/partials/sessions_table', [
            'sessions'         => $sessions,
            'currentSessionId' => $_SESSION['_db_session_id'] ?? 0,
        ]);
    }

    /**
     * Revoke a session — POST /profile/sessions/{id}/revoke
     */
    public function revokeSession(string $id): void
    {
        $id = (int) $id;
        $userId = $_SESSION['user_id'];
        $currentSessionId = $_SESSION['_db_session_id'] ?? 0;

        // Prevent revoking current session
        if ($id === $currentSessionId) {
            $this->hxToast('Non puoi revocare la sessione corrente.', 'warning');
            return;
        }

        $profileService = $this->profileService;
        $revoked = $profileService->revokeSession($userId, $id);

        if ($revoked) {
            AuditService::log('session_revoked', 'session', $id);
        }

        // Return updated sessions table
        $sessions = $profileService->getActiveSessions($userId);
        $this->renderPartial('Auth/Views/partials/sessions_table', [
            'sessions'         => $sessions,
            'currentSessionId' => $currentSessionId,
        ]);

        if ($revoked) {
            $this->hxToast('Sessione revocata.');
        }
    }

    /**
     * Login history — GET /profile/login-history (HTMX partial)
     */
    public function loginHistory(): void
    {
        $userId = $_SESSION['user_id'];
        $user = $this->userService->findUser($userId);

        $attempts = $this->profileService->getLoginHistory($user['email'] ?? '');

        $this->renderPartial('Auth/Views/partials/login_history', [
            'attempts' => $attempts,
        ]);
    }

    /**
     * Recent activity — GET /profile/activity (HTMX partial)
     */
    public function recentActivity(): void
    {
        $userId = $_SESSION['user_id'];
        $activities = $this->profileService->getRecentActivity($userId);

        $this->renderPartial('Auth/Views/partials/activity_timeline', [
            'activities' => $activities,
        ]);
    }

}
