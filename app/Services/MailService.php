<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\MailLogRepository;
use App\Repositories\MailTemplateRepository;

class MailService
{
    private MailerService $mailer;
    private MailTemplateRepository $templateRepo;
    private MailLogRepository $logRepo;

    public function __construct()
    {
        $this->mailer = app(MailerService::class);
        $this->templateRepo = app(MailTemplateRepository::class);
        $this->logRepo = app(MailLogRepository::class);
    }

    /**
     * Send an email and log it.
     */
    public function send(string $to, string $subject, string $body, ?string $templateSlug = null): bool
    {
        $success = $this->mailer->send($to, $subject, $body);

        $driver = SettingsService::get('mail_driver', 'log');
        $status = $driver === 'log' ? 'logged' : ($success ? 'sent' : 'failed');

        $this->logRepo->create([
            'to_email'   => $to,
            'subject'    => $subject,
            'template'   => $templateSlug,
            'status'     => $status,
            'error'      => $success ? null : 'Send failed',
            'created_by' => $_SESSION['user_id'] ?? null,
        ]);

        return $success;
    }

    /**
     * Send from a DB template with variable replacement.
     */
    public function sendFromTemplate(string $to, string $templateSlug, array $vars): bool
    {
        $tpl = $this->templateRepo->findBySlug($templateSlug);

        if (!$tpl) {
            return false;
        }

        $vars['app_name'] = $vars['app_name'] ?? SettingsService::get('app_name', config('app.name', 'Favilla'));
        $renderedBody = $this->renderVariables($tpl['body_html'], $vars);
        $renderedSubject = $this->renderVariables($tpl['subject'], $vars);

        return $this->send($to, $renderedSubject, $renderedBody, $templateSlug);
    }

    /**
     * Send a test email to verify configuration.
     */
    public function sendTest(string $to): bool
    {
        $appName = SettingsService::get('app_name', config('app.name', 'Favilla'));
        $driver = SettingsService::get('mail_driver', 'log');

        $subject = "Test email da {$appName}";
        $body = '<h2>Email di test</h2>'
              . "<p>Questa email conferma che la configurazione email di <strong>{$appName}</strong> funziona correttamente.</p>"
              . "<p>Driver: <code>{$driver}</code></p>"
              . '<p>Data: ' . date('d/m/Y H:i:s') . '</p>';

        return $this->send($to, $subject, $body, null);
    }

    /**
     * Sostituisce i placeholder {{key}} nel template.
     * Per default il valore viene HTML-escapato per prevenire XSS quando
     * il corpo è HTML. Per includere HTML fidato usare il marker {{!key}}.
     */
    private function renderVariables(string $content, array $vars): string
    {
        foreach ($vars as $key => $value) {
            if (!is_string($value) && !is_numeric($value)) {
                continue;
            }
            $raw = (string) $value;
            // {{!key}} inietta il valore NON escapato: ammesso solo perché i
            // template email sono autori da admin (config/Mail + UI admin), mai
            // da input utente. Non usare con valori non fidati (XSS nel corpo).
            $content = str_replace('{{!' . $key . '}}', $raw, $content);
            $content = str_replace(
                '{{' . $key . '}}',
                htmlspecialchars($raw, ENT_QUOTES, 'UTF-8'),
                $content
            );
        }
        return $content;
    }
}
