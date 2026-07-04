<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

/**
 * Sanitizza il template_html dei report (HTML autorato nel designer GrapesJS)
 * prima della persistenza.
 *
 * Sostituisce il vecchio strip_tags(): quello rimuoveva i tag non in allowlist
 * ma NON gli attributi, lasciando passare onerror=, href="javascript:" e simili
 * sui tag ammessi — innocui nel PDF (Dompdf non esegue JS) ma vettore di
 * stored-XSS nell'anteprima del designer quando un template condiviso viene
 * aperto da un altro utente. HTMLPurifier applica un'allowlist su tag E attributi
 * e ripulisce il CSS inline.
 *
 * Vincoli specifici di questo modulo (vedi SmartComponentResolver e i template
 * bundled in app/Modules/<Modulo>/report_templates/*.json):
 *  - i template usano CSS inline (style="...") per il layout → va preservato;
 *  - gli Smart Component sono marcati con gli attributi data-prm-type /
 *    data-prm-config: HTMLPurifier scarta gli attributi sconosciuti, quindi
 *    vanno registrati esplicitamente o i componenti non vengono più espansi;
 *  - alcuni template possono contenere blocchi <style> → estratti, ripuliti e
 *    reincorporati invece di essere eliminati.
 */
class TemplateHtmlSanitizer
{
    private \HTMLPurifier $purifier;

    /** Elementi sezionanti HTML5 non previsti dal doctype di default di HTMLPurifier. */
    private const HTML5_BLOCK_ELEMENTS = [
        'section', 'header', 'footer', 'article', 'nav', 'main', 'aside',
        'figure', 'figcaption', 'details', 'summary',
    ];

    /** Elementi su cui possono comparire gli attributi degli Smart Component. */
    private const PRM_HOST_ELEMENTS = ['div', 'span', 'section', 'p', 'table', 'td', 'th', 'img'];

    public function __construct()
    {
        $config = \HTMLPurifier_Config::createDefault();

        $config->set('Core.Encoding', 'UTF-8');
        // Salvataggio template = operazione rara → niente cache su disco (evita
        // dipendenze da permessi di scrittura), si ricalcola la definizione ogni volta.
        $config->set('Cache.DefinitionImpl', null);

        // CSS inline: i template GrapesJS posizionano tutto via style="...".
        // Trusted allenta la whitelist di proprietà (es. shorthand "background",
        // "display") perché l'autore ha già il permesso reports.create; il fetch
        // remoto resta comunque bloccato a valle da Dompdf (isRemoteEnabled=false).
        $config->set('CSS.Trusted', true);
        $config->set('CSS.AllowImportant', true);
        $config->set('CSS.AllowTricky', true);

        // Blocchi <style>: estrai, ripulisci e reincorpora (vedi sanitize()).
        $config->set('Filter.ExtractStyleBlocks', true);

        // Attributi: consenti class/id (usati per styling) ma niente schemi pericolosi.
        $config->set('Attr.EnableID', true);
        $config->set('URI.AllowedSchemes', [
            'http'   => true,
            'https'  => true,
            'mailto' => true,
            'data'   => true,
        ]);

        // Definizione custom: elementi HTML5 + attributi data-prm-* degli Smart Component.
        $config->set('HTML.DefinitionID', 'favilla-reports-template');
        $config->set('HTML.DefinitionRev', 1);

        $def = $config->maybeGetRawHTMLDefinition();
        if ($def !== null) {
            foreach (self::HTML5_BLOCK_ELEMENTS as $el) {
                // 'Common' include style/class/id/title/lang/dir.
                $def->addElement($el, 'Block', 'Flow', 'Common');
            }
            foreach (self::PRM_HOST_ELEMENTS as $el) {
                $def->addAttribute($el, 'data-prm-type', 'Text');
                $def->addAttribute($el, 'data-prm-config', 'Text');
            }
        }

        $this->purifier = new \HTMLPurifier($config);
    }

    /**
     * Restituisce l'HTML ripulito. Mantiene null/'' invariati per non sovrascrivere
     * un template esistente quando il form non invia HTML.
     */
    public function sanitize(?string $html): ?string
    {
        if (!is_string($html)) {
            return null;
        }
        if (trim($html) === '') {
            return $html;
        }

        $clean = $this->purifier->purify($html);

        // Reincorpora gli eventuali blocchi <style> estratti e ripuliti.
        $styleBlocks = $this->purifier->context->get('StyleBlocks', true);
        if (is_array($styleBlocks) && $styleBlocks !== []) {
            $css = implode("\n", array_map('strval', $styleBlocks));
            if (trim($css) !== '') {
                $clean = '<style>' . $css . '</style>' . $clean;
            }
        }

        return $clean;
    }
}
