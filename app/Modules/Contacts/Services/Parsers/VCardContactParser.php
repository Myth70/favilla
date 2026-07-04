<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Services\Parsers;

/**
 * Parser vCard 2.1 / 3.0 / 4.0 — sottoinsieme sufficiente per import contatti.
 *
 * Implementa il minimo necessario per la rubrica Favilla:
 *  - line unfolding (RFC 6350: una riga continuazione inizia con WS),
 *  - decoding QUOTED-PRINTABLE (comune in 2.1) e BASE64 (saltato, non riguarda i campi testuali),
 *  - parametri TYPE / VALUE / ENCODING / CHARSET,
 *  - properties: FN, N, ORG, TITLE, EMAIL, TEL, ADR, URL, NOTE, BDAY, NICKNAME,
 *    X-SOCIALPROFILE, X-AIM e simili (ignorate ma non bloccanti),
 *  - selezione TEL/EMAIL: prima occorrenza marcata PREF (o TYPE=pref), altrimenti la prima.
 *    Una seconda occorrenza diventa `telefono_alt`.
 *
 * Non gestisce binari (FOTO, LOGO): vengono saltati per ridurre il consumo di memoria.
 */
class VCardContactParser
{
    /**
     * @return array{contacts: array<int,array<string,string>>, totalRows: int}
     */
    public function inspect(string $filepath, int $previewLimit = 50): array
    {
        $all = iterator_to_array($this->contacts($filepath), false);
        return [
            'contacts'  => array_slice($all, 0, $previewLimit),
            'totalRows' => count($all),
        ];
    }

