<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Services;

use App\Modules\Contacts\Repositories\ContactsRepository;
use App\Modules\Contacts\Services\Parsers\CsvContactParser;
use App\Modules\Contacts\Services\Parsers\VCardContactParser;
use App\Services\AuditService;

/**
 * Orchestratore dell'import contatti da file (CSV / vCard).
 *
 * - Detect del formato dall'estensione del filename (l'upload controller
 *   garantisce che l'estensione corrisponda al MIME via finfo).
 * - Anteprima e import passano per parser separati ma producono lo stesso
 *   modello dati: array `colName => value` per CSV (mapping richiesto),
 *   array di campi contatto pronti per vCard (mapping fisso).
 * - Duplicati: skip se email matcha (case-insensitive) un contatto già presente
 *   per lo stesso utente. Righe senza email non vengono mai marcate duplicate.
 */
class ContactFileImportService
{
    public const FORMAT_CSV   = 'csv';
    public const FORMAT_VCARD = 'vcf';

    /** Campi target su cui un utente può mappare una colonna CSV. */
    public const TARGET_FIELDS = [
        'nome'         => 'Nome',
        'cognome'      => 'Cognome',
        'azienda'      => 'Azienda',
        'ruolo'        => 'Ruolo',
        'email'        => 'Email',
        'telefono'     => 'Telefono',
        'telefono_alt' => 'Telefono alternativo',
        'indirizzo'    => 'Indirizzo',
        'sito_web'     => 'Sito web',
        'linkedin'     => 'LinkedIn',
        'facebook'     => 'Facebook',
        'twitter'      => 'Twitter',
        'instagram'    => 'Instagram',
        'whatsapp'     => 'WhatsApp',
        'telegram'     => 'Telegram',
        'tags'         => 'Tag',
        'note'         => 'Note',
    ];

    private ContactsRepository  $repo;
    private CsvContactParser    $csv;
    private VCardContactParser  $vcard;

    public function __construct(
        ContactsRepository  $repo,
        CsvContactParser    $csv,
        VCardContactParser  $vcard
    ) {
        $this->repo  = $repo;
        $this->csv   = $csv;
        $this->vcard = $vcard;
    }

