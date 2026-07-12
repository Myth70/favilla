<?php

declare(strict_types=1);

namespace App\Cli;

use App\Cli\Support\CliBootstrap;

class Console
{
    private array $commands = [
        'make:module'      => Commands\MakeModuleCommand::class,
        'make:migration'   => Commands\MakeMigrationCommand::class,
        'module:status'    => Commands\ModuleStatusCommand::class,
        'context:generate' => Commands\ContextGenerateCommand::class,
        'lang:check'       => Commands\LangCheckCommand::class,
        'cleanup'          => Commands\CleanupCommand::class,
        'notifications:process-queue'  => Commands\ProcessNotificationQueueCommand::class,
        'calendar:send-reminders'            => Commands\CalendarSendRemindersCommand::class,
        'contacts:process-reminders'   => Commands\ContactsProcessRemindersCommand::class,
        'tasks:send-due-reminders'  => Commands\TasksSendDueRemindersCommand::class,
        'scheduler:run'                => Commands\SchedulerRunCommand::class,
        'scheduler:run-single'         => Commands\SchedulerRunSingleCommand::class,
        'backup:run'                   => Commands\BackupRunCommand::class,
        'files:checksum-backfill'      => Commands\FilesChecksumBackfillCommand::class,
        'logs:rotate'                  => Commands\LogsRotateCommand::class,
        'retention:run'                => Commands\RetentionRunCommand::class,
        'reports:cleanup'              => Commands\ReportsCleanupCommand::class,
        'session:gc'                   => Commands\SessionGcCommand::class,
        'ratelimit:cleanup'            => Commands\RateLimitCleanupCommand::class,
        'webhooks:dispatch'            => Commands\WebhooksDispatchCommand::class,
        'health:check'                 => Commands\HealthCheckCommand::class,
        'blog:publish-scheduled'       => Commands\BlogPublishScheduledCommand::class,
        'documenti:send-expiry-reminders' => Commands\DocumentiSendExpiryRemindersCommand::class,
        'documenti:expire-published'      => Commands\DocumentiExpirePublishedCommand::class,
        'documenti:verify-integrity'      => Commands\DocumentiVerifyIntegrityCommand::class,
        'documenti:cleanup-orphans'       => Commands\DocumentiCleanupOrphansCommand::class,
        'help:export'                     => Commands\HelpExportCommand::class,
        'help:import'                     => Commands\HelpImportCommand::class,
        'demo:seed'                       => Commands\DemoSeedCommand::class,
        'demo:reset'                      => Commands\DemoResetCommand::class,
    ];

    public function run(array $argv): int
    {
        $command = $argv[1] ?? 'help';
        $args    = array_slice($argv, 2);

        if ($command === 'help') {
            $this->showHelp();
            return 0;
        }

        if (!$this->hasCommand($command)) {
            $this->showHelp();
            return 1;
        }

        // Garantisce che i fallimenti dei comandi (spesso job cron: scheduler,
        // backup, retention) finiscano nel log centrale con stack trace, non solo
        // nel redirect di shell. Cheap: registra il solo logger, nessun DB.
        CliBootstrap::ensureLogging();

        try {
            (new $this->commands[$command]())->handle($args);
            return 0;
        } catch (\Throwable $e) {
            app_log('error', 'Comando CLI fallito: ' . $command, [
                'command'   => $command,
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'file'      => $e->getFile() . ':' . $e->getLine(),
                'trace'     => $e->getTraceAsString(),
            ]);
            echo $e->getMessage() . PHP_EOL;
            return 1;
        }
    }

    public function hasCommand(string $command): bool
    {
        return isset($this->commands[$command]);
    }

    private function showHelp(): void
    {
        echo "\nFavilla CLI\n\n";
        echo "  make:module <NomeModulo>            Crea struttura completa modulo\n";
        echo "  make:migration <Modulo> <nome>      Aggiunge delta migration numerata\n";
        echo "  module:status                       Mostra stato migration per modulo\n";
        echo "  context:generate                    Genera l'indice project_context.json + dettagli context/<Modulo>.json\n";
        echo "  lang:check [--locale=xx] [--strict] Verifica completezza traduzioni vs baseline 'it' (exit 1 se mancano chiavi)\n";
        echo "  cleanup [--days=30] [--dry-run]     Pulisce dati stale dal database\n\n";
        echo "  notifications:process-queue         Processa la coda notifiche multicanale\n\n";
        echo "  calendar:send-reminders           Invia promemoria eventi calendario\n\n";
        echo "  contacts:process-reminders          Processa ricorrenze contatti (compleanni, ecc.)\n\n";
        echo "  tasks:send-due-reminders         Invia reminder scadenze attività personali\n\n";
        echo "  scheduler:run                       Master scheduler — esegue tutti i job in scadenza\n\n";
        echo "  backup:run                          Crea un backup del database\n\n";
        echo "  files:checksum-backfill             Calcola checksum SHA-256 per file senza integrità (ISO 27001)\n";
        echo "  files:checksum-backfill --dry-run   Anteprima senza salvare\n\n";
        echo "  logs:rotate                         Ruota log e pulisce file scaduti (ISO 27001)\n";
        echo "  logs:rotate --verify                Verifica integrità file di log ruotati\n";
        echo "  logs:rotate --purge-only            Solo pulizia file log scaduti\n\n";
        echo "  retention:run                       Esegue policy di data retention (ISO 27001)\n";
        echo "  retention:run --dry-run              Anteprima senza eliminare dati\n\n";
        echo "  reports:cleanup                     Elimina report scaduti dal disco (ISO 27001)\n\n";
        echo "  session:gc [--lifetime=7200]         Pulisce sessioni DB scadute\n\n";
        echo "  ratelimit:cleanup [--dry-run]        Pulisce entry rate_limits e login_attempts scadute\n\n";
        echo "  webhooks:dispatch [--limit=50]       Consegna i webhook in coda con retry a backoff\n\n";
        echo "  health:check [--quiet]               Esegue tutti i controlli di salute (exit 1 se falliti)\n\n";
        echo "  blog:publish-scheduled               Pubblica articoli Blog con publish_at scaduto\n\n";
        echo "  documenti:send-expiry-reminders     Invia reminder scadenza multi-stadio documenti\n\n";
        echo "  documenti:expire-published           Porta in stato 'scaduto' i documenti pubblicati scaduti\n\n";
        echo "  documenti:verify-integrity           Verifica integrità SHA-256 dei file documenti\n\n";
        echo "  documenti:cleanup-orphans [--hours=24] [--dry-run]\n";
        echo "                                        Elimina file documenti orfani più vecchi di N ore\n\n";
        echo "  help:export                          Esporta la KB Help Online in database/help/<modulo>.json\n\n";
        echo "  help:import [--module=X] [--force]   Importa la KB Help Online da database/help/*.json\n\n";
        echo "  demo:seed [--force] [--enable-modules]  Carica i dati demo \"Aurora Studio\"\n\n";
        echo "  demo:reset                           Azzera l'istanza DEMO (upload + DB + riseed); richiede DEMO_MODE=true\n\n";
    }
}
