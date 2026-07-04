<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Eccezione che rappresenta una risposta HTTP di errore (403, 404, 405, 429, …).
 *
 * Sostituisce il pattern `renderErrorPage(...) + exit` sparso nei middleware e nel
 * Router: lanciandola, il flusso di richiesta resta interrompibile ma diventa
 * catturabile (in produzione da Application::handleRequest, nei test direttamente).
 *
 * Opzionalmente trasporta header aggiuntivi (es. `Allow`, `Retry-After`) e un body
 * pre-renderizzato con relativo content-type (es. la risposta JSON del rate limiter).
 */
class HttpException extends ApplicationException
{
    private int $status;

    /** @var array<string,string> */
    private array $headers;

    private ?string $body;

    private ?string $contentType;

    /**
     * @param array<string,string> $headers
     */
    public function __construct(
        int $status,
        array $headers = [],
        ?string $body = null,
        ?string $contentType = null,
        string $technicalMessage = ''
    ) {
        $this->status      = $status;
        $this->headers     = $headers;
        $this->body        = $body;
        $this->contentType = $contentType;

        parent::__construct(
            'Richiesta non consentita.',
            $technicalMessage !== '' ? $technicalMessage : ('HTTP ' . $status),
            $status
        );
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    /**
     * @return array<string,string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function getContentType(): ?string
    {
        return $this->contentType;
    }
}
