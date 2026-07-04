<?php

declare(strict_types=1);

namespace App\Modules\Scheduler\Services;

use App\Cli\Console;
use App\Modules\Scheduler\Repositories\SchedulerRepository;

/**
 * Motore di scheduling: individua i job scaduti e li esegue in sequenza.
 *
 * I job vengono eseguiti come sottoprocessi PHP separati per garantire
 * isolamento di stato e cattura affidabile di output ed exit code.
 */
class SchedulerService
{
    private SchedulerRepository $repo;
    /** @var string[] */
    private array $defaultAllowedCommands = [
        'cleanup',
        'notifications:process-queue',
        'calendar:send-reminders',
        'contacts:process-reminders',
        'tasks:send-due-reminders',
        'backup:run',
        'logs:rotate',
        'retention:run',
        'reports:cleanup',
        'session:gc',
        'ratelimit:cleanup',
    ];

    public function __construct(SchedulerRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Esegue tutti i job scaduti.
     *
     * @return array{checked:int, executed:int, success:int, failed:int}
     */
    public function runDueJobs(): array
    {
        $jobs  = $this->repo->getDueJobs();
        $stats = ['checked' => count($jobs), 'executed' => 0, 'success' => 0, 'failed' => 0];

        foreach ($jobs as $job) {
            $result = $this->runJob($job);
            $stats['executed']++;
            if ($result['status'] === 'success') {
                $stats['success']++;
            } else {
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /**
     * Esegue un singolo job come sottoprocesso e registra il risultato.
     *
     * @return array{status:string, output:string, duration_ms:int}
     */
    public function runJob(array $job): array
    {
        $startedAt  = \date('Y-m-d H:i:s');
        $startMicro = \microtime(true);

        try {
            $command = $this->normalizeCommand((string) ($job['command'] ?? ''));
            $this->assertCommandIsRunnable($command);
            $args = $this->parseArgs($job['args_json'] ?? null);

            $this->repo->markRunning((int) $job['id']);
            [$output, $exitCode] = $this->exec($command, $args);
        } catch (\Throwable $e) {
            $output = $e->getMessage();
            $exitCode = 1;
        }

        $durationMs = (int) \round((\microtime(true) - $startMicro) * 1000);
        $status     = $exitCode === 0 ? 'success' : 'failed';
        $finishedAt = \date('Y-m-d H:i:s');

        [$outputTrimmed, $outputFile] = $this->prepareOutputForPersistence($job['slug'], $startedAt, $output);

        if ($status === 'success') {
            $this->repo->updateResult((int) $job['id'], $status, $outputTrimmed, $durationMs, $outputFile);
        } else {
            $this->repo->updateFailureWithRetry((int) $job['id'], $outputTrimmed, $durationMs, $outputFile);
        }
        $this->repo->log($job['slug'], $startedAt, $finishedAt, $status, $outputTrimmed, $durationMs, $outputFile);

        return ['status' => $status, 'output' => $output, 'duration_ms' => $durationMs];
    }

    /**
     * Tutti i job con statistiche (per la view admin).
     */
    public function getJobs(): array
    {
        return array_map([$this, 'decorateJobRow'], $this->repo->allWithStats());
    }

    /**
     * Log recente (per la view admin).
     */
    public function getRecentLog(int $limit = 50): array
    {
        return array_map([$this, 'decorateJobRow'], $this->repo->recentLog($limit));
    }

    /**
     * Abilita/disabilita un job.
     */
    public function setEnabled(int $id, bool $enabled): void
    {
        $this->repo->setEnabled($id, $enabled);
    }

    /**
     * Trova un singolo job per ID.
     */
    public function getJob(int $id): ?array
    {
        $job = $this->repo->find($id);
        return $job !== null ? $this->decorateJobRow($job) : null;
    }

    /**
     * Comandi consentiti da mostrare in UI.
     *
     * @return string[]
     */
    public function getAllowedCommandsList(): array
    {
        return $this->getAllowedCommands();
    }

    /**
     * Crea un nuovo job dopo validazione.
     *
     * @throws \InvalidArgumentException
     */
    public function createJob(array $data): int
    {
        $this->validateJobData($data);
        $data['command'] = $this->normalizeCommand((string) ($data['command'] ?? ''));
        if ($this->repo->slugExists($data['slug'])) {
            throw new \InvalidArgumentException(t('scheduler.validation.slug_in_use', ['slug' => $data['slug']]));
        }
        $data['args_json'] = $this->normalizeArgs($data['args_json'] ?? '');
        return $this->repo->create($data);
    }

    /**
     * Aggiorna un job esistente dopo validazione.
     *
     * @throws \InvalidArgumentException
     */
    public function updateJob(int $id, array $data): void
    {
        $this->validateJobData($data);
        $data['command'] = $this->normalizeCommand((string) ($data['command'] ?? ''));
        if ($this->repo->slugExists($data['slug'], $id)) {
            throw new \InvalidArgumentException(t('scheduler.validation.slug_in_use', ['slug' => $data['slug']]));
        }
        $data['args_json'] = $this->normalizeArgs($data['args_json'] ?? '');
        $this->repo->update($id, $data);
    }

    /**
     * Elimina un job e il suo storico log.
     */
    public function deleteJob(int $id): void
    {
        $files = $this->repo->getOutputFilesForJob($id);
        if ($this->repo->delete($id)) {
            $this->deleteOutputFiles($files);
        }
    }

    /**
     * Trigger manuale asincrono (usato dal pannello web "Esegui ora").
     *
     * Marca il job come 'running' nel DB e lancia scheduler:run-single {id}
     * come processo background detached, poi ritorna subito senza attendere.
     *
     * @return array{status:'queued', output:string, duration_ms:int}
     * @throws \RuntimeException
     */
    public function runNow(int $id): array
    {
        $job = $this->repo->find($id);
        if (!$job) {
            throw new \RuntimeException(t('scheduler.runtime.job_not_found'));
        }

        $this->repo->markRunning($id);

        try {
            $this->dispatchBackground($id);
            return ['status' => 'queued', 'output' => '', 'duration_ms' => 0];
        } catch (\Throwable $e) {
            // Background dispatch non disponibile: esecuzione sincrona come fallback
            return $this->runJob($job);
        }
    }

    /**
     * Esegue un job per ID — usato da scheduler:run-single (CLI background).
     *
     * @return array{status:string, output:string, duration_ms:int}
     * @throws \RuntimeException
     */
    public function runJobById(int $id): array
    {
        $job = $this->repo->find($id);
        if (!$job) {
            throw new \RuntimeException(t('scheduler.runtime.job_not_found_id', ['id' => $id], $this->systemLocale()));
        }
        return $this->runJob($job);
    }

    /**
     * Reimposta un job bloccato in stato 'running' a 'failed'.
     *
     * @throws \RuntimeException
     */
    public function resetJob(int $id): void
    {
        if (!$this->repo->find($id)) {
            throw new \RuntimeException(t('scheduler.runtime.job_not_found'));
        }
        $this->repo->resetRunning($id, t('scheduler.runtime.reset_manual', [], $this->systemLocale()));
    }

    /**
     * Elimina log più vecchi di N giorni.
     */
    public function pruneLog(int $days = 30): int
    {
        $files = $this->repo->getPrunableLogFiles($days);
        $deleted = $this->repo->pruneLog($days);
        if ($deleted > 0) {
            $this->deleteOutputFiles($files);
        }

        return $deleted;
    }

    /**
     * Log di esecuzione per un singolo job.
     */
    public function getLogForJob(int $id, int $limit = 50): array
    {
        $job = $this->repo->find($id);
        if (!$job) {
            return [];
        }
        return array_map([$this, 'decorateJobRow'], $this->repo->getLogForJob($job['slug'], $limit));
    }

    // ── Localizzazione nomi job ──────────────────────────────────────────────

    /**
     * Aggiunge 'display_name' (nome job localizzato) a una riga job o log.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function decorateJobRow(array $row): array
    {
        $row['display_name'] = $this->localizeJobName($row);
        return $row;
    }

    /**
     * Nome visualizzato localizzato per un job.
     *
     * L'italiano è canonico: in locale 'it' si usa SEMPRE il nome salvato a DB
     * (eventualmente personalizzato dall'admin), senza override dall'overlay.
     * Per le altre lingue si consulta l'overlay `scheduler_jobs`, prima per slug
     * (override per-job opzionale) poi per command (catalogo dei comandi
     * standard), con fallback finale al nome italiano salvato.
     *
     * @param array<string,mixed> $row Riga job (name/slug/command) o log (job_name/job_slug/job_command).
     */
    private function localizeJobName(array $row): string
    {
        $name = (string) ($row['name'] ?? $row['job_name'] ?? $row['job_slug'] ?? '');

        if (locale() === 'it') {
            return $name;
        }

        $slug = (string) ($row['slug'] ?? $row['job_slug'] ?? '');
        if ($slug !== '') {
            $bySlug = t_line('scheduler_jobs', $slug, '');
            if ($bySlug !== '') {
                return $bySlug;
            }
        }

        $command = (string) ($row['command'] ?? $row['job_command'] ?? '');
        if ($command !== '') {
            $byCommand = t_line('scheduler_jobs', $command, '');
            if ($byCommand !== '') {
                return $byCommand;
            }
        }

        return $name;
    }

    /**
     * Locale di sistema per i messaggi runtime dello Scheduler: il cron non ha
     * la lingua di un utente. config('scheduler.locale') o, se vuoto,
     * config('localization.default').
     */
    private function systemLocale(): string
    {
        $configured = \trim((string) config('scheduler.locale', ''));
        return $configured !== '' ? $configured : (string) config('localization.default', 'it');
    }

    // ── Privato (validazione) ────────────────────────────────────────────────

    /**
     * Valida i campi obbligatori di un job.
     *
     * @throws \InvalidArgumentException
     */
    private function validateJobData(array $data): void
    {
        if (empty(trim($data['name'] ?? ''))) {
            throw new \InvalidArgumentException(t('scheduler.validation.name_required'));
        }
        if (strlen($data['name']) > 255) {
            throw new \InvalidArgumentException(t('scheduler.validation.name_too_long'));
        }
        $slug = \trim($data['slug'] ?? '');
        if (!\preg_match('/^[a-z0-9][a-z0-9._\-]{0,98}[a-z0-9]$/', $slug) && !\preg_match('/^[a-z0-9]{1,100}$/', $slug)) {
            throw new \InvalidArgumentException(t('scheduler.validation.slug_invalid'));
        }
        if (empty(trim($data['command'] ?? ''))) {
            throw new \InvalidArgumentException(t('scheduler.validation.command_required'));
        }
        if (strlen($data['command']) > 255) {
            throw new \InvalidArgumentException(t('scheduler.validation.command_too_long'));
        }
        $command = $this->normalizeCommand((string) ($data['command'] ?? ''));
        $this->assertCommandIsRunnable($command);
        $this->validateArgsPayload((string) ($data['args_json'] ?? ''));
        $interval = (int) ($data['interval_minutes'] ?? 0);
        if ($interval < 1) {
            throw new \InvalidArgumentException(t('scheduler.validation.interval_min'));
        }
    }

    /**
     * Restituisce la whitelist dei comandi scheduler dal file config.
     * Se assente o vuota, usa un set sicuro di default.
     *
     * @return string[]
     */
    private function getAllowedCommands(): array
    {
        $configured = config('scheduler.allowed_commands', []);
        if (!\is_array($configured) || $configured === []) {
            $base = $this->defaultAllowedCommands;
        } else {
            $base = [];
            foreach ($configured as $command) {
                $value = \trim((string) $command);
                if ($value !== '') {
                    $base[] = $value;
                }
            }
            if ($base === []) {
                $base = $this->defaultAllowedCommands;
            }
        }

        // Unisce i comandi dichiarati dai moduli abilitati (module.json → scheduled_jobs):
        // un modulo rende schedulabile un proprio comando senza editare il core.
        $merged = \array_merge($base, $this->discoverModuleCommands());

        return \array_values(\array_unique($merged));
    }

    /**
     * Comandi schedulabili dichiarati dai moduli abilitati via module.json.
     * Degrada a [] se il ModuleLoader non è disponibile (es. contesto di test).
     *
     * @return string[]
     */
    protected function discoverModuleCommands(): array
    {
        try {
            $loader = app(\App\Core\ModuleLoader::class);
        } catch (\Throwable $e) {
            return [];
        }

        $commands = [];
        foreach ($loader->getModules() as $module) {
            if (($module['enabled'] ?? true) === false) {
                continue;
            }
            $name = $module['name'] ?? null;
            if (!\is_string($name) || $name === '') {
                continue;
            }
            $meta = $loader->readModuleJson($name);
            if (!\is_array($meta)) {
                continue;
            }
            foreach ($this->extractScheduledJobs($meta) as $job) {
                $commands[] = $job['command'];
            }
        }

        return \array_values(\array_unique($commands));
    }

    /**
     * Normalizza il blocco scheduled_jobs di un module.json.
     *
     * @return array<array{slug:string,name:string,command:string,interval_minutes:int,args_json:?string,enabled_by_default:bool}>
     */
    public function extractScheduledJobs(array $moduleJson): array
    {
        $jobs = $moduleJson['scheduled_jobs'] ?? null;
        if (!\is_array($jobs)) {
            return [];
        }

        $out = [];
        foreach ($jobs as $job) {
            if (!\is_array($job)) {
                continue;
            }
            $command = \trim((string) ($job['command'] ?? ''));
            $slug    = \trim((string) ($job['slug'] ?? ''));
            if ($command === '' || $slug === '') {
                continue;
            }

            $interval = (int) ($job['interval_minutes'] ?? 0);
            if ($interval < 1) {
                $interval = 1440;
            }

            $argsJson = null;
            if (isset($job['args']) && \is_array($job['args'])) {
                $clean = \array_values(\array_filter(
                    \array_map(static fn ($v) => \trim((string) $v), $job['args']),
                    static fn ($v) => $v !== ''
                ));
                $argsJson = $clean !== [] ? \json_encode($clean) : null;
            }

            $out[] = [
                'slug'               => $slug,
                'name'               => \trim((string) ($job['name'] ?? $slug)),
                'command'            => $command,
                'interval_minutes'   => $interval,
                'args_json'          => $argsJson,
                'enabled_by_default' => (bool) ($job['enabled_by_default'] ?? false),
            ];
        }

        return $out;
    }

    /**
     * Sincronizza i job dichiarati da un modulo con la tabella scheduler_jobs.
     *
     * All'abilitazione del modulo i job mancanti vengono creati DISABILITATI
     * (l'admin li accende quando vuole); i job già presenti non vengono toccati.
     * Alla disabilitazione del modulo i relativi job vengono disattivati ma non
     * eliminati (si preserva lo storico e la configurazione).
     */
    public function syncModuleJobs(string $moduleName, bool $moduleEnabled): void
    {
        try {
            $meta = app(\App\Core\ModuleLoader::class)->readModuleJson($moduleName);
        } catch (\Throwable $e) {
            return;
        }
        if (!\is_array($meta)) {
            return;
        }

        foreach ($this->extractScheduledJobs($meta) as $job) {
            $existing = $this->repo->findBySlug($job['slug']);

            if ($moduleEnabled) {
                if ($existing === null) {
                    $this->repo->create([
                        'slug'             => $job['slug'],
                        'name'             => $job['name'],
                        'command'          => $job['command'],
                        'args_json'        => $job['args_json'],
                        'interval_minutes' => $job['interval_minutes'],
                        'enabled'          => $job['enabled_by_default'] ? 1 : 0,
                    ]);
                }
                // Job già presente: non si tocca (rispetta le impostazioni admin).
            } elseif ($existing !== null) {
                $this->repo->setEnabled((int) $existing['id'], false);
            }
        }
    }

    private function normalizeCommand(string $command): string
    {
        return \trim($command);
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function assertCommandIsRunnable(string $command): void
    {
        if ($command === '') {
            throw new \InvalidArgumentException(t('scheduler.validation.command_required'));
        }

        if (!\in_array($command, $this->getAllowedCommands(), true)) {
            throw new \InvalidArgumentException(t('scheduler.validation.command_not_allowed'));
        }

        $console = new Console();
        if (!$console->hasCommand($command)) {
            throw new \InvalidArgumentException(t('scheduler.validation.command_not_registered', ['command' => $command]));
        }
    }

    /**
     * Normalizza il campo args: stringa → JSON array.
     * Accetta JSON array, lista righe, o stringa vuota.
     */
    private function normalizeArgs(string $raw): ?string
    {
        $raw = \trim($raw);
        if ($raw === '') {
            return null;
        }
        // Se già JSON array valido, usarlo direttamente
        $decoded = \json_decode($raw, true);
        if (\is_array($decoded)) {
            $args = \array_values(\array_filter(\array_map(static fn ($v) => \trim((string) $v), $decoded), fn ($v) => $v !== ''));
            $this->assertArgsAreSafe($args);
            return $args ? \json_encode($args) : null;
        }
        // Altrimenti split per righe
        $args = \array_values(\array_filter(\array_map('trim', \explode("\n", $raw)), fn ($v) => $v !== ''));
        $this->assertArgsAreSafe($args);
        return $args ? \json_encode($args) : null;
    }

    private function validateArgsPayload(string $raw): void
    {
        $raw = \trim($raw);
        if ($raw === '') {
            return;
        }

        $decoded = \json_decode($raw, true);
        if (\is_array($decoded)) {
            $args = \array_values(\array_filter(\array_map(static fn ($v) => \trim((string) $v), $decoded), fn ($v) => $v !== ''));
            $this->assertArgsAreSafe($args);
            return;
        }

        $args = \array_values(\array_filter(\array_map('trim', \explode("\n", $raw)), fn ($v) => $v !== ''));
        $this->assertArgsAreSafe($args);
    }

    /**
     * @param string[] $args
     */
    private function assertArgsAreSafe(array $args): void
    {
        foreach ($args as $arg) {
            if (\preg_match('/[\x00\x0A\x0D]/', $arg)) {
                throw new \InvalidArgumentException(t('scheduler.validation.args_invalid_chars'));
            }

            if (\preg_match('/(;|\|\||&&|[|`$<>])/', $arg)) {
                throw new \InvalidArgumentException(t('scheduler.validation.args_shell_ops'));
            }

            if (\mb_strlen($arg) > 500) {
                throw new \InvalidArgumentException(t('scheduler.validation.args_too_long'));
            }
        }
    }

    // ── Privato ──────────────────────────────────────────────────────────────

    /**
     * Prepara output e file per la persistenza, degradando in modo sicuro se il file logging fallisce.
     *
     * @return array{0:string,1:?string}
     */
    protected function prepareOutputForPersistence(string $slug, string $startedAt, string $output): array
    {
        if (\mb_strlen($output) <= 8192) {
            return [\mb_substr($output, 0, 65535), null];
        }

        try {
            $outputFile = $this->writeOutputToFile($slug, $startedAt, $output);
            $preview = \mb_substr($output, 0, 1024);

            return [$preview . "\n[...] " . t('scheduler.runtime.output_archived', [], $this->systemLocale()), $outputFile];
        } catch (\Throwable $e) {
            $preview = \mb_substr($output, 0, 4096);
            $warning = "\n[WARN] " . t('scheduler.runtime.output_archive_failed', ['error' => $e->getMessage()], $this->systemLocale());

            return [\mb_substr($preview . $warning, 0, 65535), null];
        }
    }

    /**
     * Scrive l'output di un job su file-system e restituisce il nome del file.
     * Il file viene salvato in storage/logs/scheduler/.
     *
     * @throws \RuntimeException
     */
    protected function writeOutputToFile(string $slug, string $startedAt, string $output): string
    {
        $dir = BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'scheduler';
        if (!\is_dir($dir) && !@\mkdir($dir, 0750, true)) {
            throw new \RuntimeException(t('scheduler.runtime.log_dir_failed', [], $this->systemLocale()));
        }

        $safeName  = \preg_replace('/[^a-z0-9\-_]/', '_', \strtolower($slug));
        $timestamp = \date('Y-m-d_H-i-s', \strtotime($startedAt));
        $filename  = $safeName . '_' . $timestamp . '_' . \bin2hex(\random_bytes(4)) . '.log';
        $path      = $dir . DIRECTORY_SEPARATOR . $filename;

        if (\file_put_contents($path, $output) === false) {
            throw new \RuntimeException(t('scheduler.runtime.output_write_failed', [], $this->systemLocale()));
        }

        return $filename;
    }

    /**
     * Elimina una lista di file output scheduler, ignorando i nomi non validi o mancanti.
     *
     * @param string[] $files
     */
    protected function deleteOutputFiles(array $files): void
    {
        foreach (\array_unique(\array_filter($files)) as $file) {
            $this->deleteOutputFile((string) $file);
        }
    }

    /**
     * Elimina un singolo file di output scheduler se esiste.
     */
    protected function deleteOutputFile(string $filename): void
    {
        $path = $this->getOutputFilePath($filename);
        if ($path !== null && \is_file($path)) {
            @\unlink($path);
        }
    }

    /**
     * Restituisce il path assoluto di un file di log output, dopo validazione.
     * Restituisce null se il nome file non soddisfa il pattern di sicurezza.
     */
    public function getOutputFilePath(string $filename): ?string
    {
        // Solo nomi file semplici: lettere, numeri, _, -, . e .log finale
        if (!\preg_match('/^[a-zA-Z0-9._\-]+\.log$/', $filename)) {
            return null;
        }
        // Nessun componente di path traversal
        if (\str_contains($filename, '..') || \str_contains($filename, '/') || \str_contains($filename, '\\')) {
            return null;
        }

        $path = BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'scheduler' . DIRECTORY_SEPARATOR . $filename;
        return \is_file($path) ? $path : null;
    }

    /**
     * Lancia scheduler:run-single {id} come processo background detached.
     * Ritorna subito senza attendere il completamento del processo figlio.
     *
     * Su Windows usa proc_open → exec → popen come catena di fallback,
     * dato che popen() può essere disabilitata in alcune configurazioni PHP.
     *
     * @throws \RuntimeException se nessun metodo di dispatch è disponibile
     */
    private function dispatchBackground(int $id): void
    {
        $phpBin  = $this->resolvePhpBinary();
        $favilla = BASE_PATH . DIRECTORY_SEPARATOR . 'favilla';

        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = 'start "" /B '
                 . '"' . \str_replace('"', '', $phpBin) . '"'
                 . ' "' . \str_replace('"', '', $favilla) . '"'
                 . ' scheduler:run-single ' . (int) $id
                 . ' > NUL 2>&1';

            if ($this->canUseProcOpen()) {
                $process = \proc_open($cmd, [['file', 'NUL', 'r'], ['file', 'NUL', 'w'], ['file', 'NUL', 'w']], $pipes);
                if (\is_resource($process)) {
                    \proc_close($process);
                    return;
                }
            }

            if (\function_exists('exec')) {
                \exec($cmd);
                return;
            }

            if (\function_exists('popen')) {
                \pclose(\popen($cmd, 'r'));
                return;
            }

            throw new \RuntimeException(t('scheduler.runtime.dispatch_unavailable', [], $this->systemLocale()));
        } else {
            $cmd = '"' . \escapeshellcmd($phpBin) . '"'
                 . ' "' . \escapeshellcmd($favilla) . '"'
                 . ' scheduler:run-single ' . (int) $id
                 . ' > /dev/null 2>&1 &';
            \exec($cmd);
        }
    }

    /**
     * Risolve il path dell'eseguibile PHP CLI da usare per i sottoprocessi.
     *
     * Ordine di ricerca:
     * 1. Env SCHEDULER_PHP_BINARY (configurabile, nessuna validazione per evitare open_basedir)
     * 2. php.exe / php nella stessa directory di PHP_BINARY (con try-catch per open_basedir)
     * 3. Percorso XAMPP noto (Windows) o /usr/bin/php (Unix)
     * 4. PHP_BINARY (fallback finale)
     */
    private function resolvePhpBinary(): string
    {
        // 1. Configurazione esplicita: fidarsi senza is_file (potrebbe essere fuori open_basedir)
        $configured = (string) (env('SCHEDULER_PHP_BINARY', '') ?: config('scheduler.php_binary', ''));
        if ($configured !== '') {
            return $configured;
        }

        // 2. Prova stessa dir di PHP_BINARY — open_basedir potrebbe bloccare is_file
        $candidate = \dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php');
        try {
            if (\is_file($candidate)) {
                return $candidate;
            }
        } catch (\Throwable $e) {
            // open_basedir restriction — prosegui con fallback
        }

        // 3. Percorso noto per XAMPP Windows / Linux comune
        if (PHP_OS_FAMILY === 'Windows') {
            $xampp = 'C:\\xampp\\php\\php.exe';
            try {
                if (\is_file($xampp)) {
                    return $xampp;
                }
            } catch (\Throwable $e) {
                // open_basedir — usa comunque il path noto, è quasi certamente corretto
                return $xampp;
            }
        } else {
            foreach (['/usr/bin/php', '/usr/local/bin/php'] as $p) {
                try {
                    if (\is_file($p)) {
                        return $p;
                    }
                } catch (\Throwable $e) {
                    return $p;
                }
            }
        }

        return PHP_BINARY;
    }

    /**
     * Esegue il comando CLI favilla come sottoprocesso.
     * Usa PHP_BINARY per trovare l'eseguibile PHP corrente.
     *
     * @return array{0:string, 1:int}  [output, exitCode]
     */
    protected function exec(string $command, array $args): array
    {
        if (!$this->canUseProcOpen()) {
            return $this->execInProcess($command, $args);
        }

        $phpBin    = PHP_BINARY;
        $favilla  = BASE_PATH . DIRECTORY_SEPARATOR . 'favilla';
        $argString = \implode(' ', \array_map('escapeshellarg', $args));
        $timeoutSeconds = (int) config('scheduler.execution_timeout_seconds', 120);

        $cmd = \escapeshellarg($phpBin) . ' '
             . \escapeshellarg($favilla) . ' '
             . \escapeshellarg($command)
             . (\strlen($argString) ? ' ' . $argString : '');

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = \proc_open($cmd, $descriptors, $pipes);

        if (!\is_resource($process)) {
            return [t('scheduler.runtime.process_start_failed', [], $this->systemLocale()), 1];
        }

        \fclose($pipes[0]);

        \stream_set_blocking($pipes[1], false);
        \stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $timedOut = false;
        $start = \microtime(true);

        while (true) {
            $status = \proc_get_status($process);

            $stdout .= \stream_get_contents($pipes[1]) ?: '';
            $stderr .= \stream_get_contents($pipes[2]) ?: '';

            if (!$status['running']) {
                break;
            }

            if ($timeoutSeconds > 0 && (\microtime(true) - $start) >= $timeoutSeconds) {
                $timedOut = true;
                \proc_terminate($process);
                break;
            }

            \usleep(100000);
        }

        $stdout .= \stream_get_contents($pipes[1]) ?: '';
        $stderr .= \stream_get_contents($pipes[2]) ?: '';

        \fclose($pipes[1]);
        \fclose($pipes[2]);
        $exitCode = \proc_close($process);

        if ($timedOut) {
            $stderr = \trim($stderr . "\n" . t('scheduler.runtime.timeout', ['seconds' => $timeoutSeconds], $this->systemLocale()));
            if ($exitCode === 0) {
                $exitCode = 1;
            }
        }

        $output = \trim($stdout . ($stderr ? "\nSTDERR: " . $stderr : ''));
        return [$output, $exitCode];
    }

    private function canUseProcOpen(): bool
    {
        if (!\function_exists('proc_open')) {
            return false;
        }

        $disabled = \array_map('trim', \explode(',', (string) \ini_get('disable_functions')));
        return !\in_array('proc_open', $disabled, true);
    }

    /**
     * Fallback sicuro se proc_open non è disponibile: esecuzione in-process.
     * Riduce l'isolamento, ma mantiene lo Scheduler operativo su hosting restrittivi.
     *
     * @param string[] $args
     * @return array{0:string,1:int}
     */
    private function execInProcess(string $command, array $args): array
    {
        $console = new Console();
        $argv = \array_merge(['favilla', $command], $args);

        try {
            \ob_start();
            $exitCode = $console->run($argv);
            $output = \trim((string) \ob_get_clean());

            return [$output, $exitCode];
        } catch (\Throwable $e) {
            $buffer = \ob_get_level() > 0 ? (string) \ob_get_clean() : '';
            $message = \trim($buffer . ($buffer !== '' ? "\n" : '') . $e->getMessage());

            return [$message !== '' ? $message : t('scheduler.runtime.in_process_error', [], $this->systemLocale()), 1];
        }
    }

    /**
     * Decodifica args_json in array di stringhe.
     */
    private function parseArgs(?string $argsJson): array
    {
        if (!$argsJson) {
            return [];
        }
        $decoded = \json_decode($argsJson, true);
        if (!\is_array($decoded)) {
            return [];
        }

        $args = \array_values(\array_filter(\array_map(static fn ($v) => \trim((string) $v), $decoded), fn ($v) => $v !== ''));
        $this->assertArgsAreSafe($args);

        return $args;
    }
}
