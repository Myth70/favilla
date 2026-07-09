<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use Base64Url\Base64Url;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;
use Jose\Component\Core\JWK;
use Minishlink\WebPush\ContentEncoding;
use Minishlink\WebPush\Encryption;
use Minishlink\WebPush\VAPID;

/**
 * Consegna Web Push (RFC 8030/8291/8292) orchestrando i primitivi pubblici di
 * minishlink/web-push: Encryption::deterministicEncrypt + getContentCodingHeader
 * e VAPID::getVapidHeaders. Non si usa la classe WebPush della libreria perché
 * la sua generazione della chiave effimera (openssl_pkey_new senza 'config')
 * fallisce su Windows/XAMPP: la chiave effimera arriva da OpensslEcKeyFactory.
 *
 * Unico punto del modulo che tocca rete e crittografia: nei test si mocka
 * l'intera classe via container.
 */
class WebPushSender
{
    private const REQUEST_TIMEOUT = 15;
    private const TTL = 2419200; // 4 settimane, default del protocollo

    private ?ClientInterface $http = null;

    /** @var array<string, array<string, string>> Cache header VAPID per audience+encoding (validi 12h, riusabili nel batch). */
    private array $vapidHeaderCache = [];

    /**
     * Inietta un client HTTP alternativo (usato dai test per mockare la rete).
     * In produzione il client Guzzle viene creato lazy alla prima consegna:
     * il costruttore resta senza dipendenze così il container non deve
     * autowire GuzzleHttp\ClientInterface.
     */
    public function setHttpClient(ClientInterface $http): void
    {
        $this->http = $http;
    }

    private function http(): ClientInterface
    {
        if ($this->http === null) {
            $this->http = new Client([
                'timeout'         => self::REQUEST_TIMEOUT,
                'connect_timeout' => self::REQUEST_TIMEOUT,
            ]);
        }
        return $this->http;
    }

    /**
     * Invia lo stesso payload a un insieme di subscription.
     *
     * @param array<int, array{id: int, endpoint: string, endpoint_hash: string, p256dh: string, auth: string, content_encoding: string}> $subscriptions
     * @param string $payloadJson corpo della notifica (JSON, già limitato in lunghezza dal driver)
     * @param string $vapidPublicKey base64url (65 byte decodificati)
     * @param string $vapidPrivateKey base64url (32 byte decodificati)
     * @return array<string, array{success: bool, status: int|null, expired: bool, error: string|null}> esiti per endpoint_hash
     */
    public function send(
        array $subscriptions,
        string $payloadJson,
        string $vapidPublicKey,
        #[\SensitiveParameter]
        string $vapidPrivateKey,
        string $subject
    ): array {
        $results = [];

        foreach ($subscriptions as $subscription) {
            $hash = (string) $subscription['endpoint_hash'];
            try {
                $results[$hash] = $this->sendOne($subscription, $payloadJson, $vapidPublicKey, $vapidPrivateKey, $subject);
            } catch (\Throwable $e) {
                $results[$hash] = [
                    'success' => false,
                    'status'  => null,
                    'expired' => false,
                    'error'   => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * @param array{id: int, endpoint: string, endpoint_hash: string, p256dh: string, auth: string, content_encoding: string} $subscription
     * @return array{success: bool, status: int|null, expired: bool, error: string|null}
     */
    private function sendOne(
        array $subscription,
        string $payloadJson,
        string $vapidPublicKey,
        #[\SensitiveParameter]
        string $vapidPrivateKey,
        string $subject
    ): array {
        $endpoint = (string) $subscription['endpoint'];
        $encoding = ContentEncoding::tryFrom((string) ($subscription['content_encoding'] ?: 'aes128gcm'));
        if ($encoding === null) {
            return ['success' => false, 'status' => null, 'expired' => false, 'error' => 'Content encoding non supportato: ' . $subscription['content_encoding']];
        }

        $padded = Encryption::padPayload($payloadJson, Encryption::MAX_COMPATIBILITY_PAYLOAD_LENGTH, $encoding);
        $encrypted = Encryption::deterministicEncrypt(
            $padded,
            (string) $subscription['p256dh'],
            (string) $subscription['auth'],
            $encoding,
            [$this->createEphemeralJwk()],
            random_bytes(16)
        );

        $headers = [
            'Content-Type'     => 'application/octet-stream',
            'Content-Encoding' => $encoding->value,
            'TTL'              => (string) self::TTL,
        ];

        if ($encoding === ContentEncoding::aesgcm) {
            $body = $encrypted['cipherText'];
            $headers['Encryption'] = 'salt=' . Base64Url::encode($encrypted['salt']);
            $headers['Crypto-Key'] = 'dh=' . Base64Url::encode($encrypted['localPublicKey']);
        } else {
            $body = Encryption::getContentCodingHeader($encrypted['salt'], $encrypted['localPublicKey'], $encoding)
                . $encrypted['cipherText'];
        }

        $vapidHeaders = $this->vapidHeadersFor($endpoint, $encoding, $vapidPublicKey, $vapidPrivateKey, $subject);
        $headers['Authorization'] = $vapidHeaders['Authorization'];
        if ($encoding === ContentEncoding::aesgcm && isset($vapidHeaders['Crypto-Key'])) {
            $headers['Crypto-Key'] .= ';' . $vapidHeaders['Crypto-Key'];
        }

        try {
            $response = $this->http()->request('POST', $endpoint, [
                'headers'     => $headers,
                'body'        => $body,
                'http_errors' => false,
            ]);
        } catch (TransferException $e) {
            return ['success' => false, 'status' => null, 'expired' => false, 'error' => $e->getMessage()];
        }

        $status = $response->getStatusCode();

        return [
            'success' => $status >= 200 && $status < 300,
            'status'  => $status,
            'expired' => in_array($status, [404, 410], true),
            'error'   => $status >= 300 ? ('Push service HTTP ' . $status) : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function vapidHeadersFor(
        string $endpoint,
        ContentEncoding $encoding,
        string $vapidPublicKey,
        #[\SensitiveParameter]
        string $vapidPrivateKey,
        string $subject
    ): array {
        $audience = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
        $cacheKey = $audience . '|' . $encoding->value;

        if (!isset($this->vapidHeaderCache[$cacheKey])) {
            $this->vapidHeaderCache[$cacheKey] = VAPID::getVapidHeaders(
                $audience,
                $subject,
                Base64Url::decode($vapidPublicKey),
                Base64Url::decode($vapidPrivateKey),
                $encoding
            );
        }

        return $this->vapidHeaderCache[$cacheKey];
    }

    /**
     * Chiave effimera per la cifratura del singolo messaggio, nel formato JWK
     * accettato da Encryption::deterministicEncrypt (branch count === 1).
     */
    private function createEphemeralJwk(): JWK
    {
        $keypair = OpensslEcKeyFactory::createKeypair();

        return new JWK([
            'kty' => 'EC',
            'crv' => 'P-256',
            'x'   => Base64Url::encode($keypair['x']),
            'y'   => Base64Url::encode($keypair['y']),
            'd'   => Base64Url::encode($keypair['d']),
        ]);
    }
}
