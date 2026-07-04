<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Helpers;

/**
 * Helper UI per ridurre boilerplate nelle view del modulo Documenti.
 */
final class UiHelper
{
    /**
     * Render bottone con sola icona, accessibile e con tooltip.
     *
     * @param string $label Testo per aria-label e title (mostrato nel tooltip)
     * @param string $icon  Classe Font Awesome (es. "fa-eye")
     * @param array  $opts  ['href','class','onclick','data','type','aria-controls','disabled','title']
     */
    public static function ariaButton(string $label, string $icon, array $opts = []): string
    {
        $base = $opts['class'] ?? 'btn btn-sm btn-outline-secondary';
        $isLink = !empty($opts['href']);
        $tag = $isLink ? 'a' : 'button';
        $attrs = [
            'class'            => $base,
            'aria-label'       => $label,
            'title'            => $opts['title'] ?? $label,
            'data-bs-toggle'   => 'tooltip',
            'data-bs-placement' => $opts['placement'] ?? 'top',
        ];
        if ($isLink) {
            $attrs['href'] = $opts['href'];
        } else {
            $attrs['type'] = $opts['type'] ?? 'button';
        }
        if (!empty($opts['disabled'])) {
            $attrs['disabled'] = 'disabled';
        }
        if (!empty($opts['onclick'])) {
            $attrs['onclick'] = $opts['onclick'];
        }
        if (!empty($opts['data']) && is_array($opts['data'])) {
            foreach ($opts['data'] as $k => $v) {
                $attrs['data-' . $k] = (string) $v;
            }
        }
        if (!empty($opts['extra']) && is_array($opts['extra'])) {
            foreach ($opts['extra'] as $k => $v) {
                $attrs[$k] = (string) $v;
            }
        }
        $parts = [];
        foreach ($attrs as $k => $v) {
            $parts[] = htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '"';
        }
        return '<' . $tag . ' ' . implode(' ', $parts) . '>'
             . '<i class="fa-solid ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i>'
             . '</' . $tag . '>';
    }

    /**
     * Formatta dimensione file in unità leggibili (KB, MB, GB).
     */
    public static function formatBytes(int|float|null $bytes): string
    {
        if ($bytes === null || $bytes <= 0) {
            return '—';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $val = (float) $bytes;
        while ($val >= 1024 && $i < count($units) - 1) {
            $val /= 1024;
            $i++;
        }
        return number_format($val, $i === 0 ? 0 : 1, ',', '.') . ' ' . $units[$i];
    }

    /**
     * Tempo trascorso in forma compatta, localizzata (es. "3 ore fa", "5 gg fa").
     */
    public static function timeAgo(?string $datetime): string
    {
        if (!$datetime) {
            return '—';
        }
        $ts = strtotime($datetime);
        if ($ts === false) {
            return '—';
        }
        $diff = time() - $ts;
        if ($diff < 60) {
            return t('documenti.time_ago.just_now');
        }
        if ($diff < 3600) {
            return tc('documenti.time_ago.minutes', (int) floor($diff / 60));
        }
        if ($diff < 86400) {
            return tc('documenti.time_ago.hours', (int) floor($diff / 3600));
        }
        $d = (int) floor($diff / 86400);
        if ($d < 30) {
            return tc('documenti.time_ago.days', $d);
        }
        $mo = (int) floor($d / 30);
        if ($mo < 12) {
            return tc('documenti.time_ago.months', $mo);
        }
        return tc('documenti.time_ago.years', (int) floor($d / 365));
    }

    /**
     * Helper conveniente per attributi data-* da array.
     * @param array<string,string|int|bool> $data
     */
    public static function dataAttrs(array $data): string
    {
        $out = [];
        foreach ($data as $k => $v) {
            if ($v === false || $v === null) {
                continue;
            }
            if ($v === true) {
                $out[] = 'data-' . $k;
                continue;
            }
            $out[] = 'data-' . htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8')
                   . '="' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '"';
        }
        return implode(' ', $out);
    }
}
