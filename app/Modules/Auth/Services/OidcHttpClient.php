<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

/**
 * HTTP client minimale per le chiamate OIDC (discovery, JWKS, token,
 * userinfo). Pattern WeatherService: curl con timeout stretti, verifica TLS
 * esplicita, nessuna eccezione verso l'alto — i fallimenti tornano null/status
 * e vengono loggati dal chiamante.
 */
class OidcHttpClient
{
    private const TIMEOUT = 5;

    /**
     * @param list<string> $headers header extra "Nome: valore"
     * @return array<mixed>|null JSON decodificato, null su errore/status non-2xx
     */
    public function getJson(string $url, array $headers = []): ?array
    {
        $result = $this->request('GET', $url, null, $headers);

        return ($result['status'] >= 200 && $result['status'] < 300) ? $result['body'] : null;
    }

    /**
     * POST application/x-www-form-urlencoded (token endpoint).
     *
     * @param array<string,string> $fields
     * @param array{0:string,1:string}|null $basicAuth [client_id, client_secret] per client_secret_basic
     * @return array{status:int, body:array<mixed>|null}
     */
    public function postForm(string $url, array $fields, ?array $basicAuth = null): array
    {
        $headers = ['Content-Type: application/x-www-form-urlencoded'];
        if ($basicAuth !== null) {
            // RFC 6749 §2.3.1: credenziali urlencoded dentro il Basic header
            $headers[] = 'Authorization: Basic ' . base64_encode(
                rawurlencode($basicAuth[0]) . ':' . rawurlencode($basicAuth[1])
            );
        }

        return $this->request('POST', $url, http_build_query($fields), $headers);
    }

    /**
     * @param list<string>|array<string,string> $headers
     * @return array{status:int, body:array<mixed>|null}
     */
    private function request(string $method, string $url, ?string $payload, array $headers): array
    {
        if (!function_exists('curl_init')) {
            app_log('error', '[OidcHttp] estensione curl non disponibile');

            return ['status' => 0, 'body' => null];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 0, 'body' => null];
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
            CURLOPT_USERAGENT      => 'Favilla-OIDC',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => array_values($headers),
        ];
        if ($method === 'POST') {
            $options[CURLOPT_POST]       = true;
            $options[CURLOPT_POSTFIELDS] = $payload ?? '';
        }
        curl_setopt_array($ch, $options);

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            app_log('error', '[OidcHttp] ' . $method . ' ' . $url . ' fallita: ' . $error);

            return ['status' => 0, 'body' => null];
        }

        $decoded = json_decode((string) $raw, true);

        return ['status' => $status, 'body' => is_array($decoded) ? $decoded : null];
    }
}
