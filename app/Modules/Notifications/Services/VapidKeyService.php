<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Services\SettingsService;
use Base64Url\Base64Url;

/**
 * Chiavi VAPID (RFC 8292) per il canale Web Push, persistite in app_settings
 * come base64url: pubblica = punto EC non compresso (65 byte), privata = d
 * (32 byte). Stessa postura dei token bot Telegram: segreti a riposo nel DB,
 * generazione dal pannello Admin -> Notifiche.
 */
class VapidKeyService
{
    public const SETTING_PUBLIC_KEY = 'webpush_vapid_public_key';
    public const SETTING_PRIVATE_KEY = 'webpush_vapid_private_key';
    public const SETTING_SUBJECT = 'webpush_subject';

    public function isConfigured(): bool
    {
        return $this->publicKey() !== null && $this->privateKey() !== null;
    }

    public function publicKey(): ?string
    {
        $value = trim((string) setting(self::SETTING_PUBLIC_KEY, ''));
        return $value !== '' ? $value : null;
    }

    public function privateKey(): ?string
    {
        $value = trim((string) setting(self::SETTING_PRIVATE_KEY, ''));
        return $value !== '' ? $value : null;
    }

    /**
     * Subject del JWT VAPID: URL o mailto: del gestore dell'installazione.
     */
    public function subject(): string
    {
        $value = trim((string) setting(self::SETTING_SUBJECT, ''));
        if ($value !== '') {
            return $value;
        }
        return rtrim((string) config('app.url', 'https://localhost'), '/');
    }

    /**
     * Genera e salva una nuova coppia VAPID. La rigenerazione invalida tutte
     * le subscription esistenti, quindi senza $force una coppia già presente
     * viene mantenuta.
     *
     * @return array{publicKey: string, generated: bool}
     */
    public function generate(bool $force = false): array
    {
        $existing = $this->publicKey();
        if ($existing !== null && !$force) {
            return ['publicKey' => $existing, 'generated' => false];
        }

        $keypair = OpensslEcKeyFactory::createKeypair();
        $publicKey = Base64Url::encode("\x04" . $keypair['x'] . $keypair['y']);
        $privateKey = Base64Url::encode($keypair['d']);

        SettingsService::set(self::SETTING_PUBLIC_KEY, $publicKey);
        SettingsService::set(self::SETTING_PRIVATE_KEY, $privateKey);
        if (trim((string) setting(self::SETTING_SUBJECT, '')) === '') {
            SettingsService::set(self::SETTING_SUBJECT, $this->subject());
        }

        return ['publicKey' => $publicKey, 'generated' => true];
    }
}
