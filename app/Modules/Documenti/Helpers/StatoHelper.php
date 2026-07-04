<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Helpers;

/**
 * Mapping centralizzato per stati documento.
 * Riusato da view, filtri, validazione whitelist e helper badge.
 */
final class StatoHelper
{
    public const STATI = [
        'bozza'           => ['color' => 'secondary', 'icon' => 'fa-pen-ruler'],
        'inviato'         => ['color' => 'info',      'icon' => 'fa-paper-plane'],
        'in_controllo'    => ['color' => 'warning',   'icon' => 'fa-magnifying-glass'],
        'controllato'     => ['color' => 'primary',   'icon' => 'fa-clipboard-check'],
        'in_approvazione' => ['color' => 'warning',   'icon' => 'fa-hourglass-half'],
        'approvato'       => ['color' => 'success',   'icon' => 'fa-circle-check'],
        'pubblicato'      => ['color' => 'success',   'icon' => 'fa-globe'],
        'scaduto'         => ['color' => 'danger',    'icon' => 'fa-circle-exclamation'],
        'rifiutato'       => ['color' => 'danger',    'icon' => 'fa-circle-xmark'],
        'archiviato'      => ['color' => 'dark',      'icon' => 'fa-box-archive'],
    ];

    public const AZIONI_AUDIT = [
        'create'           => 'success',
        'update'           => 'warning',
        'delete'           => 'danger',
        'soft_delete'      => 'danger',
        'restore'          => 'info',
        'workflow_invia'   => 'info',
        'workflow_approva' => 'success',
        'workflow_rifiuta' => 'danger',
    ];

    /**
     * Stati per cui esiste un'etichetta "responsabile corrente" (per la UI "in carico a").
     */
    public const RESPONSABILE_STATI = ['inviato', 'in_controllo', 'controllato', 'in_approvazione', 'approvato'];

    /**
     * @return list<string>
     */
    public static function statiValidi(): array
    {
        return array_keys(self::STATI);
    }

    /**
     * Etichetta del responsabile corrente, o null se il documento non è in workflow attivo.
     */
    public static function responsabile(string $stato): ?string
    {
        if (!in_array($stato, self::RESPONSABILE_STATI, true)) {
            return null;
        }
        return t('documenti.responsabile.' . $stato);
    }

    /**
     * Sanitizza un array di stati dal querystring contro la whitelist.
     *
     * @param array $input
     * @return list<string>
     */
    public static function filterStates(array $input): array
    {
        return array_values(array_intersect(
            array_map('strval', $input),
            self::statiValidi()
        ));
    }

    public static function label(string $stato): string
    {
        return isset(self::STATI[$stato])
            ? t('documenti.stato.' . $stato)
            : ucfirst(str_replace('_', ' ', $stato));
    }

    public static function color(string $stato): string
    {
        return self::STATI[$stato]['color'] ?? 'secondary';
    }

    public static function icon(string $stato): string
    {
        return self::STATI[$stato]['icon'] ?? 'fa-circle';
    }

    /**
     * Render badge HTML per uno stato documento.
     */
    public static function badge(string $stato, string $extraClass = ''): string
    {
        $color = self::color($stato);
        $label = self::label($stato);
        $icon  = self::icon($stato);
        $cls   = trim('badge bg-' . $color . ' dc-stato dc-stato-' . $stato . ' ' . $extraClass);
        return '<span class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '">'
             . '<i class="fa-solid ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . ' me-1" aria-hidden="true"></i>'
             . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
             . '</span>';
    }

    /**
     * Classe urgenza per scadenza in giorni (negativi = passato).
     */
    public static function urgencyClass(int $giorni): string
    {
        return match (true) {
            $giorni < 0   => 'dc-urgent-overdue',
            $giorni === 0 => 'dc-urgent-now',
            $giorni <= 7  => 'dc-urgent-soon',
            $giorni <= 14 => 'dc-urgent-watch',
            default       => '',
        };
    }

    public static function urgencyLabel(int $giorni): string
    {
        return match (true) {
            $giorni < 0   => t('documenti.urgency.overdue', ['days' => abs($giorni)]),
            $giorni === 0 => t('documenti.urgency.today'),
            $giorni === 1 => t('documenti.urgency.tomorrow'),
            default       => t('documenti.urgency.in_days', ['days' => $giorni]),
        };
    }

    public static function azioneAuditBadge(string $azione): string
    {
        $color = self::AZIONI_AUDIT[$azione] ?? 'secondary';
        $label = isset(self::AZIONI_AUDIT[$azione]) ? t('documenti.audit_azione.' . $azione) : $azione;
        $cls   = 'badge bg-' . $color;
        return '<span class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '">'
             . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
             . '</span>';
    }
}
