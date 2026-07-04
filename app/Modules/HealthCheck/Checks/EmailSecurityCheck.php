<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Checks;

/**
 * ISO 27001 A.13.2 — Autenticazione email SPF/DKIM/DMARC.
 *
 * Check "deep": esegue lookup DNS (anche multipli per i selettori DKIM), quindi
 * non va eseguito ad ogni refresh del dashboard.
 */
class EmailSecurityCheck extends AbstractHealthCheck
{
    protected string $depth = self::DEPTH_DEEP;

    public function key(): string
    {
        return 'email_security';
    }

    public function label(): string
    {
        return 'Sicurezza Email';
    }

    public function description(): string
    {
        return 'Controllo A.13.2 — Autenticazione email SPF/DKIM/DMARC.';
    }

    protected function checks(): array
    {
        $checks = [];
        $isProduction = $this->isProduction();

        try {
            $fromAddress = (string) setting('mail_from_address', 'noreply@favilla.local');
            $driver      = (string) setting('mail_driver', 'log');

            $domain = '';
            if (preg_match('/@(.+)$/', $fromAddress, $m)) {
                $domain = $m[1];
            }

            if ($driver === 'log') {
                $checks[] = $isProduction
                    ? $this->warn('Driver email', 'log — email non inviate realmente. SPF/DKIM/DMARC non verificabili')
                    : $this->ok('Driver email', 'log in ambiente non produttivo');
                return $checks;
            }

            $checks[] = $this->ok('Driver email', $driver);

            if (empty($domain) || $domain === 'favilla.local') {
                $checks[] = $this->warn('Dominio mittente', $fromAddress . ' — configurare un dominio reale');
                return $checks;
            }

            $checks[] = $this->ok('Dominio mittente', $domain);

            // SPF
            $spfFound = false;
            $spfRecords = @dns_get_record($domain, DNS_TXT);
            if (is_array($spfRecords)) {
                foreach ($spfRecords as $record) {
                    if (isset($record['txt']) && str_starts_with(strtolower($record['txt']), 'v=spf1')) {
                        $spfFound = true;
                        $checks[] = $this->ok('SPF (Sender Policy Framework)', $record['txt']);
                        break;
                    }
                }
            }
            if (!$spfFound) {
                $checks[] = $this->fail('SPF (Sender Policy Framework)', 'Nessun record SPF trovato per ' . $domain);
            }

            // DMARC
            $dmarcFound = false;
            $dmarcRecords = @dns_get_record('_dmarc.' . $domain, DNS_TXT);
            if (is_array($dmarcRecords)) {
                foreach ($dmarcRecords as $record) {
                    if (isset($record['txt']) && str_starts_with(strtolower($record['txt']), 'v=dmarc1')) {
                        $dmarcFound = true;
                        $checks[] = $this->ok('DMARC', $record['txt']);
                        break;
                    }
                }
            }
            if (!$dmarcFound) {
                $checks[] = $this->warn('DMARC', 'Nessun record DMARC trovato per ' . $domain);
            }

            // DKIM — verifica i selettori comuni
            $dkimFound = false;
            foreach (['default', 'google', 'mail', 'dkim', 'selector1', 'selector2', 'k1'] as $selector) {
                $dkimRecords = @dns_get_record($selector . '._domainkey.' . $domain, DNS_TXT);
                if (is_array($dkimRecords) && !empty($dkimRecords)) {
                    foreach ($dkimRecords as $record) {
                        if (isset($record['txt']) && str_contains(strtolower($record['txt']), 'v=dkim1')) {
                            $dkimFound = true;
                            $checks[] = $this->ok('DKIM (selector: ' . $selector . ')', 'Record DKIM presente');
                            break 2;
                        }
                    }
                }
            }
            if (!$dkimFound) {
                $checks[] = $this->warn('DKIM', 'Nessun record DKIM trovato (selettori comuni verificati)');
            }

            // SMTP encryption
            $smtpEncryption = (string) setting('smtp_encryption', 'tls');
            $checks[] = in_array($smtpEncryption, ['tls', 'ssl'], true)
                ? $this->ok('Crittografia SMTP', strtoupper($smtpEncryption))
                : $this->warn('Crittografia SMTP', $smtpEncryption === '' ? 'nessuna — email inviate in chiaro' : $smtpEncryption);
        } catch (\Throwable $e) {
            $checks[] = $this->warn('Sicurezza email', 'Controllo non riuscito: ' . $e->getMessage());
        }

        return $checks;
    }
}
