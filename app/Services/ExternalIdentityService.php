<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ExternalLoginDeniedException;
use App\Repositories\ExternalIdentityRepository;
use App\Repositories\UserRepository;
use PDO;

/**
 * Risolve un'identità esterna autenticata (OIDC oggi, LDAP in futuro) in un
 * utente locale, applicando la policy di collegamento e provisioning:
 *
 *   1. match per (provider, issuer, subject) già collegato → login;
 *   2. altrimenti match per email VERIFICATA (case-insensitive) → collega e login;
 *   3. altrimenti, se il JIT è abilitato (sso_oidc_jit_enabled) → crea l'utente
 *      con il ruolo di default configurato; se disabilitato → nega.
 *
 * is_active/deleted_at sono verificati a OGNI login, anche su link esistente:
 * disattivare un utente blocca immediatamente anche l'SSO.
 */
class ExternalIdentityService
{
    public function __construct(
        private readonly ExternalIdentityRepository $identities,
        private readonly UserRepository $users,
        private readonly UserService $userService,
        private readonly PDO $pdo,
    ) {
    }

    /**
     * @param array{provider:string, issuer:string, subject:string,
     *              email:?string, email_verified:?bool, name:?string,
     *              preferred_username:?string} $identity
     * @return array<string,mixed> riga utente pronta per AuthService::loginExternal()
     * @throws ExternalLoginDeniedException
     */
    public function resolveUser(array $identity): array
    {
        $link = $this->identities->findBySubject(
            $identity['provider'],
            $identity['issuer'],
            $identity['subject']
        );

        if ($link !== null) {
            $user = $this->users->find((int) $link['user_id']);
            $this->assertUsable($user);
            $this->identities->touchLogin((int) $link['id']);

            return $user;
        }

        // Nessun link: tentativo di aggancio per email verificata.
        $email = trim((string) ($identity['email'] ?? ''));
        if ($email === '') {
            throw new ExternalLoginDeniedException(ExternalLoginDeniedException::EMAIL_MISSING);
        }
        if (($identity['email_verified'] ?? null) !== true) {
            throw new ExternalLoginDeniedException(ExternalLoginDeniedException::EMAIL_UNVERIFIED);
        }

        $user = $this->identities->findUserByEmailCi($email);
        if ($user !== null) {
            $this->assertUsable($user);
            $this->linkIdentity((int) $user['id'], $identity);

            return $user;
        }

        // Nessun account locale: JIT o rifiuto.
        if (!setting('sso_oidc_jit_enabled', false)) {
            throw new ExternalLoginDeniedException(ExternalLoginDeniedException::NO_LOCAL_ACCOUNT);
        }

        $userId = $this->provision($identity, $email);
        $this->linkIdentity($userId, $identity);

        $user = $this->users->find($userId);
        if ($user === null) {
            throw new ExternalLoginDeniedException(ExternalLoginDeniedException::PROVISION_FAILED);
        }

        return $user;
    }

    public function linkIdentity(int $userId, array $identity): void
    {
        $this->identities->create([
            'user_id'       => $userId,
            'provider'      => $identity['provider'],
            'issuer'        => $identity['issuer'],
            'subject'       => $identity['subject'],
            'email_at_link' => $identity['email'] ?? null,
        ]);

        AuditService::log('sso_identity_linked', 'user', $userId, null, [
            'provider' => $identity['provider'],
            'issuer'   => $identity['issuer'],
            'subject'  => substr((string) $identity['subject'], 0, 16) . '…',
        ]);
    }

    /**
     * @param array<string,mixed>|null $user
     */
    private function assertUsable(?array $user): void
    {
        if ($user === null || !empty($user['deleted_at'])) {
            throw new ExternalLoginDeniedException(ExternalLoginDeniedException::USER_DELETED);
        }
        if ((int) ($user['is_active'] ?? 0) !== 1) {
            throw new ExternalLoginDeniedException(ExternalLoginDeniedException::USER_INACTIVE);
        }
    }

    private function provision(array $identity, string $email): int
    {
        $name     = trim((string) ($identity['name'] ?? '')) ?: strstr($email, '@', true);
        $username = $this->deriveUsername($identity, $email);
        $roleSlug = $this->validDefaultRole();

        try {
            $userId = $this->userService->createExternalUser((string) $name, $username, $email, $roleSlug);
        } catch (\Throwable $e) {
            app_log('error', '[ExternalIdentity] Provisioning JIT fallito: ' . $e->getMessage());
            throw new ExternalLoginDeniedException(ExternalLoginDeniedException::PROVISION_FAILED);
        }

        AuditService::log('sso_user_provisioned', 'user', $userId, null, [
            'provider' => $identity['provider'],
            'email'    => $email,
            'role'     => $roleSlug,
        ]);

        return $userId;
    }

    /**
     * preferred_username → altrimenti local-part dell'email; normalizzato a
     * [a-z0-9._-], max 50 char; collisioni risolte con suffisso numerico.
     */
    private function deriveUsername(array $identity, string $email): string
    {
        $base = trim((string) ($identity['preferred_username'] ?? ''));
        if ($base === '') {
            $base = (string) strstr($email, '@', true);
        }
        $base = strtolower($base);
        $base = (string) preg_replace('/[^a-z0-9._-]+/', '', $base);
        $base = substr($base !== '' ? $base : 'utente', 0, 50);

        $candidate = $base;
        $suffix    = 2;
        while ($this->usernameTaken($candidate)) {
            $tail      = (string) $suffix;
            $candidate = substr($base, 0, 50 - strlen($tail)) . $tail;
            $suffix++;
        }

        return $candidate;
    }

    private function usernameTaken(string $username): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Il ruolo JIT configurato deve esistere; 'admin' non è mai un default
     * valido (privilege escalation da configurazione distratta). Fallback: user.
     */
    private function validDefaultRole(): string
    {
        $slug = (string) setting('sso_oidc_jit_default_role', 'user');
        if ($slug === 'admin') {
            return 'user';
        }

        $stmt = $this->pdo->prepare('SELECT 1 FROM roles WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);

        return $stmt->fetchColumn() ? $slug : 'user';
    }
}
