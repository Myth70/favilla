<?php

declare(strict_types=1);

namespace App\Modules\Feedback\Services;

/**
 * Costruisce il report "azionabile" per admin/LLM a partire da una riga
 * `feedback`. Logica pura (nessuna dipendenza da DB/HTTP) → testabile.
 */
class FeedbackReportBuilder
{
    /**
     * Bundle Markdown pronto da incollare a un assistente che deve fixare.
     */
    public function toMarkdown(array $row): string
    {
        $contesto = $this->decode($row['contesto_json'] ?? null);
        $contesto = is_array($contesto) ? $contesto : [];
        $client   = is_array($contesto['client'] ?? null) ? $contesto['client'] : [];
        $server   = is_array($contesto['server'] ?? null) ? $contesto['server'] : [];
        $errors   = $this->decode($row['errori_console_json'] ?? null);
        if (!is_array($errors)) {
            $errors = is_array($client['errors'] ?? null) ? $client['errors'] : [];
        }
        $breadcrumb = is_array($client['breadcrumb'] ?? null) ? $client['breadcrumb'] : [];

        $ref   = (string) ($row['ref_code'] ?? ('#' . ($row['id'] ?? '')));
        $tipo  = (string) ($row['tipo'] ?? 'bug');
        $lines = [];

        $lines[] = "# Segnalazione {$ref} — " . $this->oneLine((string) ($row['titolo'] ?? ''));
        $lines[] = '';
        $lines[] = '| Campo | Valore |';
        $lines[] = '|---|---|';
        $lines[] = '| Tipo | ' . $this->cell($tipo) . ' |';
        $lines[] = '| Severità | ' . $this->cell((string) ($row['severita'] ?? '')) . ' |';
        $lines[] = '| Stato | ' . $this->cell((string) ($row['stato'] ?? '')) . ' |';
        $lines[] = '| Modulo | ' . $this->cell((string) ($row['modulo'] ?? ($server['modulo'] ?? 'n/d'))) . ' |';
        $lines[] = '| URL | ' . $this->cell((string) ($row['pagina_url'] ?? '')) . ' |';
        $lines[] = '| Route | ' . $this->cell((string) ($row['route_name'] ?? 'n/d')) . ' |';
        $lines[] = '| Autore | ' . $this->cell((string) ($row['creatore_nome'] ?? ('utente #' . ($row['created_by'] ?? '?')))) . ' |';
        $lines[] = '| Data | ' . $this->cell((string) ($row['created_at'] ?? '')) . ' |';
        $lines[] = '';

        $lines[] = '## Descrizione utente';
        $lines[] = trim((string) ($row['descrizione'] ?? '')) !== '' ? trim((string) $row['descrizione']) : '_(nessuna)_';
        $lines[] = '';

        if (trim((string) ($row['passi'] ?? '')) !== '') {
            $lines[] = '## Passi per riprodurre (indicati dall\'utente)';
            $lines[] = trim((string) $row['passi']);
            $lines[] = '';
        }

        $lines[] = '## Ambiente';
        $env = [
            'App version' => $row['app_version'] ?? ($server['app_version'] ?? null),
            'PHP'         => $server['php_version'] ?? null,
            'IP'          => $server['ip'] ?? null,
            'Ora server'  => $server['server_time'] ?? null,
            'User agent'  => $row['user_agent'] ?? ($client['user_agent'] ?? null),
            'Viewport'    => $row['viewport'] ?? null,
            'Lingua'      => $client['language'] ?? null,
            'Tema'        => isset($client['theme']) ? $this->compact($client['theme']) : null,
            'Ruoli'       => isset($server['user']['roles']) ? implode(', ', (array) $server['user']['roles']) : null,
        ];
        foreach ($env as $k => $v) {
            if ($v !== null && $v !== '') {
                $lines[] = "- **{$k}:** " . $this->oneLine((string) $v);
            }
        }
        $lines[] = '';

        if (!empty($errors)) {
            $lines[] = '## Errori catturati (console / HTMX)';
            $lines[] = '```';
            foreach ($errors as $err) {
                $lines[] = $this->formatError((array) $err);
            }
            $lines[] = '```';
            $lines[] = '';
        }

        if (!empty($breadcrumb)) {
            $lines[] = '## Sequenza azioni (breadcrumb automatico)';
            foreach ($breadcrumb as $b) {
                $lines[] = '- ' . $this->formatBreadcrumb((array) $b);
            }
            $lines[] = '';
        }

        $lines[] = '## Contesto completo (JSON)';
        $lines[] = '```json';
        $lines[] = json_encode($contesto ?: new \stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $lines[] = '```';

        return implode("\n", $lines) . "\n";
    }

    /**
     * Bundle JSON normalizzato (per il download .json).
     */
    public function toArray(array $row): array
    {
        return [
            'ref_code'    => $row['ref_code'] ?? null,
            'tipo'        => $row['tipo'] ?? null,
            'severita'    => $row['severita'] ?? null,
            'stato'       => $row['stato'] ?? null,
            'titolo'      => $row['titolo'] ?? null,
            'descrizione' => $row['descrizione'] ?? null,
            'passi'       => $row['passi'] ?? null,
            'modulo'      => $row['modulo'] ?? null,
            'pagina_url'  => $row['pagina_url'] ?? null,
            'route_name'  => $row['route_name'] ?? null,
            'app_version' => $row['app_version'] ?? null,
            'user_agent'  => $row['user_agent'] ?? null,
            'viewport'    => $row['viewport'] ?? null,
            'autore'      => $row['creatore_nome'] ?? null,
            'created_at'  => $row['created_at'] ?? null,
            'errori'      => $this->decode($row['errori_console_json'] ?? null) ?: [],
            'contesto'    => $this->decode($row['contesto_json'] ?? null) ?: [],
        ];
    }

    // ── Helper interni ────────────────────────────────────────────────

    private function decode(?string $json): mixed
    {
        if ($json === null || $json === '') {
            return null;
        }
        $decoded = json_decode($json, true);
        return $decoded ?? null;
    }

    private function formatError(array $err): string
    {
        $type = (string) ($err['type'] ?? 'js');
        if ($type === 'htmx') {
            return sprintf(
                '[HTMX] %s %s → HTTP %s  (%s)',
                strtoupper((string) ($err['verb'] ?? '')),
                (string) ($err['path'] ?? ''),
                (string) ($err['status'] ?? '?'),
                (string) ($err['ts'] ?? '')
            );
        }
        $msg = sprintf(
            '[JS] %s  (%s:%s)  %s',
            (string) ($err['message'] ?? ''),
            (string) ($err['source'] ?? ''),
            (string) ($err['line'] ?? ''),
            (string) ($err['ts'] ?? '')
        );
        if (!empty($err['stack'])) {
            $msg .= "\n     " . str_replace("\n", "\n     ", trim((string) $err['stack']));
        }
        return $msg;
    }

    private function formatBreadcrumb(array $b): string
    {
        $kind = (string) ($b['kind'] ?? '');
        $ts   = (string) ($b['ts'] ?? '');
        return match ($kind) {
            'htmx'  => sprintf('`%s` HTMX %s %s → %s', $ts, strtoupper((string) ($b['verb'] ?? '')), (string) ($b['path'] ?? ''), (string) ($b['status'] ?? '?')),
            'nav'   => sprintf('`%s` navigazione → %s', $ts, (string) ($b['path'] ?? '')),
            'click' => sprintf('`%s` click su %s', $ts, (string) ($b['target'] ?? '')),
            default => sprintf('`%s` %s', $ts, $this->oneLine(json_encode($b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '')),
        };
    }

    private function compact(array $kv): string
    {
        $parts = [];
        foreach ($kv as $k => $v) {
            if (is_scalar($v) && $v !== '') {
                $parts[] = "{$k}={$v}";
            }
        }
        return implode(' ', $parts);
    }

    private function oneLine(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', $s) ?? $s);
    }

    private function cell(string $s): string
    {
        // Evita di rompere le tabelle Markdown.
        return str_replace(['|', "\n"], ['\\|', ' '], $this->oneLine($s));
    }
}