    public function detectFormat(string $filename): ?string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return match ($ext) {
            'csv', 'txt'      => self::FORMAT_CSV,
            'vcf', 'vcard'    => self::FORMAT_VCARD,
            default           => null,
        };
    }

    /**
     * Anteprima per la pagina di mapping/conferma.
     *
     * @return array{
     *   format: string,
     *   headers?: string[],
     *   rows: array<int,array<string,string>|string[]>,
     *   delimiter?: string,
     *   totalRows: int,
     *   suggestedMapping?: array<int,string>,
     *   duplicateEmailsPreview: string[]
     * }
     */
    public function preview(string $filepath, string $format, int $userId): array
    {
        if ($format === self::FORMAT_CSV) {
            $info       = $this->csv->inspect($filepath, 10);
            $suggested  = $this->suggestMapping($info['headers']);
            $existing   = $this->getExistingEmails($userId);

            // Email previste (per il conteggio duplicati): scorriamo tutto il file
            // applicando la mapping suggerita, se include una colonna mappata su 'email'.
            $duplicates = [];
            $emailColIdx = array_search('email', $suggested, true);
            if ($emailColIdx !== false) {
                foreach ($this->csv->rows($filepath) as $row) {
                    $values = array_values($row);
                    $em = strtolower(trim((string) ($values[$emailColIdx] ?? '')));
                    if ($em !== '' && isset($existing[$em])) {
                        $duplicates[$em] = true;
                    }
                }
            }

            return [
                'format'                 => self::FORMAT_CSV,
                'headers'                => $info['headers'],
                'rows'                   => $info['rows'],
                'delimiter'              => $info['delimiter'],
                'totalRows'              => $info['totalRows'],
                'suggestedMapping'       => $suggested,
                'duplicateEmailsPreview' => array_keys($duplicates),
            ];
        }

        if ($format === self::FORMAT_VCARD) {
            $info     = $this->vcard->inspect($filepath, 50);
            $existing = $this->getExistingEmails($userId);

            $duplicates = [];
            foreach ($this->vcard->contacts($filepath) as $c) {
                $em = strtolower(trim((string) ($c['email'] ?? '')));
                if ($em !== '' && isset($existing[$em])) {
                    $duplicates[$em] = true;
                }
            }

            return [
                'format'                 => self::FORMAT_VCARD,
                'rows'                   => $info['contacts'],
                'totalRows'              => $info['totalRows'],
                'duplicateEmailsPreview' => array_keys($duplicates),
            ];
        }

        throw new \RuntimeException('Formato non supportato: ' . $format);
    }

    /**
     * Esegue l'import effettivo.
     *
     * @param array<int,string> $mapping  Per CSV: indice colonna → nome campo target
     *                                    (o stringa vuota / 'ignore' per ignorare).
     *
     * @return array{
     *   created: int,
     *   skipped: int,
     *   rejected: array<int,array{row:int,reason:string}>
     * }
     */
    public function import(string $filepath, string $format, int $userId, array $mapping = []): array
    {
        $existing = $this->getExistingEmails($userId);
        $created  = 0;
        $skipped  = 0;
        $rejected = [];

        if ($format === self::FORMAT_CSV) {
            $iterator = $this->iterCsvMapped($filepath, $mapping);
        } elseif ($format === self::FORMAT_VCARD) {
            $iterator = $this->vcard->contacts($filepath);
        } else {
            throw new \RuntimeException('Formato non supportato: ' . $format);
        }

        $rowNum = 1; // header conta come riga 1 per CSV; per vCard ogni record è una "riga logica"
        foreach ($iterator as $data) {
            $rowNum++;

            $data = $this->normalizeContact($data);

            if (trim((string) ($data['nome'] ?? '')) === '') {
                $rejected[] = ['row' => $rowNum, 'reason' => 'Nome mancante'];
                continue;
            }

            $emailLc = strtolower(trim((string) ($data['email'] ?? '')));
            if ($emailLc !== '' && isset($existing[$emailLc])) {
                $skipped++;
                continue;
            }

            $data['user_id']   = $userId;
            $data['preferito'] = 0;

            try {
                $id = $this->repo->create($data);
                $created++;
                if ($emailLc !== '') {
                    $existing[$emailLc] = $id;
                }
            } catch (\Throwable $e) {
                $rejected[] = ['row' => $rowNum, 'reason' => 'Errore database: ' . $e->getMessage()];
            }
        }

        AuditService::log('contatti_import_file', 'contatto', 0, null, [
            'format'   => $format,
            'created'  => $created,
            'skipped'  => $skipped,
            'rejected' => count($rejected),
            'user_id'  => $userId,
        ]);

        return [
            'created'  => $created,
            'skipped'  => $skipped,
            'rejected' => $rejected,
        ];
    }

    /**
     * Suggerisce un mapping iniziale colonna→campo basandosi sull'header del CSV.
     * Confronto case-insensitive con accent fold e match parziale.
     *
     * @param string[] $headers
     * @return array<int,string>  index colonna → field key (o '' se nessun match)
     */
    public function suggestMapping(array $headers): array
    {
        $synonyms = [
            'nome'         => ['nome', 'name', 'first name', 'firstname', 'given name', 'given'],
            'cognome'      => ['cognome', 'surname', 'last name', 'lastname', 'family name', 'family'],
            'azienda'      => ['azienda', 'company', 'organization', 'organisation', 'org', 'ditta'],
            'ruolo'        => ['ruolo', 'role', 'title', 'job title', 'job', 'qualifica', 'posizione'],
            'email'        => ['email', 'e-mail', 'mail', 'posta', 'indirizzo email'],
            'telefono'     => ['telefono', 'phone', 'tel', 'mobile', 'cell', 'cellulare', 'mobile phone'],
            'telefono_alt' => ['telefono alt', 'telefono alternativo', 'telefono 2', 'phone 2', 'altro telefono'],
            'indirizzo'    => ['indirizzo', 'address', 'street', 'via'],
            'sito_web'     => ['sito', 'sito web', 'website', 'url', 'web'],
            'linkedin'     => ['linkedin', 'linked in', 'profilo linkedin'],
            'facebook'     => ['facebook', 'fb'],
            'twitter'      => ['twitter', 'x'],
            'instagram'    => ['instagram', 'ig'],
            'whatsapp'     => ['whatsapp', 'wa'],
            'telegram'     => ['telegram'],
            'tags'         => ['tag', 'tags', 'etichette', 'labels', 'categorie'],
            'note'         => ['note', 'notes', 'commento', 'comment', 'memo'],
        ];

        $mapping = [];
        $used    = [];
        foreach ($headers as $i => $h) {
            $norm = $this->normalizeForMatch($h);
            $best = '';
            foreach ($synonyms as $field => $words) {
                if (in_array($field, $used, true)) {
                    continue;
                }
                foreach ($words as $w) {
                    if ($norm === $w || str_contains($norm, $w)) {
                        $best = $field;
                        break 2;
                    }
                }
            }
            $mapping[$i] = $best;
            if ($best !== '') {
                $used[] = $best;
            }
        }
        return $mapping;
    }

    /**
     * @return \Generator<int, array<string,string>>
     */
    private function iterCsvMapped(string $filepath, array $mapping): \Generator
    {
        // Mapping arriva come [colIndex => field]. Normalizziamo: vuoti / 'ignore' -> skip.
        $clean = [];
        foreach ($mapping as $i => $field) {
            $field = is_string($field) ? trim($field) : '';
            if ($field === '' || $field === 'ignore') {
                continue;
            }
            if (!isset(self::TARGET_FIELDS[$field])) {
                continue;
            }
            $clean[(int) $i] = $field;
        }

        foreach ($this->csv->rows($filepath) as $row) {
            $values = array_values($row);
            $out = [];
            foreach ($clean as $i => $field) {
                $val = trim((string) ($values[$i] ?? ''));
                if ($val === '') {
                    continue;
                }
                // Se il mapping assegna due colonne allo stesso campo, concatena con virgola.
                if (isset($out[$field]) && $out[$field] !== '') {
                    $out[$field] .= ', ' . $val;
                } else {
                    $out[$field] = $val;
                }
            }
            yield $out;
        }
    }

    /**
     * Pulisce / normalizza una riga prima dell'insert.
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function normalizeContact(array $data): array
    {
        // Solo i campi noti: scarta chiavi non in TARGET_FIELDS.
        $allowed = array_keys(self::TARGET_FIELDS);
        $out = [];
        foreach ($allowed as $k) {
            $out[$k] = isset($data[$k]) ? trim((string) $data[$k]) : '';
        }

        // Validation soft: email malformata -> svuota (ma non rifiuta la riga).
        if ($out['email'] !== '' && !filter_var($out['email'], FILTER_VALIDATE_EMAIL)) {
            $out['email'] = '';
        }

        // Telefoni: tieni solo se sono numerici/separatori comuni e di lunghezza ragionevole.
        foreach (['telefono', 'telefono_alt', 'whatsapp'] as $tk) {
            $v = $out[$tk] ?? '';
            if ($v !== '' && !preg_match('/^[\d\s\+\-\.\(\)]{6,30}$/', $v)) {
                $out[$tk] = '';
            }
        }

        // URL: aggiungi http:// se manca lo schema.
        foreach (['sito_web', 'linkedin', 'facebook'] as $uk) {
            $v = $out[$uk] ?? '';
            if ($v !== '' && !preg_match('#^https?://#i', $v)) {
                $out[$uk] = 'https://' . $v;
            }
        }

        // Tags: normalizza come fa ContactsService::normalizeTags
        if ($out['tags'] !== '') {
            $out['tags'] = ContactsService::normalizeTags($out['tags']);
        }

        // Trim length per restare nei limiti del DB.
        $limits = [
            'nome' => 100, 'cognome' => 100, 'azienda' => 100, 'ruolo' => 100,
            'email' => 255, 'telefono' => 30, 'telefono_alt' => 30,
            'indirizzo' => 500, 'sito_web' => 255, 'linkedin' => 255,
            'facebook' => 255, 'twitter' => 100, 'instagram' => 100,
            'whatsapp' => 30, 'telegram' => 100,
            'tags' => 500, 'note' => 2000,
        ];
        foreach ($limits as $k => $max) {
            if (isset($out[$k]) && $out[$k] !== '') {
                $out[$k] = mb_substr($out[$k], 0, $max);
            }
        }

        // Rimuovi chiavi vuote per non sovrascrivere default DB con stringhe.
        foreach ($out as $k => $v) {
            if ($v === '') {
                $out[$k] = null;
            }
        }

        return $out;
    }

    /**
     * @return array<string,int>  email lowercase => contatto_id
     */
    private function getExistingEmails(int $userId): array
    {
        $pdo  = app(\PDO::class);
        $stmt = $pdo->prepare(
            "SELECT id, LOWER(email) AS em FROM contacts
             WHERE user_id = ? AND email IS NOT NULL AND email <> ''"
        );
        $stmt->execute([$userId]);

        $map = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $map[$row['em']] = (int) $row['id'];
        }
        return $map;
    }

    private function normalizeForMatch(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        return preg_replace('/[^a-z0-9 ]+/', ' ', $s) ?? $s;
    }
}
