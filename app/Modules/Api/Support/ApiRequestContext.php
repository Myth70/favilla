<?php

declare(strict_types=1);

namespace App\Modules\Api\Support;

/**
 * Contesto della richiesta API autenticata (request-scoped).
 *
 * Popolato da ApiTokenMiddleware dopo la validazione del Bearer token e letto
 * dai controller/ApiController: rimpiazza la sessione PHP, che l'API non usa.
 * Risolto come singleton dal container (una istanza per richiesta), così i test
 * possono iniettarne una pre-popolata.
 */
final class ApiRequestContext
{
    private ?int $userId = null;

    /** @var array<string, mixed>|null Riga utente (id, name, email, …). */
    private ?array $user = null;

    /** @var string[] Ruoli dell'utente (slug). */
    private array $roles = [];

    /** @var string[] Permessi dell'utente (slug). */
    private array $permissions = [];

    /** @var string[]|null Scope del token: null = nessun limite (tutti i permessi utente). */
    private ?array $scopes = null;

    private ?int $tokenId = null;

    /**
     * @param array<string, mixed> $user
     * @param string[]             $roles
     * @param string[]             $permissions
     * @param string[]|null        $scopes
     */
    public function authenticate(int $userId, array $user, array $roles, array $permissions, ?array $scopes, int $tokenId): void
    {
        $this->userId = $userId;
        $this->user = $user;
        $this->roles = $roles;
        $this->permissions = $permissions;
        $this->scopes = $scopes;
        $this->tokenId = $tokenId;
    }

    public function isAuthenticated(): bool
    {
        return $this->userId !== null;
    }

    public function userId(): int
    {
        return (int) $this->userId;
    }

    /**
     * @return array<string, mixed>
     */
    public function user(): array
    {
        return $this->user ?? [];
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return $this->roles;
    }

    public function tokenId(): ?int
    {
        return $this->tokenId;
    }

    /**
     * Gate effettivo: l'utente possiede il permesso (o è admin) E — se il token
     * dichiara scope — il permesso rientra tra gli scope. min(permessi, scope).
     */
    public function can(string $permission): bool
    {
        $userHas = in_array('admin', $this->roles, true)
            || in_array($permission, $this->permissions, true);
        if (!$userHas) {
            return false;
        }

        // scopes null = token senza restrizioni: eredita i permessi utente.
        return $this->scopes === null || in_array($permission, $this->scopes, true);
    }

    /**
     * @return string[]|null
     */
    public function scopes(): ?array
    {
        return $this->scopes;
    }
}