    /**
     * Itera tutti i contatti del file come array `campo => valore` già mappati
     * sui nomi dei campi della tabella contatti.
     *
     * @return \Generator<int, array<string,string>>
     */
    public function contacts(string $filepath): \Generator
    {
        if (!is_readable($filepath)) {
            throw new \RuntimeException('File non leggibile: ' . $filepath);
        }

        $handle = fopen($filepath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Impossibile aprire ' . $filepath);
        }

        $index = 0;
        try {
            $buffer = '';
            $inside = false;
            $prevLine = '';

            while (($raw = fgets($handle)) !== false) {
                $line = rtrim($raw, "\r\n");

                // Strip BOM dalla primissima riga.
                if ($prevLine === '' && $buffer === '' && !$inside) {
                    if (strncmp($line, "\xEF\xBB\xBF", 3) === 0) {
                        $line = substr($line, 3);
                    }
                }

                // Line unfolding: una continuation line inizia con SPACE o TAB.
                if ($line !== '' && ($line[0] === ' ' || $line[0] === "\t")) {
                    $prevLine .= substr($line, 1);
                    continue;
                }

                if ($prevLine !== '') {
                    if (strcasecmp(trim($prevLine), 'BEGIN:VCARD') === 0) {
                        $inside  = true;
                        $buffer  = '';
                    } elseif (strcasecmp(trim($prevLine), 'END:VCARD') === 0) {
                        if ($inside && $buffer !== '') {
                            $contact = $this->parseVCard($buffer);
                            if ($contact !== null) {
                                yield $index++ => $contact;
                            }
                        }
                        $inside = false;
                        $buffer = '';
                    } elseif ($inside) {
                        $buffer .= $prevLine . "\n";
                    }
                }
                $prevLine = $line;
            }

            // Flush ultima linea.
            if ($prevLine !== '') {
                if (strcasecmp(trim($prevLine), 'END:VCARD') === 0 && $inside && $buffer !== '') {
                    $contact = $this->parseVCard($buffer);
                    if ($contact !== null) {
                        yield $index++ => $contact;
                    }
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Parsa il corpo di una singola vCard (senza BEGIN/END) in array di campi contatto.
     *
     * @return array<string,string>|null
     */
    private function parseVCard(string $body): ?array
    {
        $out = [
            'nome'         => '',
            'cognome'      => '',
            'azienda'      => '',
            'ruolo'        => '',
            'email'        => '',
            'telefono'     => '',
            'telefono_alt' => '',
            'indirizzo'    => '',
            'sito_web'     => '',
            'linkedin'     => '',
            'facebook'     => '',
            'twitter'      => '',
            'instagram'    => '',
            'note'         => '',
            'tags'         => '',
        ];

        $emails = [];
        $tels   = [];

        foreach (explode("\n", $body) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Separa property+params da value (primo ':' non escapato).
            $colon = strpos($line, ':');
            if ($colon === false) {
                continue;
            }

            $head = substr($line, 0, $colon);
            $val  = substr($line, $colon + 1);

            // head = PROPERTY[;PARAM=VALUE;...]
            $parts    = explode(';', $head);
            $property = strtoupper(trim($parts[0]));
            $params   = [];
            for ($i = 1; $i < count($parts); $i++) {
                $p = trim($parts[$i]);
                if ($p === '') {
                    continue;
                }
                if (str_contains($p, '=')) {
                    [$k, $v] = explode('=', $p, 2);
                    $params[strtoupper(trim($k))] = trim($v);
                } else {
                    // 2.1: TYPE senza '=' (es. TEL;HOME;VOICE)
                    $params['TYPE'] = isset($params['TYPE'])
                        ? $params['TYPE'] . ',' . strtoupper($p)
                        : strtoupper($p);
                }
            }

            $val = $this->decodeValue($val, $params);

            switch ($property) {
                case 'FN':
                    if ($out['nome'] === '' && $out['cognome'] === '') {
                        // Se non abbiamo ancora N, usa FN come fallback per nome+cognome.
                        $bits = preg_split('/\s+/', trim($val), 2);
                        $out['nome']    = $bits[0] ?? '';
                        $out['cognome'] = $bits[1] ?? '';
                    }
                    break;

                case 'N':
                    // N: Surname;Given;Additional;Prefix;Suffix
                    $bits = explode(';', $val);
                    $out['cognome'] = $this->unescape($bits[0] ?? '');
                    $out['nome']    = $this->unescape($bits[1] ?? $out['nome']);
                    break;

                case 'ORG':
                    $bits = explode(';', $val);
                    $out['azienda'] = $this->unescape($bits[0] ?? '');
                    break;

                case 'TITLE':
                case 'ROLE':
                    if ($out['ruolo'] === '') {
                        $out['ruolo'] = $this->unescape($val);
                    }
                    break;

                case 'EMAIL':
                    $emails[] = [
                        'value' => $this->unescape($val),
                        'pref'  => $this->isPref($params),
                    ];
                    break;

                case 'TEL':
                    $tels[] = [
                        'value' => $this->unescape($val),
                        'pref'  => $this->isPref($params),
                        'type'  => strtoupper($params['TYPE'] ?? ''),
                    ];
                    break;

                case 'ADR':
                    // ADR: PO;EXT;STREET;LOCALITY;REGION;POSTAL;COUNTRY
                    $bits = array_map([$this, 'unescape'], explode(';', $val));
                    $pieces = array_filter([
                        $bits[2] ?? '',
                        trim(($bits[5] ?? '') . ' ' . ($bits[3] ?? '')),
                        $bits[4] ?? '',
                        $bits[6] ?? '',
                    ], fn ($x) => trim((string) $x) !== '');
                    if ($out['indirizzo'] === '' && !empty($pieces)) {
                        $out['indirizzo'] = implode(', ', $pieces);
                    }
                    break;

                case 'URL':
                    $u = $this->unescape($val);
                    if ($out['sito_web'] === '') {
                        $out['sito_web'] = $u;
                    }
                    break;

                case 'X-SOCIALPROFILE':
                    $type = strtolower($params['TYPE'] ?? '');
                    $u    = $this->unescape($val);
                    if (str_contains($type, 'linkedin') && $out['linkedin'] === '') {
                        $out['linkedin'] = $u;
                    } elseif (str_contains($type, 'facebook') && $out['facebook'] === '') {
                        $out['facebook'] = $u;
                    } elseif (str_contains($type, 'twitter') && $out['twitter'] === '') {
                        $out['twitter'] = $u;
                    } elseif (str_contains($type, 'instagram') && $out['instagram'] === '') {
                        $out['instagram'] = $u;
                    }
                    break;

                case 'NOTE':
                    $out['note'] = $this->unescape($val);
                    break;

                case 'CATEGORIES':
                    // Diventano tag (CSV).
                    $cats = array_filter(array_map('trim', explode(',', $val)));
                    if (!empty($cats)) {
                        $out['tags'] = implode(', ', $cats);
                    }
                    break;

                case 'NICKNAME':
                    if ($out['nome'] === '') {
                        $out['nome'] = $this->unescape($val);
                    }
                    break;

                    // Properties ignorate intenzionalmente: FOTO, LOGO, KEY, GEO, BDAY, X-*, REV, UID, ...
            }
        }

        // Email: preferita (PREF) o prima, fallback su nessuna.
        $primaryEmail = $this->pickPreferred($emails);
        if ($primaryEmail !== null) {
            $out['email'] = $primaryEmail;
        }

        // Telefoni: primo numero "principale" su `telefono`, eventuale secondo su `telefono_alt`.
        if (!empty($tels)) {
            [$first, $second] = $this->pickTwoTelephones($tels);
            $out['telefono']     = $first;
            $out['telefono_alt'] = $second;
        }

        // Nome obbligatorio: se manca, scarta il contatto.
        $hasName = trim($out['nome']) !== '' || trim($out['cognome']) !== '';
        if (!$hasName) {
            return null;
        }

        return $out;
    }

    private function decodeValue(string $val, array $params): string
    {
        $encoding = strtoupper($params['ENCODING'] ?? '');
        if ($encoding === 'QUOTED-PRINTABLE') {
            // Soft line-breaks "=\n" devono essere rimossi prima della decode.
            $val = str_replace(["=\n", "=\r\n"], '', $val);
            $val = quoted_printable_decode($val);
        } elseif ($encoding === 'B' || $encoding === 'BASE64') {
            // I valori testuali in base64 sono rari; decodifica e basta.
            $decoded = base64_decode($val, true);
            if ($decoded !== false) {
                $val = $decoded;
            }
        }

        $charset = strtoupper($params['CHARSET'] ?? '');
        if ($charset !== '' && $charset !== 'UTF-8' && $val !== '') {
            $converted = @iconv($charset, 'UTF-8//TRANSLIT', $val);
            if ($converted !== false) {
                $val = $converted;
            }
        } elseif (!mb_check_encoding($val, 'UTF-8')) {
            $converted = @iconv('Windows-1252', 'UTF-8//TRANSLIT', $val);
            if ($converted !== false) {
                $val = $converted;
            }
        }

        return $val;
    }

    private function unescape(string $s): string
    {
        // RFC 6350: \\ \, \; \n
        return strtr($s, [
            '\\n'  => "\n",
            '\\N'  => "\n",
            '\\,'  => ',',
            '\\;'  => ';',
            '\\\\' => '\\',
        ]);
    }

    private function isPref(array $params): bool
    {
        if (isset($params['PREF'])) {
            return true;
        }
        $type = strtoupper($params['TYPE'] ?? '');
        return str_contains($type, 'PREF');
    }

    /**
     * @param array<int,array{value:string,pref:bool}> $items
     */
    private function pickPreferred(array $items): ?string
    {
        foreach ($items as $i) {
            if ($i['pref'] && trim($i['value']) !== '') {
                return trim($i['value']);
            }
        }
        foreach ($items as $i) {
            if (trim($i['value']) !== '') {
                return trim($i['value']);
            }
        }
        return null;
    }

    /**
     * @param array<int,array{value:string,pref:bool,type:string}> $tels
     * @return array{0:string,1:string}
     */
    private function pickTwoTelephones(array $tels): array
    {
        // Ordina: pref prima, poi CELL/MOBILE, poi tutti gli altri.
        usort($tels, function ($a, $b) {
            $scoreA = ($a['pref'] ? 2 : 0) + (str_contains($a['type'], 'CELL') || str_contains($a['type'], 'MOBILE') ? 1 : 0);
            $scoreB = ($b['pref'] ? 2 : 0) + (str_contains($b['type'], 'CELL') || str_contains($b['type'], 'MOBILE') ? 1 : 0);
            return $scoreB <=> $scoreA;
        });

        $values = [];
        foreach ($tels as $t) {
            $v = trim($t['value']);
            if ($v !== '' && !in_array($v, $values, true)) {
                $values[] = $v;
            }
            if (count($values) >= 2) {
                break;
            }
        }
        return [$values[0] ?? '', $values[1] ?? ''];
    }
}
