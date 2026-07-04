<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

class MailerService
{
    private string $logPath;

    public function __construct()
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $logDir = $basePath . '/storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $this->logPath = $logDir . '/mail.log';
    }

    /**
     * Send an email via the configured driver (log or smtp).
     */
    public function send(string $to, string $subject, string $body): bool
    {
        $driver = SettingsService::get('mail_driver', 'log');

        if ($driver === 'smtp') {
            return $this->sendSmtp($to, $subject, $body);
        }

        return $this->sendLog($to, $subject, $body);
    }

    /**
     * Render a template and send it.
     * Looks for template in DB (mail_templates.slug) first, then falls back to PHP file.
     */
    public function sendTemplate(string $to, string $template, array $vars = [], string $subject = ''): bool
    {
        // Try DB template first
        try {
            $repo = app(\App\Repositories\MailTemplateRepository::class);
            $tpl = $repo->findBySlug($template);
        } catch (\Throwable $e) {
            $tpl = null;
            app_log('error', "[MailerService] Failed to load DB template '{$template}': " . $e->getMessage());
        }

        if ($tpl) {
            $vars['app_name'] = $vars['app_name'] ?? SettingsService::get('app_name', config('app.name', 'Favilla'));
            $renderedBody = $this->renderVariables($tpl['body_html'], $vars);
            $renderedSubject = $subject ?: $this->renderVariables($tpl['subject'], $vars);
            return $this->send($to, $renderedSubject, $renderedBody);
        }

        // Fallback to PHP file template
        return $this->sendFileTemplate($to, $template, $vars, $subject);
    }

    /**
     * Log driver — write to file (original behavior).
     */
    private function sendLog(string $to, string $subject, string $body): bool
    {
        $timestamp = date('Y-m-d H:i:s');
        $entry = str_repeat('=', 60) . "\n"
               . "Date: {$timestamp}\n"
               . "To: {$to}\n"
               . "Subject: {$subject}\n"
               . str_repeat('-', 60) . "\n"
               . $body . "\n"
               . str_repeat('=', 60) . "\n\n";

        return file_put_contents($this->logPath, $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    /**
     * SMTP driver — send via PHPMailer.
     */
    private function sendSmtp(string $to, string $subject, string $body): bool
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = SettingsService::get('smtp_host', '');
            $mail->Port       = (int) SettingsService::get('smtp_port', 587);
            $mail->SMTPAuth   = true;
            $mail->Username   = SettingsService::get('smtp_username', '');
            $mail->Password   = SettingsService::get('smtp_password', '');

            $encryption = SettingsService::get('smtp_encryption', 'tls');
            if ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }

            $mail->setFrom(
                SettingsService::get('mail_from_address', 'noreply@favilla.local'),
                SettingsService::get('mail_from_name', 'Favilla')
            );
            $mail->addAddress($to);
            $mail->CharSet = 'UTF-8';

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            $mail->send();
            return true;
        } catch (PHPMailerException $e) {
            // Log the error for debugging
            $this->sendLog($to, "[SMTP ERROR] {$subject}", "Error: {$e->getMessage()}\n\nOriginal body:\n{$body}");
            return false;
        }
    }

    /**
     * Render PHP file template (fallback).
     */
    private function sendFileTemplate(string $to, string $template, array $vars, string $subject): bool
    {
        $basePath     = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $template     = basename($template);
        $templatePath = $basePath . '/app/Mail/templates/' . $template . '.php';

        if (!file_exists($templatePath)) {
            return false;
        }

        $vars['app_name'] = $vars['app_name'] ?? config('app.name', 'Favilla');
        $subject          = $subject ?: ($vars['title'] ?? ucfirst(str_replace('-', ' ', $template)));

        $body = (static function (string $_path, array $_vars): string {
            extract($_vars, EXTR_SKIP);
            ob_start();
            include $_path;
            return (string) ob_get_clean();
        })($templatePath, $vars);

        return $this->send($to, $subject, $body);
    }

    /**
     * Replace {{placeholder}} in a string with variable values.
     */
    private function renderVariables(string $content, array $vars): string
    {
        foreach ($vars as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $content = str_replace('{{' . $key . '}}', (string) $value, $content);
            }
        }
        return $content;
    }
}
