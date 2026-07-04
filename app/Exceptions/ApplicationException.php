<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Eccezione applicativa base.
 *
 * Separa il messaggio utente (safe, generico) dal messaggio tecnico (per i log).
 * I controller possono catturare ApplicationException e mostrare getUserMessage()
 * senza rischio di leak di dettagli interni.
 */
class ApplicationException extends \RuntimeException
{
    private string $userMessage;

    public function __construct(
        string $userMessage,
        string $technicalMessage = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $this->userMessage = $userMessage;
        parent::__construct($technicalMessage ?: $userMessage, $code, $previous);
    }

    /**
     * Messaggio sicuro da mostrare all'utente finale.
     */
    public function getUserMessage(): string
    {
        return $this->userMessage;
    }
}
