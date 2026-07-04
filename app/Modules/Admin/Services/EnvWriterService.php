<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

class EnvWriterService
{
    private string $envPath;

    public function __construct(?string $envPath = null)
    {
        // Path iniettabile per i test; in produzione resta il .env di progetto.
        $this->envPath = $envPath ?? dirname(__DIR__, 3) . '/../.env';
    }

    /**
     * Legge un valore dal file .env.
     */
    public function read(string $key): ?string
    {
        if (!file_exists($this->envPath)) {
            return null;
        }

        $lines = file($this->envPath, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (preg_match('/^' . preg_quote($key, '/') . '\s*=\s*(.*)$/', $line, $m)) {
                return trim($m[1], '"\'');
            }
        }

        return null;
    }

    /**
     * Scrive (aggiorna o aggiunge) una chiave nel file .env.
     * Usa flock() per sicurezza su accessi concorrenti.
     */
    public function write(string $key, string $value): void
    {
        if (!file_exists($this->envPath)) {
            throw new \RuntimeException('File .env non trovato: ' . $this->envPath);
        }

        $handle = fopen($this->envPath, 'r+');
        if (!$handle) {
            throw new \RuntimeException('Impossibile aprire .env per scrittura');
        }

        flock($handle, LOCK_EX);

        $content = stream_get_contents($handle);
        $pattern = '/^' . preg_quote($key, '/') . '\s*=.*$/m';

        // Valori con spazi vanno tra virgolette
        $formattedValue = $this->formatValue($value);
        $newLine = "{$key}={$formattedValue}";

        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $newLine, $content);
        } else {
            $content = rtrim($content) . "\n" . $newLine . "\n";
        }

        fseek($handle, 0);
        ftruncate($handle, 0);
        fwrite($handle, $content);
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /**
     * Aggiorna piu' chiavi in una volta.
     */
    public function writeMany(array $pairs): void
    {
        foreach ($pairs as $key => $value) {
            $this->write($key, $value);
        }
    }

    private function formatValue(string $value): string
    {
        if ($value === '' || preg_match('/\s/', $value)) {
            return '"' . addcslashes($value, '"') . '"';
        }
        return $value;
    }
}
