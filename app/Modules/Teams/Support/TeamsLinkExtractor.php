<?php

declare(strict_types=1);

namespace App\Modules\Teams\Support;

/**
 * Estrae gli URL dal body di un messaggio Teams.
 *
 * La stessa regex è usata da MarkdownRenderer per l'auto-link inline:
 * tenerla qui evita duplicazione e garantisce coerenza tra rendering
 * dei messaggi e il tab "Link" dell'offcanvas di gruppo.
 */
class TeamsLinkExtractor
{
    public const URL_PATTERN = '#(?<![">\w])((?:https?://|www\.)[^\s<]+)#i';

    /**
     * Punteggiatura di fine frase rimossa dal trailing dell'URL.
     * Es. "vedi http://example.com." → "http://example.com".
     */
    private const TRAIL_CHARS = ['.', ',', ';', ')', ']', '!', '?'];

    /**
     * Restituisce tutti gli URL trovati in $body, nell'ordine di apparizione,
     * deduplicati preservando il primo occorrenza. Ogni URL è normalizzato
     * con schema (www. → https://www.) e senza trailing punctuation.
     *
     * @return string[]
     */
    public static function extract(string $body): array
    {
        if ($body === '' || (strpos($body, 'http') === false && strpos($body, 'www.') === false)) {
            return [];
        }

        $matches = [];
        if (preg_match_all(self::URL_PATTERN, $body, $matches) === false) {
            return [];
        }

        $out  = [];
        $seen = [];
        foreach ($matches[1] as $raw) {
            $clean = self::trimTrailingPunct((string) $raw);
            if ($clean === '') {
                continue;
            }
            $normalized = str_starts_with($clean, 'www.') ? 'https://' . $clean : $clean;
            if (isset($seen[$normalized])) {
                continue;
            }
            $seen[$normalized] = true;
            $out[] = $normalized;
        }
        return $out;
    }

    /**
     * Estrae il dominio di un URL (host senza www., minuscolo).
     * Restituisce stringa vuota se l'URL è malformato.
     */
    public static function domain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return '';
        }
        $host = strtolower($host);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }
        return $host;
    }

    private static function trimTrailingPunct(string $url): string
    {
        while ($url !== '' && in_array(substr($url, -1), self::TRAIL_CHARS, true)) {
            $url = substr($url, 0, -1);
        }
        return $url;
    }
}
