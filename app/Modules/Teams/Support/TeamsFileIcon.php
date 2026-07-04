<?php

declare(strict_types=1);

namespace App\Modules\Teams\Support;

/**
 * Mappa MIME / estensione → icona Font Awesome + categoria del file.
 *
 * Stessa convenzione del rendering chip lato JS in public/assets/js/teams.js
 * (Teams.renderAttachmentChips). Centralizzata qui per riusare la stessa
 * mappa nel tab "File" dell'offcanvas gruppo (lato server).
 */
class TeamsFileIcon
{
    public const KIND_IMAGE   = 'image';
    public const KIND_VIDEO   = 'video';
    public const KIND_AUDIO   = 'audio';
    public const KIND_DOC     = 'doc';
    public const KIND_ARCHIVE = 'archive';
    public const KIND_OTHER   = 'other';

    /** Estensioni in lowercase → classe icona Font Awesome (senza prefisso). */
    private const EXT_ICON = [
        'pdf'  => 'fa-file-pdf',
        'doc'  => 'fa-file-word',  'docx' => 'fa-file-word',  'odt' => 'fa-file-word',
        'xls'  => 'fa-file-excel', 'xlsx' => 'fa-file-excel', 'ods' => 'fa-file-excel', 'csv' => 'fa-file-excel',
        'ppt'  => 'fa-file-powerpoint', 'pptx' => 'fa-file-powerpoint', 'odp' => 'fa-file-powerpoint',
        'zip'  => 'fa-file-zipper', 'rar'  => 'fa-file-zipper',
        '7z'   => 'fa-file-zipper', 'tar' => 'fa-file-zipper', 'gz'  => 'fa-file-zipper',
        'mp3'  => 'fa-file-audio',  'wav' => 'fa-file-audio',
        'ogg'  => 'fa-file-audio',  'm4a' => 'fa-file-audio',  'flac' => 'fa-file-audio',
        'mp4'  => 'fa-file-video',  'mov' => 'fa-file-video',
        'avi'  => 'fa-file-video',  'mkv' => 'fa-file-video',  'webm' => 'fa-file-video',
        'png'  => 'fa-file-image',  'jpg' => 'fa-file-image',  'jpeg' => 'fa-file-image',
        'gif'  => 'fa-file-image',  'webp' => 'fa-file-image', 'bmp'  => 'fa-file-image', 'svg' => 'fa-file-image',
        'txt'  => 'fa-file-lines',
    ];

    /** Estensioni → categoria (`kind`). */
    private const EXT_KIND = [
        'png' => self::KIND_IMAGE, 'jpg' => self::KIND_IMAGE, 'jpeg' => self::KIND_IMAGE,
        'gif' => self::KIND_IMAGE, 'webp' => self::KIND_IMAGE, 'bmp' => self::KIND_IMAGE, 'svg' => self::KIND_IMAGE,
        'mp4' => self::KIND_VIDEO, 'mov' => self::KIND_VIDEO,
        'avi' => self::KIND_VIDEO, 'mkv' => self::KIND_VIDEO, 'webm' => self::KIND_VIDEO,
        'mp3' => self::KIND_AUDIO, 'wav' => self::KIND_AUDIO,
        'ogg' => self::KIND_AUDIO, 'm4a' => self::KIND_AUDIO, 'flac' => self::KIND_AUDIO,
        'pdf' => self::KIND_DOC,   'doc' => self::KIND_DOC,  'docx' => self::KIND_DOC, 'odt' => self::KIND_DOC,
        'xls' => self::KIND_DOC,   'xlsx' => self::KIND_DOC, 'ods' => self::KIND_DOC,  'csv' => self::KIND_DOC,
        'ppt' => self::KIND_DOC,   'pptx' => self::KIND_DOC, 'odp' => self::KIND_DOC,
        'txt' => self::KIND_DOC,
        'zip' => self::KIND_ARCHIVE, 'rar' => self::KIND_ARCHIVE,
        '7z'  => self::KIND_ARCHIVE, 'tar' => self::KIND_ARCHIVE, 'gz' => self::KIND_ARCHIVE,
    ];

    /**
     * Classe icona Font Awesome (es. "fa-file-pdf") in base a MIME ed estensione.
     * Priorità: estensione lookup → prefisso MIME → fallback "fa-file".
     */
    public static function iconClass(string $mime, string $extension): string
    {
        $ext = strtolower(ltrim($extension, '.'));
        if (isset(self::EXT_ICON[$ext])) {
            return self::EXT_ICON[$ext];
        }

        $mime = strtolower($mime);
        if (str_starts_with($mime, 'image/')) {
            return 'fa-file-image';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'fa-file-video';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'fa-file-audio';
        }
        if (str_starts_with($mime, 'text/')) {
            return 'fa-file-lines';
        }
        return 'fa-file';
    }

    /**
     * Categoria del file. Usata per i filtri pills del tab File
     * (documenti / archivi / audio / tutti) ed eventuale routing.
     */
    public static function kindOf(string $mime, string $extension): string
    {
        $ext = strtolower(ltrim($extension, '.'));
        if (isset(self::EXT_KIND[$ext])) {
            return self::EXT_KIND[$ext];
        }

        $mime = strtolower($mime);
        if (str_starts_with($mime, 'image/')) {
            return self::KIND_IMAGE;
        }
        if (str_starts_with($mime, 'video/')) {
            return self::KIND_VIDEO;
        }
        if (str_starts_with($mime, 'audio/')) {
            return self::KIND_AUDIO;
        }
        if (str_starts_with($mime, 'application/pdf')) {
            return self::KIND_DOC;
        }
        if (str_starts_with($mime, 'application/zip')) {
            return self::KIND_ARCHIVE;
        }
        return self::KIND_OTHER;
    }

    /**
     * Etichetta italiana per le pills filtro nel tab File.
     */
    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            self::KIND_DOC     => t('teams.file_kind.docs'),
            self::KIND_ARCHIVE => t('teams.file_kind.archives'),
            self::KIND_AUDIO   => t('teams.file_kind.audio'),
            self::KIND_VIDEO   => t('teams.file_kind.video'),
            self::KIND_IMAGE   => t('teams.file_kind.images'),
            default            => t('teams.file_kind.all'),
        };
    }
}
