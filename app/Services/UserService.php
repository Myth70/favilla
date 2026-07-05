<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\EventDispatcher;
use App\Events\UserCreated;
use App\Repositories\UserRepository;
use App\Support\ClientIp;
use PDO;

class UserService
{
    private UserRepository $userRepo;
    private PDO $pdo;

    public function __construct()
    {
        $this->userRepo = app(UserRepository::class);
        $this->pdo = app(PDO::class);
    }

    /**
     * Admin reset password: generate temporary password, set must_change_password.
     * Returns the temporary plain-text password (shown once to admin).
     */
    public function adminResetPassword(int $userId): array
    {
        $user = $this->userRepo->find($userId);
        if (!$user) {
            return ['success' => false, 'error' => 'Utente non trovato.'];
        }

        // Generate temporary password (16 chars hex)
        $tempPassword = bin2hex(random_bytes(8));
        $hashedPassword = password_hash($tempPassword, PASSWORD_ARGON2ID);

        $this->userRepo->setTemporaryPassword($userId, $hashedPassword);

        // Log in password_resets for audit (SHA256 — coerente con ForgotPasswordController)
        $tokenHash = hash('sha256', $tempPassword);
        $stmt = $this->pdo->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))'
        );
        $stmt->execute([$userId, $tokenHash]);

        // Audit log
        $adminId = $_SESSION['user_id'] ?? null;
        $ip = ClientIp::resolve();
        $stmt = $this->pdo->prepare(
            "INSERT INTO audit_logs (user_id, action, entity, entity_id, ip, new_value)
             VALUES (?, 'password_reset', 'user', ?, ?, 'Admin reset password')"
        );
        $stmt->execute([$adminId, $userId, $ip]);

        return [
            'success' => true,
            'temp_password' => $tempPassword,
            'user' => $user,
        ];
    }

    /**
     * Change password (user self-service, after forced change).
     */
    public function changePassword(int $userId, string $newPassword): bool
    {
        $hashed = password_hash($newPassword, PASSWORD_ARGON2ID);
        $result = $this->userRepo->updatePassword($userId, $hashed);

        if ($result) {
            // ISO 27001: record password in history and update changed_at
            try {
                $policyService = app(PasswordPolicyService::class);
                $policyService->recordInHistory($userId, $hashed);
                $policyService->touchPasswordChangedAt($userId);
            } catch (\Throwable) {
                // Non-fatal: password change still succeeds
            }

            // Marca tutti i token di reset pendenti come usati
            $this->pdo->prepare(
                'UPDATE password_resets SET used_at = NOW()
                 WHERE user_id = ? AND used_at IS NULL'
            )->execute([$userId]);

            // Audit log
            $ip = ClientIp::resolve();
            $this->pdo->prepare(
                "INSERT INTO audit_logs (user_id, action, entity, entity_id, ip)
                 VALUES (?, 'password_changed', 'user', ?, ?)"
            )->execute([$userId, $userId, $ip]);
        }

        return $result;
    }

    /**
     * Update profile name.
     */
    public function updateProfileName(int $userId, string $name): bool
    {
        return $this->userRepo->updateName($userId, $name);
    }

    /**
     * Trova un utente per ID.
     */
    public function findUser(int $userId): ?array
    {
        return $this->userRepo->find($userId);
    }

    /**
     * Trova un utente con ruoli e permessi.
     */
    public function findUserWithPermissions(int $userId): ?array
    {
        return $this->userRepo->findWithPermissions($userId);
    }

    /**
     * Verifica password corrente utente.
     */
    public function verifyPassword(int $userId, string $password): bool
    {
        $user = $this->userRepo->find($userId);
        if (!$user) {
            return false;
        }

        return password_verify($password, (string) ($user['password'] ?? ''));
    }

    /**
     * Verifica se username gia presente (anche su account inattivi/non cancellati).
     */
    public function isUsernameTaken(string $username): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Verifica se email gia presente (anche su account inattivi/non cancellati).
     */
    public function isEmailTaken(string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Crea utente inattivo per flusso registrazione pubblica.
     */
    public function createInactiveUser(string $name, string $username, string $email, string $password): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, username, email, password, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, 0, NOW(), NOW())'
        );

        $stmt->execute([
            $name,
            $username,
            $email,
            password_hash($password, PASSWORD_ARGON2ID),
        ]);

        $userId = (int) $this->pdo->lastInsertId();
        EventDispatcher::getInstance()->dispatch(new UserCreated($userId, $email));
        return $userId;
    }

    /**
     * Crea un utente per provisioning JIT da identità esterna (SSO): attivo,
     * nessun cambio password forzato, password locale random inutilizzabile
     * (l'accesso avviene sempre via IdP), ruolo assegnato per slug.
     */
    public function createExternalUser(string $name, string $username, string $email, string $roleSlug): int
    {
        $unusable = password_hash(bin2hex(random_bytes(32)), PASSWORD_ARGON2ID);

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, username, email, password, is_active, must_change_password, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, 0, NOW(), NOW())'
        );
        $stmt->execute([$name, $username, $email, $unusable]);
        $userId = (int) $this->pdo->lastInsertId();

        $role = $this->pdo->prepare('SELECT id FROM roles WHERE slug = ? LIMIT 1');
        $role->execute([$roleSlug]);
        $roleId = $role->fetchColumn();
        if ($roleId) {
            $this->pdo->prepare('INSERT INTO user_role (user_id, role_id) VALUES (?, ?)')
                ->execute([$userId, (int) $roleId]);
        }

        EventDispatcher::getInstance()->dispatch(new UserCreated($userId, $email));

        return $userId;
    }

    /**
     * Get user preferences.
     */
    public function getPreferences(int $userId): array
    {
        return $this->userRepo->getPreferences($userId);
    }

    /**
     * Update avatar: delete old owned file, save new path.
     */
    public function updateAvatar(int $userId, string $filename): void
    {
        $oldPath = $this->userRepo->getAvatarPath($userId);
        if ($oldPath && !str_contains($oldPath, '/')) {
            \App\Services\FileUploadService::delete($oldPath, 'avatars');
        }
        $this->userRepo->updateAvatar($userId, $filename);
    }

    /**
     * Remove avatar: delete owned file, set null.
     */
    public function removeAvatar(int $userId): void
    {
        $oldPath = $this->userRepo->getAvatarPath($userId);
        if ($oldPath && !str_contains($oldPath, '/')) {
            \App\Services\FileUploadService::delete($oldPath, 'avatars');
        }
        $this->userRepo->updateAvatar($userId, null);
    }

    /**
     * Cancella tutte le sessioni DB attive dell'utente.
     * Da chiamare ogni volta che ruoli o permessi vengono modificati.
     */
    public function invalidateUserSessions(int $userId): void
    {
        $this->pdo->prepare('DELETE FROM sessions WHERE user_id = ?')
            ->execute([$userId]);
    }
}
