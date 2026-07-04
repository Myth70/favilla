<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Services;

/**
 * Registry dei tipi MIME accettati per i documenti.
 * Override runtime via app_settings chiave 'documenti.mime.disabled' (JSON array).
 */
class DocumentiMimeRegistry
{
    public const MIMES = [
        // Documenti
        'application/pdf'                                                       => 'pdf',
        'application/msword'                                                    => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel'                                              => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'    => 'xlsx',
        'application/vnd.ms-powerpoint'                                         => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.oasis.opendocument.text'                               => 'odt',
        'application/vnd.oasis.opendocument.spreadsheet'                        => 'ods',
        'application/vnd.oasis.opendocument.presentation'                       => 'odp',
        // Testo
        'text/plain'                                                            => 'txt',
        'text/csv'                                                              => 'csv',
        // Immagini
        'image/jpeg'                                                            => 'jpg',
        'image/png'                                                             => 'png',
        'image/gif'                                                             => 'gif',
        'image/webp'                                                            => 'webp',
        'image/tiff'                                                            => 'tiff',
        'image/svg+xml'                                                         => 'svg',
        // CAD / Tecnico
        'application/dxf'                                                       => 'dxf',
        'image/vnd.dwg'                                                         => 'dwg',
        'application/postscript'                                                => 'eps',
        // Archivi
        'application/zip'                                                       => 'zip',
        'application/x-zip-compressed'                                          => 'zip',
    ];

    /**
     * Restituisce i MIME attivi (meno quelli disabilitati via app_settings).
     */
    public static function activeMimes(): array
    {
        $disabled = self::disabledMimes();
        if (empty($disabled)) {
            return self::MIMES;
        }
        return array_filter(self::MIMES, fn ($mime) => !in_array($mime, $disabled, true), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Produce l'attributo HTML accept="" per <input type="file">.
     */
    public static function acceptAttr(): string
    {
        return implode(',', array_keys(self::activeMimes()));
    }

    /**
     * Legge i MIME disabilitati da app_settings.
     */
    public static function disabledMimes(): array
    {
        try {
            $pdo  = app(\PDO::class);
            $stmt = $pdo->prepare(
                "SELECT `value` FROM app_settings WHERE `key` = 'documenti.mime.disabled' LIMIT 1"
            );
            $stmt->execute();
            $val = $stmt->fetchColumn();
            if ($val) {
                $arr = json_decode($val, true);
                return is_array($arr) ? $arr : [];
            }
        } catch (\Throwable) {
            // app_settings potrebbe non esistere in test
        }
        return [];
    }

    /**
     * Abilita/disabilita un singolo MIME.
     *
     * Invariante di sicurezza: questo metodo NON può ABILITARE un MIME non presente
     * nella const {@see self::MIMES}. L'admin può solo restringere ulteriormente la
     * whitelist statica, MAI estenderla. Un MIME sconosciuto può finire nella lista
     * "disabled" ma non avrà alcun effetto perché activeMimes() filtra solo le entry
     * della const. Quindi non serve un'altra blacklist intrinseca.
     *
     * @return bool true se ora attivo, false se ora disabilitato.
     */
    public static function toggleMime(string $mime): bool
    {
        $disabled = self::disabledMimes();
        if (in_array($mime, $disabled, true)) {
            $disabled = array_values(array_filter($disabled, fn ($m) => $m !== $mime));
            $active   = true;
        } else {
            $disabled[] = $mime;
            $active     = false;
        }

        try {
            $pdo = app(\PDO::class);
            $now = date('Y-m-d H:i:s');
            $exists = $pdo->prepare("SELECT 1 FROM app_settings WHERE `key` = 'documenti.mime.disabled' LIMIT 1");
            $exists->execute();
            if ($exists->fetchColumn()) {
                $pdo->prepare(
                    "UPDATE app_settings SET `value` = ?, updated_at = ? WHERE `key` = 'documenti.mime.disabled'"
                )->execute([json_encode($disabled), $now]);
            } else {
                $pdo->prepare(
                    "INSERT INTO app_settings (`key`, `value`, `type`, `group`, updated_at)
                     VALUES ('documenti.mime.disabled', ?, 'json', 'documenti', ?)"
                )->execute([json_encode($disabled), $now]);
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException(t('documenti.exception.impostazioni_mime_non_aggiornate', ['error' => $e->getMessage()]));
        }

        return $active;
    }
}
