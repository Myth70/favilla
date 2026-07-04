<?php

declare(strict_types=1);

namespace App\Modules\Scheduler\Repositories;

use App\Repositories\BaseRepository;
use PDO;

class SchedulerRepository extends BaseRepository
{
    protected string $table = 'scheduler_jobs';

    /**
     * Tutti i job con il conteggio esecuzioni e l'ultimo log.
     */
    public function allWithStats(): array
    {
        $stmt = $this->pdo->query("
            SELECT j.*,
                   COALESCE(cnt.total, 0)   AS total_runs,
                   COALESCE(cnt.ok, 0)      AS success_runs,
                   COALESCE(cnt.fail, 0)    AS failed_runs
            FROM scheduler_jobs j
            LEFT JOIN (
                SELECT job_slug,
                       COUNT(*)                                  AS total,
                       SUM(status = 'success')                   AS ok,
                       SUM(status = 'failed')                    AS fail
                FROM scheduler_log
                GROUP BY job_slug
            ) cnt ON cnt.job_slug = j.slug
            ORDER BY j.interval_minutes, j.name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Acquisisce atomicamente i job scaduti e li segna come 'running'.
     *
     * Usa una transazione con SELECT…FOR UPDATE per prevenire la doppia esecuzione
     * in caso di due processi cron concorrenti. Un job con last_status='running'
     * viene considerato bloccato solo dopo 10 minuti dall'ultimo last_run_at.
     *
     * @return array<array<string,mixed>>
     */
    public function getDueJobs(): array
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->query("
                SELECT *
                FROM scheduler_jobs
                WHERE enabled = 1
                  AND (
                                        (
                                                next_retry_at IS NOT NULL
                                                AND next_retry_at <= NOW()
                                        )
                                        OR (
                                                next_retry_at IS NULL
                                                AND (
                                                        last_run_at IS NULL
                                                        OR DATE_ADD(last_run_at, INTERVAL interval_minutes MINUTE) <= NOW()
                                                )
                                        )
                  )
                  AND (
                    last_status != 'running'
                    OR last_run_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                  )
                ORDER BY interval_minutes
                FOR UPDATE
            ");
            $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($jobs)) {
                $ids = implode(',', array_map('intval', array_column($jobs, 'id')));
                $this->pdo->exec(
                    "UPDATE scheduler_jobs
                     SET last_status = 'running', last_run_at = NOW(), next_retry_at = NULL, updated_at = NOW()
                     WHERE id IN ({$ids})"
                );
            }

            $this->pdo->commit();
            return $jobs;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Segna un job come in esecuzione e aggiorna last_run_at.
     *
     * Aggiorna last_run_at = NOW() per evitare che la stuck-detection del CLI
     * (last_run_at < NOW() - 10min) ri-esegua il job mentre è già avviato in background.
     */
    public function markRunning(int $id): void
    {
        $this->pdo->prepare(
            "UPDATE scheduler_jobs SET last_status = 'running', last_run_at = NOW(), updated_at = NOW() WHERE id = ?"
        )->execute([$id]);
    }

    /**
     * Reimposta un job bloccato in stato 'running' a 'failed'.
     * No-op se il job non è in stato 'running'.
     */
    public function resetRunning(int $id, string $message = 'Reset manuale — job era bloccato in stato running.'): void
    {
        $this->pdo->prepare(
            "UPDATE scheduler_jobs
             SET last_status = 'failed',
                 last_output = ?,
                 updated_at  = NOW()
             WHERE id = ? AND last_status = 'running'"
        )->execute([$message, $id]);
    }

    /**
     * Aggiorna il risultato di un job dopo l'esecuzione.
     */
    public function updateResult(int $id, string $status, string $output, int $durationMs, ?string $outputFile = null): void
    {
        $this->pdo->prepare('
            UPDATE scheduler_jobs
            SET last_status      = ?,
                last_output      = ?,
                last_output_file = ?,
                last_duration_ms = ?,
                retry_count      = 0,
                next_retry_at    = NULL,
                updated_at       = NOW()
            WHERE id = ?
        ')->execute([$status, $output, $outputFile, $durationMs, $id]);
    }

    /**
     * Aggiorna il job su fallimento e pianifica eventuale retry con backoff esponenziale.
     */
    public function updateFailureWithRetry(int $id, string $output, int $durationMs, ?string $outputFile = null): void
    {
        $stmt = $this->pdo->prepare('SELECT retry_count, max_retries FROM scheduler_jobs WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['retry_count' => 0, 'max_retries' => 0];

        $retryCount = (int) ($row['retry_count'] ?? 0) + 1;
        $maxRetries = (int) ($row['max_retries'] ?? 0);

        $nextRetryAt = null;
        if ($retryCount <= $maxRetries) {
            $minutes = min(60, (int) pow(2, max(0, $retryCount - 1)));
            $nextRetryAt = date('Y-m-d H:i:s', time() + ($minutes * 60));
        }

        $this->pdo->prepare(
            'UPDATE scheduler_jobs
             SET last_status = ?,
                 last_output = ?,
                 last_output_file = ?,
                 last_duration_ms = ?,
                 retry_count = ?,
                 next_retry_at = ?,
                 updated_at = NOW()
             WHERE id = ?'
        )->execute(['failed', $output, $outputFile, $durationMs, $retryCount, $nextRetryAt, $id]);
    }

    /**
     * Inserisce una riga nel log.
     */
    public function log(string $slug, string $startedAt, string $finishedAt, string $status, string $output, int $durationMs, ?string $outputFile = null): void
    {
        $this->pdo->prepare('
            INSERT INTO scheduler_log
                (job_slug, started_at, finished_at, status, output, output_file, duration_ms)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ')->execute([$slug, $startedAt, $finishedAt, $status, $output, $outputFile, $durationMs]);
    }

    /**
     * Log recente per tutti i job (ultimi N per job).
     */
    public function recentLog(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('
            SELECT l.*, j.name AS job_name, j.command AS job_command
            FROM scheduler_log l
            LEFT JOIN scheduler_jobs j ON j.slug = l.job_slug
            ORDER BY l.started_at DESC
            LIMIT ?
        ');
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Elimina log più vecchi di N giorni.
     */
    public function pruneLog(int $days = 30): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - (max(1, $days) * 86400));
        $stmt = $this->pdo->prepare(
            'DELETE FROM scheduler_log WHERE started_at < ?'
        );
        $stmt->execute([$cutoff]);
        return $stmt->rowCount();
    }

    /**
     * Restituisce tutti i file di output collegati a un job.
     * Include sia l'ultimo output del job che lo storico log.
     *
     * @return string[]
     */
    public function getOutputFilesForJob(int $id): array
    {
        $job = $this->find($id);
        if (!$job) {
            return [];
        }

        $files = [];
        if (!empty($job['last_output_file'])) {
            $files[] = $job['last_output_file'];
        }

        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT output_file
             FROM scheduler_log
             WHERE job_slug = ? AND output_file IS NOT NULL AND output_file != ""'
        );
        $stmt->execute([$job['slug']]);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $file) {
            if (!empty($file)) {
                $files[] = (string) $file;
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * Restituisce i file output dei log prunabili che non sono più referenziati da scheduler_jobs.
     *
     * @return string[]
     */
    public function getPrunableLogFiles(int $days = 30): array
    {
        $cutoff = date('Y-m-d H:i:s', time() - (max(1, $days) * 86400));
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT l.output_file
             FROM scheduler_log l
             LEFT JOIN scheduler_jobs j ON j.last_output_file = l.output_file
             WHERE l.started_at < ?
               AND l.output_file IS NOT NULL
               AND l.output_file != ""
               AND j.id IS NULL'
        );
        $stmt->execute([$cutoff]);

        return array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
    }

    /**
     * Abilita/disabilita un job.
     */
    public function setEnabled(int $id, bool $enabled): void
    {
        $this->pdo->prepare(
            'UPDATE scheduler_jobs SET enabled = ?, updated_at = NOW() WHERE id = ?'
        )->execute([(int) $enabled, $id]);
    }

    /**
     * Trova un singolo job per ID.
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM scheduler_jobs WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Trova un singolo job per slug (usato dal seeding dei job di modulo).
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM scheduler_jobs WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Crea un nuovo job. Ritorna l'ID inserito.
     */
    public function create(array $data): int
    {
        $this->pdo->prepare('
            INSERT INTO scheduler_jobs
                (slug, name, command, args_json, interval_minutes, enabled)
            VALUES (?, ?, ?, ?, ?, ?)
        ')->execute([
            $data['slug'],
            $data['name'],
            $data['command'],
            $data['args_json'] ?? null,
            (int) $data['interval_minutes'],
            (int) ($data['enabled'] ?? 1),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Aggiorna i campi configurabili di un job.
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE scheduler_jobs
            SET name             = ?,
                slug             = ?,
                command          = ?,
                args_json        = ?,
                interval_minutes = ?,
                enabled          = ?,
                updated_at       = NOW()
            WHERE id = ?
        ');
        return $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['command'],
            $data['args_json'] ?? null,
            (int) $data['interval_minutes'],
            (int) ($data['enabled'] ?? 1),
            $id,
        ]);
    }

    /**
     * Elimina un job e il suo log.
     */
    public function delete(int $id): bool
    {
        $job = $this->find($id);
        if ($job) {
            $this->pdo->prepare('DELETE FROM scheduler_log WHERE job_slug = ?')->execute([$job['slug']]);
        }
        $stmt = $this->pdo->prepare('DELETE FROM scheduler_jobs WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Controlla se uno slug esiste già (per unicità).
     * Esclude $excludeId per non bloccare l'update dello stesso record.
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM scheduler_jobs WHERE slug = ? AND id != ?');
            $stmt->execute([$slug, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM scheduler_jobs WHERE slug = ?');
            $stmt->execute([$slug]);
        }
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Log di esecuzione per un singolo job.
     */
    public function getLogForJob(string $slug, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('
            SELECT l.*, j.name AS job_name, j.command AS job_command
            FROM scheduler_log l
            LEFT JOIN scheduler_jobs j ON j.slug = l.job_slug
            WHERE l.job_slug = ?
            ORDER BY l.started_at DESC
            LIMIT ?
        ');
        $stmt->execute([$slug, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
