<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Checks;

/**
 * ISO 27001 A.4.1 — Configurazione php.ini per sicurezza in produzione.
 */
class PhpHardeningCheck extends AbstractHealthCheck
{
    public function key(): string
    {
        return 'php_hardening';
    }

    public function label(): string
    {
        return 'Hardening PHP';
    }

    public function description(): string
    {
        return 'Controllo A.4.1 — Configurazione php.ini per sicurezza in produzione.';
    }

    protected function checks(): array
    {
        $checks = [];
        $isProduction = $this->isProduction();

        // expose_php — should be Off in production
        // Nota: '0' è falsy in PHP, quindi !$valore copre già '0'/''/false.
        $expose = ini_get('expose_php');
        if (!$expose || $expose === 'Off') {
            $checks[] = $this->ok('expose_php', 'disattivato — versione PHP non esposta negli header');
        } elseif ($isProduction) {
            $checks[] = $this->warn('expose_php', 'attivo — la versione PHP è visibile negli header HTTP');
        } else {
            $checks[] = $this->ok('expose_php', 'attivo in ambiente non produttivo');
        }

        // allow_url_include — MUST be Off
        $urlInclude = ini_get('allow_url_include');
        if (!$urlInclude || $urlInclude === 'Off') {
            $checks[] = $this->ok('allow_url_include', 'disattivato');
        } elseif ($isProduction) {
            $checks[] = $this->fail('allow_url_include', 'attivo — rischio Remote File Inclusion (RFI)');
        } else {
            $checks[] = $this->warn('allow_url_include', 'attivo in ambiente non produttivo — disattivare prima del rilascio');
        }

        // allow_url_fopen — prefer Off
        $urlFopen = ini_get('allow_url_fopen');
        if (!$urlFopen || $urlFopen === 'Off') {
            $checks[] = $this->ok('allow_url_fopen', 'disattivato');
        } elseif ($isProduction) {
            $checks[] = $this->warn('allow_url_fopen', 'attivo — da valutare disattivazione se non necessario');
        } else {
            $checks[] = $this->ok('allow_url_fopen', 'attivo in ambiente non produttivo');
        }

        // open_basedir — should be set
        $openBasedir = ini_get('open_basedir');
        if (!empty($openBasedir)) {
            $checks[] = $this->ok('open_basedir', 'configurato: ' . (strlen($openBasedir) > 80 ? substr($openBasedir, 0, 77) . '...' : $openBasedir));
        } elseif ($isProduction) {
            $checks[] = $this->warn('open_basedir', 'non configurato — il PHP può accedere a tutto il filesystem');
        } else {
            $checks[] = $this->ok('open_basedir', 'non configurato in ambiente non produttivo');
        }

        // disable_functions — check for dangerous functions
        $disabledFns  = ini_get('disable_functions');
        $dangerousFns = ['exec', 'passthru', 'shell_exec', 'system', 'proc_open', 'popen', 'eval', 'assert'];
        $disabledList = array_map('trim', explode(',', $disabledFns ?: ''));
        $disabledList = array_filter($disabledList);
        $notDisabled  = array_filter($dangerousFns, fn ($f) => !in_array($f, $disabledList, true));

        if (empty($notDisabled)) {
            $checks[] = $this->ok('disable_functions', count($dangerousFns) . ' funzioni pericolose disabilitate');
        } elseif (count($notDisabled) <= 3) {
            $checks[] = $this->warn('disable_functions', 'Non disabilitate: ' . implode(', ', $notDisabled));
        } else {
            $checks[] = $this->warn('disable_functions', count($notDisabled) . ' funzioni pericolose attive: ' . implode(', ', array_slice($notDisabled, 0, 5)));
        }

        // session.use_strict_mode
        $strict = ini_get('session.use_strict_mode');
        $checks[] = $strict
            ? $this->ok('session.use_strict_mode', 'attivo — sessioni con ID non inizializzati rifiutate')
            : $this->warn('session.use_strict_mode', 'disattivato — sessioni con ID arbitrari accettate');

        // session.cookie_samesite
        $samesite = ini_get('session.cookie_samesite');
        $checks[] = (!empty($samesite) && in_array(strtolower($samesite), ['strict', 'lax'], true))
            ? $this->ok('session.cookie_samesite', ucfirst(strtolower($samesite)))
            : $this->warn('session.cookie_samesite', empty($samesite) ? 'non configurato' : $samesite . ' — raccomandato Strict o Lax');

        // max_execution_time — should be reasonable
        $maxExec = (int) ini_get('max_execution_time');
        if ($maxExec === 0) {
            $checks[] = $this->warn('max_execution_time', 'illimitato — potenziale rischio DoS');
        } elseif ($maxExec <= 120) {
            $checks[] = $this->ok('max_execution_time', $maxExec . ' secondi');
        } else {
            $checks[] = $this->warn('max_execution_time', $maxExec . ' secondi — raccomandato max 120');
        }

        // log_errors
        $logErrors = ini_get('log_errors');
        $checks[] = $logErrors
            ? $this->ok('log_errors', 'attivo — gli errori vengono registrati')
            : $this->warn('log_errors', 'disattivato — errori non registrati nel log');

        // file_uploads — just informational
        $fileUploads = ini_get('file_uploads');
        $checks[] = $fileUploads
            ? $this->ok('file_uploads', 'attivi (necessario per il modulo Files)')
            : $this->warn('file_uploads', 'disattivati — il modulo Files non funzionerà');

        return $checks;
    }
}
