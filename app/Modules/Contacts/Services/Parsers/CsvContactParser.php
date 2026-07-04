<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Services\Parsers;

/**
 * Legge un CSV di contatti riga per riga.
 *
 * Responsabilità:
 *  - autodetect del delimitatore (`,`, `;`, `\t`) dalla prima riga non vuota,
 *  - rimozione del BOM UTF-8 iniziale,
 *  - normalizzazione encoding (assume UTF-8; converte da Windows-1252 se la
 *    decodifica UTF-8 fallisce in modo evidente — comune in CSV esportati da Excel IT).
 *
 * Non applica nessuna logica di mapping su campi contatto: restituisce sempre
 * `headers` + righe come array indicizzato. Sta al chiamante (ImportService)
 * applicare il mapping colonna→campo scelto dall'utente.
 */
class CsvContactParser
{
    private const SUPPORTED_DELIMITERS = [',', ';', "\t", '|'];

    /**
     * @return array{headers: string[], rows: array<int,string[]>, delimiter: string, totalRows: int}
     */
    public function inspect(string $filepath, int $previewLimit = 10): array
    {
        if (!is_readable($filepath)) {
            throw new \RuntimeException('File non leggibile: ' . $filepath);
        }

        $delimiter = $this->detectDelimiter($filepath);
        $handle    = fopen($filepath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Impossibile aprire ' . $filepath);
        }

        try {
            $headers   = [];
            $preview   = [];
            $totalRows = 0;
            $first     = true;

            while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                if ($row === [null] || $row === false) {
                    continue;
                }

                if ($first) {
                    $row[0]  = $this->stripBom((string) ($row[0] ?? ''));
                    $headers = array_map(fn ($c) => $this->normalizeCell((string) $c), $row);
                    $first   = false;
                    continue;
                }

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $totalRows++;
                if (count($preview) < $previewLimit) {
                    $preview[] = array_map(fn ($c) => $this->normalizeCell((string) ($c ?? '')), $row);
                }
            }
        } finally {
            fclose($handle);
        }

        return [
            'headers'   => $headers,
            'rows'      => $preview,
            'delimiter' => $delimiter,
            'totalRows' => $totalRows,
        ];
    }

    /**
     * Itera tutte le righe (header escluso) come array associativo `colName => value`.
     * Le colonne con header vuoto vengono indicizzate come `col_<n>`.
     *
     * @return \Generator<int, array<string,string>>
     */
    public function rows(string $filepath): \Generator
    {
        if (!is_readable($filepath)) {
            throw new \RuntimeException('File non leggibile: ' . $filepath);
        }

        $delimiter = $this->detectDelimiter($filepath);
        $handle    = fopen($filepath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Impossibile aprire ' . $filepath);
        }

        try {
            $headers = [];
            $first   = true;
            $line    = 0;

            while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                if ($row === [null] || $row === false) {
                    continue;
                }
                $line++;

                if ($first) {
                    $row[0]  = $this->stripBom((string) ($row[0] ?? ''));
                    $headers = array_map(fn ($c) => $this->normalizeCell((string) $c), $row);
                    foreach ($headers as $i => $h) {
                        if ($h === '') {
                            $headers[$i] = 'col_' . ($i + 1);
                        }
                    }
                    $first = false;
                    continue;
                }

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $assoc = [];
                foreach ($headers as $i => $key) {
                    $assoc[$key] = $this->normalizeCell((string) ($row[$i] ?? ''));
                }
                yield $line => $assoc;
            }
        } finally {
            fclose($handle);
        }
    }

    private function detectDelimiter(string $filepath): string
    {
        $handle = fopen($filepath, 'rb');
        if ($handle === false) {
            return ',';
        }
        try {
            $sample = '';
            while (!feof($handle) && strlen($sample) < 4096) {
                $sample .= fread($handle, 1024);
            }
        } finally {
            fclose($handle);
        }

        $sample = $this->stripBom($sample);
        $firstLine = strtok($sample, "\r\n") ?: $sample;

        $best  = ',';
        $score = -1;
        foreach (self::SUPPORTED_DELIMITERS as $d) {
            $count = substr_count($firstLine, $d);
            if ($count > $score) {
                $score = $count;
                $best  = $d;
            }
        }
        return $best;
    }

    private function stripBom(string $s): string
    {
        if (strncmp($s, "\xEF\xBB\xBF", 3) === 0) {
            return substr($s, 3);
        }
        return $s;
    }

    private function normalizeCell(string $value): string
    {
        // Se non è UTF-8 valido, prova a convertire da Windows-1252 (export Excel IT).
        if ($value !== '' && !mb_check_encoding($value, 'UTF-8')) {
            $converted = @iconv('Windows-1252', 'UTF-8//TRANSLIT', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }
        return trim($value);
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }
        return true;
    }
}
