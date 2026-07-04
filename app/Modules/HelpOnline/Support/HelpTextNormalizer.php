<?php

declare(strict_types=1);

namespace App\Modules\HelpOnline\Support;

/**
 * Normalizzazione e tokenizzazione del testo per il motore di ricerca
 * dell'help (italiano). Logica pura, condivisa tra ricerca (HelpOnlineService)
 * e indicizzazione admin (HelpAdminService).
 */
class HelpTextNormalizer
{
    private const STOP_WORDS = [
        'il','lo','la','le','gli','un','uno','una','di','del','della','dei','degli','delle',
        'al','allo','alla','agli','alle','dal','dalla','dai','dagli','dalle','col','sul',
        'sulla','sui','sugli','sulle','con','per','in','su','tra','fra','che','chi','cui',
        'come','cosa','quando','dove','quale','quali','quanto','quanta','quanti','quante',
        'piu','meno','tutto','tutta','tutti','tutte','molto','poco','poche','questo','questa',
        'questi','queste','quello','quella','quelli','quelle','suo','sua','suoi','sue','mio',
        'mia','miei','mie','tuo','tua','tuoi','tue','nostro','nostra','nostri','nostre',
        'vostro','vostra','vostri','vostre','si','no','non','si','ne','mi','ti','ci','vi',
        'lui','lei','loro','io','tu','noi','voi','essere','avere','fare','sono','sei','siamo',
        'siete','sono','ho','hai','ha','abbiamo','avete','hanno','era','erano','dev','deve',
        'devo','devi','dobbiamo','dovete','devono','puo','posso','puoi','possiamo','potete',
        'possono','va','vai','vado','andare','farsi','farlo','farla','dell','sull','nell',
        'all','dall','per','ed','e','o','ma','se',
    ];

    /**
     * Minuscole, accenti rimossi, solo [a-z0-9] e spazi singoli.
     */
    public static function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, [
            'à' => 'a', 'á' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i',
            'í' => 'i', 'ò' => 'o', 'ó' => 'o', 'ù' => 'u', 'ú' => 'u',
            '’' => ' ', '\'' => ' ',
        ]);
        $text = preg_replace('/[^a-z0-9]+/u', ' ', $text) ?? $text;
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * Token significativi (≥3 char, stop-word escluse) da testo già
     * normalizzato; fallback ai token grezzi se restano solo stop-word.
     *
     * @return string[]
     */
    public static function tokenize(string $normalizedQuery): array
    {
        $stopWords = array_flip(self::STOP_WORDS);
        $raw = array_values(array_filter(
            explode(' ', $normalizedQuery),
            static fn (string $token): bool => mb_strlen($token, 'UTF-8') >= 3
        ));
        $filtered = array_values(array_unique(array_filter(
            $raw,
            static fn (string $token): bool => !isset($stopWords[$token])
        )));

        return $filtered !== [] ? $filtered : array_values(array_unique($raw));
    }
}
