<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Eccezione che rappresenta un reindirizzamento HTTP (302 classico o HX-Redirect
 * per le richieste HTMX).
 *
 * Sostituisce le chiamate `header('Location: …') + exit` di AuthMiddleware: il
 * redirect resta un'uscita anticipata dal flusso, ma diventa catturabile e quindi
 * testabile (in produzione lo gestisce Application::handleRequest).
 */
class HttpRedirectException extends ApplicationException
{
    private string $url;
    private bool $htmx;

    public function __construct(string $url, bool $htmx = false)
    {
        $this->url  = $url;
        $this->htmx = $htmx;

        parent::__construct('Reindirizzamento.', 'HTTP redirect → ' . $url, $htmx ? 200 : 302);
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function isHtmx(): bool
    {
        return $this->htmx;
    }

    public function getStatusCode(): int
    {
        return $this->htmx ? 200 : 302;
    }
}
