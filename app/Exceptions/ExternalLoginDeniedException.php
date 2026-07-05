<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Login esterno (OIDC/LDAP) rifiutato per policy. Il reason è un codice
 * macchina destinato ad audit/log; la UI mostra sempre un messaggio generico
 * per non fare da oracolo sullo stato degli account.
 */
final class ExternalLoginDeniedException extends \RuntimeException
{
    public const USER_INACTIVE    = 'user_inactive';
    public const USER_DELETED     = 'user_deleted';
    public const EMAIL_MISSING    = 'email_missing';
    public const EMAIL_UNVERIFIED = 'email_unverified';
    public const NO_LOCAL_ACCOUNT = 'no_local_account';
    public const PROVISION_FAILED = 'provision_failed';

    public function __construct(private readonly string $reason)
    {
        parent::__construct('External login denied: ' . $reason);
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
