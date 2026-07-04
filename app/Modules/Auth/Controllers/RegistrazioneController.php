<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Core\Controller;
use App\Modules\Notifications\Services\NotificationService;
use App\Services\PasswordPolicyService;
use App\Services\UserService;
use App\Traits\ControllerHelpers;

class RegistrazioneController extends Controller
{
    use ControllerHelpers;

    private UserService $userService;
    private PasswordPolicyService $passwordPolicy;

    public function __construct()
    {
        $this->userService = app(UserService::class);
        $this->passwordPolicy = app(PasswordPolicyService::class);
    }

    /**
     * Show the registration form.
     */
    public function showForm(): void
    {
        if (!empty($_SESSION['user_id'])) {
            $this->redirect(route('home'));
            return;
        }

        if (is_single_user()) {
            $this->redirect(route('login'));
            return;
        }

        $errors = $_SESSION['_reg_errors'] ?? [];
        $old    = $_SESSION['_reg_old'] ?? [];
        unset($_SESSION['_reg_errors'], $_SESSION['_reg_old']);

        $this->render('Auth/Views/registrazione', [
            'layout'    => 'auth',
            'authPage'  => true,
            'errors'    => $errors,
            'old'       => $old,
            'pageTitle' => 'Registrazione',
        ]);
    }

    /**
     * Handle registration form POST.
     */
    public function register(): void
    {
        if (!empty($_SESSION['user_id'])) {
            $this->redirect(route('home'));
            return;
        }

        if (is_single_user()) {
            $this->redirect(route('login'));
            return;
        }

        $clean = $this->cleanPost(['name', 'username', 'email', 'email_confirm']);
        $name         = $clean['name'];
        $username     = $clean['username'];
        $email        = $clean['email'];
        $emailConfirm = $clean['email_confirm'];
        // Passwords: never sanitize
        $password        = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        $errors = $this->validateRegistration(
            $name,
            $username,
            $email,
            $emailConfirm,
            $password,
            $passwordConfirm
        );

        if (!empty($errors)) {
            $_SESSION['_reg_errors'] = $errors;
            $_SESSION['_reg_old']    = [
                'name'          => $name,
                'username'      => $username,
                'email'         => $email,
                'email_confirm' => $emailConfirm,
            ];
            $this->redirect(route('registrazione'));
            return;
        }

        try {
            $newUserId = $this->userService->createInactiveUser($name, $username, $email, $password);
        } catch (\PDOException $e) {
            if (($e->getCode() ?? '') === '23000') {
                // Graceful fallback for concurrent registrations hitting UNIQUE constraints.
                $errors = [];
                $message = strtolower($e->getMessage());

                if (str_contains($message, 'username')) {
                    $errors['username'] = 'Username già in uso. Scegline un altro.';
                }
                if (str_contains($message, 'email')) {
                    $errors['email'] = 'Email già registrata. Prova ad accedere o a recuperare la password.';
                }
                if ($errors === []) {
                    $errors['username'] = 'Username o email già in uso.';
                }

                $_SESSION['_reg_errors'] = $errors;
                $_SESSION['_reg_old']    = [
                    'name'          => $name,
                    'username'      => $username,
                    'email'         => $email,
                    'email_confirm' => $emailConfirm,
                ];
                $this->redirect(route('registrazione'));
                return;
            }

            throw $e;
        }

        // Notify all admins
        $this->notifyAdmins($name, $username, $email, $newUserId);

        $this->redirect(route('registrazione.completata'));
    }

    /**
     * Show the registration completion page.
     */
    public function showCompletata(): void
    {
        $this->render('Auth/Views/registrazione-completata', [
            'layout'    => 'auth',
            'authPage'  => true,
            'pageTitle' => 'Registrazione completata',
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @return array<string, string>
     */
    private function validateRegistration(
        string $name,
        string $username,
        string $email,
        string $emailConfirm,
        string $password,
        string $passwordConfirm
    ): array {
        $errors = [];

        if (empty($name)) {
            $errors['name'] = 'Il nome è obbligatorio.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['name'] = 'Il nome deve essere tra 2 e 100 caratteri.';
        }

        if (empty($username)) {
            $errors['username'] = 'Lo username è obbligatorio.';
        } elseif (!preg_match('/^[a-zA-Z0-9_\-]{3,50}$/', $username)) {
            $errors['username'] = 'Lo username può contenere solo lettere, numeri, trattini e underscore (3–50 caratteri).';
        } elseif ($this->userService->isUsernameTaken($username)) {
            $errors['username'] = 'Username già in uso. Scegline un altro.';
        }

        if (empty($email)) {
            $errors['email'] = "L'email è obbligatoria.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Indirizzo email non valido.';
        } elseif (mb_strlen($email) > 255) {
            $errors['email'] = 'Indirizzo email troppo lungo.';
        } elseif ($this->userService->isEmailTaken($email)) {
            $errors['email'] = 'Email già registrata. Prova ad accedere o a recuperare la password.';
        }

        if (empty($emailConfirm)) {
            $errors['email_confirm'] = 'Conferma la tua email.';
        } elseif ($email !== $emailConfirm) {
            $errors['email_confirm'] = 'Le due email non coincidono.';
        }

        if (empty($password)) {
            $errors['password'] = 'La password è obbligatoria.';
        } else {
            // ISO 27001 A.9.4.3 — Password policy validation
            $policyErrors = $this->passwordPolicy->validate($password);
            if (!empty($policyErrors)) {
                $errors['password'] = implode(' ', $policyErrors);
            }
        }

        if (empty($passwordConfirm)) {
            $errors['password_confirm'] = 'Conferma la password.';
        } elseif ($password !== $passwordConfirm) {
            $errors['password_confirm'] = 'Le due password non coincidono.';
        }

        return $errors;
    }

    /**
     * Send in-app notification to all users with the 'admin' role.
     */
    private function notifyAdmins(string $name, string $username, string $email, int $newUserId): void
    {
        try {
            NotificationService::dispatchEventToRole(
                'auth.nuova_registrazione',
                'Auth',
                'admin',
                [
                    'name'     => $name,
                    'username' => $username,
                    'email'    => $email,
                ],
                route('admin.users.index')
            );
        } catch (\Throwable) {
            // Non-fatal: registration still succeeds even if notification fails
        }
    }
}
