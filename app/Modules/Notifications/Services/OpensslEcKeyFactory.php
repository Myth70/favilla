<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use RuntimeException;

/**
 * Genera coppie di chiavi EC P-256 via OpenSSL.
 *
 * Su Windows (XAMPP) openssl_pkey_new() fallisce senza un openssl.cnf
 * raggiungibile: il default compilato non esiste e OPENSSL_CONF impostata a
 * runtime viene ignorata. L'unico rimedio affidabile è l'argomento 'config'
 * esplicito, quindi la factory prova prima la chiamata "bare" (Linux/macOS)
 * e poi i percorsi cnf noti, memorizzando il primo che funziona.
 * open_basedir può vietare il probe con is_file() fuori da htdocs: per questo
 * si tenta direttamente il keygen invece di verificare l'esistenza del file.
 */
final class OpensslEcKeyFactory
{
    /** @var array<string, string>|null Argomenti extra vincenti ('config' => path), [] = bare. */
    private static ?array $workingExtraArgs = null;

    /**
     * Crea una coppia EC P-256 e restituisce le coordinate grezze paddate a 32 byte.
     *
     * @return array{x: string, y: string, d: string}
     */
    public static function createKeypair(): array
    {
        $key = self::createKey();

        $details = openssl_pkey_get_details($key);
        if ($details === false || !isset($details['ec']['x'], $details['ec']['y'], $details['ec']['d'])) {
            throw new RuntimeException('OpenSSL: impossibile leggere i dettagli della chiave EC generata.');
        }

        $pad = static fn (string $v): string => str_pad($v, 32, "\0", STR_PAD_LEFT);

        return [
            'x' => $pad((string) $details['ec']['x']),
            'y' => $pad((string) $details['ec']['y']),
            'd' => $pad((string) $details['ec']['d']),
        ];
    }

    private static function createKey(): \OpenSSLAsymmetricKey
    {
        $base = [
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ];

        if (self::$workingExtraArgs !== null) {
            $key = openssl_pkey_new($base + self::$workingExtraArgs);
            if ($key !== false) {
                return $key;
            }
            self::$workingExtraArgs = null; // l'ambiente è cambiato: riprova da capo
        }

        foreach (self::candidateExtraArgs() as $extra) {
            $key = openssl_pkey_new($base + $extra);
            if ($key !== false) {
                self::$workingExtraArgs = $extra;
                return $key;
            }
        }

        throw new RuntimeException(
            'OpenSSL: generazione chiave EC fallita. Nessuna configurazione openssl.cnf utilizzabile '
            . '(provati: default di sistema, php/extras, apache/conf, /etc/ssl). '
            . 'Impostare la variabile d\'ambiente OPENSSL_CONF prima dell\'avvio di PHP.'
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function candidateExtraArgs(): array
    {
        $phpDir = dirname(PHP_BINARY); // su XAMPP PHP_BINDIR mente, PHP_BINARY no

        return [
            [], // ambienti sani (Linux/macOS/Docker): nessun override necessario
            ['config' => $phpDir . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'openssl' . DIRECTORY_SEPARATOR . 'openssl.cnf'],
            ['config' => dirname($phpDir) . DIRECTORY_SEPARATOR . 'apache' . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'openssl.cnf'],
            ['config' => '/etc/ssl/openssl.cnf'],
        ];
    }
}
