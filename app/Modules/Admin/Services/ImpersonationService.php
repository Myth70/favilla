<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\SettingsService;

/**
 * ImpersonationService — orchestra l'impersonazione admin → user.
 *
 * Il Service non legge $_SESSION direttamente: ogni metodo riceve in input lo stato
 * di sessione necessario e ritorna un payload puro (sessione + cookie) che il
 * Controller applica alla sessione corrente.
 */
class ImpersonationService
{
    private UserRepository $userRepo;

    public function __construct()
    {
        $this->userRepo = app(UserRepository::class);
    }

    /**
     * Verifica se l'admin puo' impersonare l'utente target.
     *
     * @param array $session  stato di sessione corrente (per il check "stai già impersonando")
     * @return array{ok: bool, error: ?string}
     */
    public function canImpersonate(int $adminId, int $targetId, array $session = []): array
    {
        if ($adminId === $targetId) {
            return ['ok' => false, 'error' => 'Non puoi impersonare te stesso.'];
        }

        if (self::sessionIsImpersonating($session)) {
            return ['ok' => false, 'error' => 'Sei già in una sessione di impersonazione. Torna al tuo account prima di impersonarne un altro.'];
        }

        $target = $this->userRepo->findWithPermissions($targetId);
        if (!$target) {
            return ['ok' => false, 'error' => 'Utente non trovato.'];
        }

        if (empty($target['is_active'])) {
            return ['ok' => false, 'error' => 'Non puoi impersonare un utente disattivato.'];
        }

        // Non impersonare utenti con ruolo admin
        $targetRoleSlugs = array_column($target['roles'] ?? [], 'slug');
        if (in_array('admin', $targetRoleSlugs, true)) {
            return ['ok' => false, 'error' => 'Non puoi impersonare un amministratore.'];
        }

        return ['ok' => true, 'error' => null];
    }

    /**
     * Build the session and cookie payload for starting an impersonation.
     *
     * Il caller deve:
     *   1. fare un backup completo di $_SESSION (passato come $adminSessionBackup)
     *   2. applicare $result['session_payload'] a $_SESSION
     *   3. emettere il cookie usando $result['cookie']
     *
     * @param array<string,mixed> $adminSessionBackup  copia integrale di $_SESSION dell'admin
     * @return array{
     *     session_payload: array<string,mixed>,
     *     cookie: array{name:string,value:string,options:array<string,mixed>}
     * }
     */
    public function start(int $adminId, int $targetId, array $adminSessionBackup): array
    {
        $target = $this->userRepo->findWithPermissions($targetId);
        if (!$target) {
            throw new \RuntimeException('Utente non trovato');
        }

        $timeout = (int) SettingsService::get('impersonation_timeout', 30);
        $expiresAt = time() + ($timeout * 60);

        $sessionPayload = [
            'user_id'                    => $target['id'],
            'user_name'                  => $target['name'],
            'user_email'                 => $target['email'],
            'user_username'              => $target['username'],
            'user_roles'                 => array_column($target['roles'] ?? [], 'slug'),
            'user_permissions'           => $target['permissions'] ?? [],
            'must_change_password'       => false,
            'user_avatar'                => $target['avatar_path'] ?? null,
            '_impersonator_id'           => $adminId,
            '_impersonator_name'         => $adminSessionBackup['user_name'] ?? 'Admin',
            '_impersonator_data'         => $adminSessionBackup,
            '_impersonation_started_at'  => time(),
            '_impersonation_expires_at'  => $expiresAt,
        ];

        $cookieValue = (string) json_encode([
            'name'      => $target['name'],
            'revertUrl' => route('admin.impersonate.revert'),
            'expiresAt' => $expiresAt,
        ]);

        $basePath = env('APP_BASE_PATH', '') ?: '/';
        $secure = \App\Support\RequestContext::isSecure();

        AuditService::log(
            'impersonation_started',
            'user',
            $targetId,
            null,
            ['admin_id' => $adminId, 'target_name' => $target['name']]
        );

        return [
            'session_payload' => $sessionPayload,
            'cookie'          => [
                'name'    => 'favilla_impersonating',
                'value'   => $cookieValue,
                'options' => [
                    'expires'  => 0,
                    'path'     => $basePath,
                    'domain'   => '',
                    'secure'   => $secure,
                    'httponly' => false, // letto da JS per mostrare il banner di impersonazione
                    'samesite' => 'Strict',
                ],
            ],
        ];
    }

    /**
     * Build the payload to revert an impersonation.
     *
     * Il caller deve:
     *   1. azzerare $_SESSION
     *   2. ripopolarla con $result['session_replace']
     *   3. azzerare il cookie usando $result['cookie']
     *
     * Ritorna null se la sessione passata non è in stato di impersonazione.
     *
     * @return array{
     *     session_replace: array<string,mixed>,
     *     cookie: array{name:string,value:string,options:array<string,mixed>}
     * }|null
     */
    public function revert(array $currentSession): ?array
    {
        if (!self::sessionIsImpersonating($currentSession)) {
            return null;
        }

        $impersonatedUserId = $currentSession['user_id'] ?? null;
        $adminId            = (int) ($currentSession['_impersonator_id'] ?? 0);
        $backup             = (array) ($currentSession['_impersonator_data'] ?? []);

        $basePath = env('APP_BASE_PATH', '') ?: '/';
        $secure = \App\Support\RequestContext::isSecure();

        AuditService::log(
            'impersonation_ended',
            'user',
            $impersonatedUserId,
            null,
            ['admin_id' => $adminId]
        );

        return [
            'session_replace' => $backup,
            'cookie'          => [
                'name'    => 'favilla_impersonating',
                'value'   => '',
                'options' => [
                    'expires'  => time() - 3600,
                    'path'     => $basePath,
                    'domain'   => '',
                    'secure'   => $secure,
                    'httponly' => false,
                    'samesite' => 'Strict',
                ],
            ],
        ];
    }

    /**
     * Pure check over a session-shaped array: is impersonation currently active?
     *
     * @param array<string,mixed> $session
     */
    public static function sessionIsImpersonating(array $session): bool
    {
        return !empty($session['_impersonator_id']);
    }

    /**
     * Pure check over a session-shaped array: is the impersonation expired?
     *
     * @param array<string,mixed> $session
     */
    public static function sessionIsExpired(array $session): bool
    {
        if (!self::sessionIsImpersonating($session)) {
            return false;
        }
        $expiresAt = (int) ($session['_impersonation_expires_at'] ?? 0);
        return time() > $expiresAt;
    }

    /**
     * @param array<string,mixed> $session
     */
    public static function sessionImpersonatorName(array $session): ?string
    {
        return isset($session['_impersonator_name']) ? (string) $session['_impersonator_name'] : null;
    }

    // ── Legacy shim methods kept for view-layer compatibility ────────────────

    public function isImpersonating(): bool
    {
        return self::sessionIsImpersonating($_SESSION);
    }

    public function isExpired(): bool
    {
        return self::sessionIsExpired($_SESSION);
    }

    public function getImpersonatorName(): ?string
    {
        return self::sessionImpersonatorName($_SESSION);
    }
}
