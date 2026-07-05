<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Collegamenti tra utenti locali e identità esterne (OIDC oggi, LDAP domani).
 * SQL volutamente dual-dialect (MariaDB + SQLite dei test): niente
 * INSERT IGNORE / ON DUPLICATE KEY nel codice repository.
 */
class ExternalIdentityRepository extends BaseRepository
{
    protected string $table = 'oidc_identities';
    protected array $fillable = [
        'user_id', 'provider', 'issuer', 'subject', 'email_at_link', 'last_login_at',
    ];
    protected bool $timestamps = true;

    /**
     * @return array<string,mixed>|null
     */
    public function findBySubject(string $provider, string $issuer, string $subject): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM oidc_identities WHERE provider = ? AND issuer = ? AND subject = ? LIMIT 1'
        );
        $stmt->execute([$provider, $issuer, $subject]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Utente locale attivo o meno, per email case-insensitive (LOWER funziona
     * su entrambi i dialetti; su MariaDB la collation _ci lo rende ridondante
     * ma innocuo). I soft-deleted sono esclusi.
     *
     * @return array<string,mixed>|null
     */
    public function findUserByEmailCi(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM users WHERE LOWER(email) = LOWER(?) AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function touchLogin(int $identityId): void
    {
        $stmt = $this->pdo->prepare('UPDATE oidc_identities SET last_login_at = NOW() WHERE id = ?');
        $stmt->execute([$identityId]);
    }
}
