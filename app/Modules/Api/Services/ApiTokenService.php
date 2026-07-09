<?php

declare(strict_types=1);

namespace App\Modules\Api\Services;

use App\Modules\Api\Repositories\PersonalAccessTokenRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use RuntimeException;

/**
 * Ciclo di vita dei Personal Access Token: creazione (token in chiaro mostrato
 * una sola volta), lista, revoca. Il token in chiaro ha prefisso favilla_pat_
 * seguito da 40 caratteri casuali; a riposo si conserva solo lo sha256.
 */
class ApiTokenService
{
    public const TOKEN_PREFIX = 'favilla_pat_';

    private PersonalAccessTokenRepository $tokenRepo;
    private UserRepository $userRepo;

    public function __construct()
    {
        $this->tokenRepo = app(PersonalAccessTokenRepository::class);
        $this->userRepo = app(UserRepository::class);
    }

    /**
     * Crea un token per l'utente. Gli scope richiesti vengono intersecati con i
     * permessi effettivi dell'utente (non si può creare un token più potente di
     * chi lo emette). scopes vuoto/null = token con i permessi pieni dell'utente.
     *
     * @param string[]|null $requestedScopes
     * @return array{id:int, plain_token:string, name:string, scopes:string[]|null, expires_at:?string}
     */
    public function create(int $userId, string $name, ?array $requestedScopes = null, ?string $expiresAt = null): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('Il nome del token è obbligatorio.');
        }
        if (mb_strlen($name) > 120) {
            $name = mb_substr($name, 0, 120);
        }

        $scopes = $this->resolveScopes($userId, $requestedScopes);

        $plain = self::TOKEN_PREFIX . bin2hex(random_bytes(20)); // 40 hex chars
        $hash = hash('sha256', $plain);

        $id = $this->tokenRepo->create([
            'user_id'    => $userId,
            'name'       => $name,
            'token_hash' => $hash,
            'scopes'     => $scopes === null ? null : json_encode(array_values($scopes)),
            'expires_at' => $expiresAt,
        ]);

        AuditService::log('api_token.created', 'personal_access_tokens', $id, null, [
            'name'   => $name,
            'scopes' => $scopes,
        ]);

        return [
            'id'          => $id,
            'plain_token' => $plain,
            'name'        => $name,
            'scopes'      => $scopes,
            'expires_at'  => $expiresAt,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(int $userId): array
    {
        $tokens = $this->tokenRepo->forUser($userId);
        foreach ($tokens as &$token) {
            $token['scopes'] = $token['scopes'] !== null ? json_decode((string) $token['scopes'], true) : null;
        }
        unset($token);
        return $tokens;
    }

    public function revoke(int $tokenId, int $userId): bool
    {
        $token = $this->tokenRepo->findForUser($tokenId, $userId);
        if ($token === null) {
            return false;
        }
        $this->tokenRepo->markRevoked($tokenId);
        AuditService::log('api_token.revoked', 'personal_access_tokens', $tokenId, ['name' => $token['name'] ?? ''], null);
        return true;
    }

    /**
     * Permessi effettivi dell'utente (slug), come li vedrebbe has_permission().
     * Un utente con ruolo admin ottiene l'intero catalogo permessi.
     *
     * @return string[]
     */
    public function availableScopesForUser(int $userId): array
    {
        $user = $this->userRepo->findWithPermissions($userId);
        if ($user === null) {
            return [];
        }
        $roles = array_column($user['roles'] ?? [], 'slug');
        if (in_array('admin', $roles, true)) {
            return $this->allPermissionSlugs();
        }
        /** @var string[] $permissions */
        $permissions = $user['permissions'] ?? [];
        sort($permissions);
        return $permissions;
    }

    /**
     * @param string[]|null $requestedScopes
     * @return string[]|null
     */
    private function resolveScopes(int $userId, ?array $requestedScopes): ?array
    {
        if ($requestedScopes === null || $requestedScopes === []) {
            // Nessuno scope scelto: il token eredita i permessi pieni dell'utente.
            return null;
        }
        $available = $this->availableScopesForUser($userId);
        $granted = array_values(array_intersect($requestedScopes, $available));
        if ($granted === []) {
            throw new RuntimeException('Nessuno degli scope richiesti è concesso all\'utente.');
        }
        return $granted;
    }

    /**
     * @return string[]
     */
    private function allPermissionSlugs(): array
    {
        $pdo = app(\PDO::class);
        $rows = $pdo->query('SELECT slug FROM permissions ORDER BY slug')->fetchAll(\PDO::FETCH_COLUMN);
        return array_map('strval', $rows);
    }
}
