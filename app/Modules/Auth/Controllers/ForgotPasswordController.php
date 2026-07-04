<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Core\Controller;
use App\Security\RateLimiter;
use App\Services\AuthService;
use App\Support\ClientIp;
use App\Traits\ControllerHelpers;

class ForgotPasswordController extends Controller
{
    use ControllerHelpers;

    private AuthService $authService;

    public function __construct()
    {
        $this->authService = app(AuthService::class);
    }

    /**
     * Show the forgot password form.
     */
    public function showForm(): void
    {
        // Already logged in? Go home
        if (!empty($_SESSION['user_id'])) {
            $this->redirect(route('home'));
            return;
        }

        $error = $_SESSION['_forgot_error'] ?? null;
        $success = $_SESSION['_forgot_success'] ?? null;
        unset($_SESSION['_forgot_error'], $_SESSION['_forgot_success']);

        $this->render('Auth/Views/forgot-password', [
            'layout'    => 'auth',
            'authPage'  => true,
            'error'     => $error,
            'success'   => $success,
            'pageTitle' => 'Password Dimenticata',
        ]);
    }

    /**
     * Handle forgot password POST — generate reset token and send email.
     */
    public function sendReset(): void
    {
        $ip          = ClientIp::resolve();
        $rateLimiter = app(RateLimiter::class);

        if ($rateLimiter->isLimited($ip)) {
            $_SESSION['_forgot_error'] = 'Troppi tentativi. Riprova tra qualche minuto.';
            $this->redirect(route('password.forgot'));
            return;
        }

        $clean = $this->cleanPost(['email']);
        $email = trim((string) ($clean['email'] ?? ''));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $rateLimiter->record($email ?: 'invalid', $ip, false);
            $_SESSION['_forgot_error'] = 'Inserisci un indirizzo email valido.';
            $this->redirect(route('password.forgot'));
            return;
        }

        // Always show generic success to prevent email enumeration
        $_SESSION['_forgot_success'] = 'Se l\'indirizzo email esiste nel sistema, riceverai le istruzioni per il reset della password.';

        // Record attempt against rate limit (regardless of user existence)
        $rateLimiter->record($email, $ip, false);

        $this->authService->processForgotPassword($email);

        $this->redirect(route('password.forgot'));
    }

}
