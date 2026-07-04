<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Core\Controller;
use App\Security\RateLimiter;
use App\Services\AuthService;
use App\Services\PasswordPolicyService;
use App\Support\ClientIp;

class ResetPasswordController extends Controller
{
    private const RESET_LIMIT_KEY = 'password_reset_token';
    private const RESET_MAX_ATTEMPTS = 10;

    private AuthService $authService;
    private PasswordPolicyService $passwordPolicy;

    public function __construct()
    {
        $this->authService = app(AuthService::class);
        $this->passwordPolicy = app(PasswordPolicyService::class);
    }

    public function showForm(string $token): void
    {
        $ip = ClientIp::resolve();
        if ($this->isResetRateLimited($ip)) {
            $_SESSION['_reset_error'] = 'Troppi tentativi di reset. Riprova tra qualche minuto.';
            $this->redirect(route('password.forgot'));
            return;
        }

        $resetData = $this->authService->validatePasswordResetToken($token);

        $error = $_SESSION['_reset_error'] ?? null;
        $success = $_SESSION['_reset_success'] ?? null;
        unset($_SESSION['_reset_error'], $_SESSION['_reset_success']);

        if (!$resetData && empty($success)) {
            $this->recordResetAttempt($ip, false);
            $_SESSION['_reset_error'] = 'Il link di reset non è valido o è scaduto.';
            $this->redirect(route('password.forgot'));
            return;
        }

        $this->render('Auth/Views/reset-password', [
            'layout'        => 'auth',
            'authPage'      => true,
            'error'         => $error,
            'success'       => $success,
            'token'         => $token,
            'rules'         => $this->passwordPolicy->getRulesDescription(),
            'pageTitle'     => 'Reimposta Password',
            'maskedAccount' => $this->maskEmail((string) ($resetData['email'] ?? '')),
        ]);
    }

    public function reset(string $token): void
    {
        $ip = ClientIp::resolve();
        if ($this->isResetRateLimited($ip)) {
            $_SESSION['_reset_error'] = 'Troppi tentativi di reset. Riprova tra qualche minuto.';
            $this->redirect(route('password.forgot'));
            return;
        }

        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirmation'] ?? '';

        if ($password === '' || $confirm === '') {
            $_SESSION['_reset_error'] = 'Inserisci e conferma la nuova password.';
            $this->redirect(route('password.reset.form', ['token' => $token]));
            return;
        }

        if ($password !== $confirm) {
            $_SESSION['_reset_error'] = 'Le password non corrispondono.';
            $this->redirect(route('password.reset.form', ['token' => $token]));
            return;
        }

        $resetData = $this->authService->validatePasswordResetToken($token);
        if (!$resetData) {
            $this->recordResetAttempt($ip, false);
            $_SESSION['_reset_error'] = 'Il link di reset non è valido o è scaduto.';
            $this->redirect(route('password.forgot'));
            return;
        }

        $policyErrors = $this->passwordPolicy->validate($password, (int) $resetData['user_id']);
        if (!empty($policyErrors)) {
            $_SESSION['_reset_error'] = implode(' ', $policyErrors);
            $this->redirect(route('password.reset.form', ['token' => $token]));
            return;
        }

        $ok = $this->authService->consumePasswordResetToken($token, $password);
        if (!$ok) {
            $this->recordResetAttempt($ip, false);
            $_SESSION['_reset_error'] = 'Impossibile completare il reset password. Richiedi un nuovo link.';
            $this->redirect(route('password.forgot'));
            return;
        }

        $this->recordResetAttempt($ip, true);

        $_SESSION['_reset_success'] = 'Password aggiornata con successo. Ora puoi accedere con le nuove credenziali.';
        $this->redirect(route('login'));
    }

    private function isResetRateLimited(string $ip): bool
    {
        return app(RateLimiter::class)
            ->isLimitedForIpAndAccount($ip, self::RESET_LIMIT_KEY, self::RESET_MAX_ATTEMPTS);
    }

    private function recordResetAttempt(string $ip, bool $success): void
    {
        try {
            app(RateLimiter::class)->record(self::RESET_LIMIT_KEY, $ip, $success);
        } catch (\Throwable) {
            // Non bloccare il flusso utente se il logging tentativi non è disponibile.
        }
    }

    private function maskEmail(string $email): string
    {
        if ($email === '' || !str_contains($email, '@')) {
            return '';
        }

        [$local, $domain] = explode('@', $email, 2);
        if (strlen($local) <= 2) {
            return str_repeat('*', strlen($local)) . '@' . $domain;
        }

        return substr($local, 0, 1) . str_repeat('*', max(strlen($local) - 2, 1)) . substr($local, -1) . '@' . $domain;
    }
}
