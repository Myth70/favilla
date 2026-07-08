<?php

declare(strict_types=1);

namespace App\Modules\Blog\Services;

/**
 * Sanitizza il contenuto HTML (Quill editor) degli articoli Blog prima della
 * persistenza.
 *
 * Sostituisce il precedente sanitizer DOMDocument fatto in casa con
 * HTMLPurifier, già in uso per lo stesso tipo di rischio in
 * Reports\TemplateHtmlSanitizer (HTML autorato da un utente, riaperto e reso
 * per altri utenti). Stesso allowlist di tag/attributi del sanitizer
 * precedente — nessun cambio di comportamento per il contenuto legittimo
 * (allineamenti/indentazioni Quill via `class` restano ammessi su ogni tag).
 */
class BlogContentSanitizer
{
    /** Elementi HTML5 non previsti dal doctype di default di HTMLPurifier: nome => [tipo, contenuto]. */
    private const HTML5_ELEMENTS = [
        'mark'       => ['Inline', 'Inline'],
        'figure'     => ['Block', 'Flow'],
        'figcaption' => ['Block', 'Flow'],
    ];

    private \HTMLPurifier $purifier;

    public function __construct()
    {
        $config = \HTMLPurifier_Config::createDefault();

        $config->set('Core.Encoding', 'UTF-8');
        // Salvataggio articolo = operazione poco frequente → niente cache su
        // disco (evita dipendenze da permessi di scrittura).
        $config->set('Cache.DefinitionImpl', null);

        $config->set('HTML.AllowedElements', [
            'p', 'br', 'hr',
            'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup', 'mark', 'small', 'code',
            'a', 'span',
            'ul', 'ol', 'li',
            'blockquote', 'pre',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'img', 'figure', 'figcaption',
            'table', 'thead', 'tbody', 'tr', 'th', 'td',
            'div',
        ]);
        $config->set('HTML.AllowedAttributes', [
            'a.href', 'a.title', 'a.target', 'a.rel',
            'img.src', 'img.alt', 'img.title', 'img.width', 'img.height',
            'th.colspan', 'th.rowspan', 'td.colspan', 'td.rowspan',
            '*.class', '*.id',
        ]);
        $config->set('Attr.EnableID', true);
        $config->set('Attr.AllowedFrameTargets', ['_blank' => true]);
        $config->set('URI.AllowedSchemes', [
            'http'   => true,
            'https'  => true,
            'mailto' => true,
            'data'   => true,
        ]);

        $config->set('HTML.DefinitionID', 'favilla-blog-article');
        $config->set('HTML.DefinitionRev', 1);

        $def = $config->maybeGetRawHTMLDefinition();
        if ($def !== null) {
            foreach (self::HTML5_ELEMENTS as $el => [$type, $contents]) {
                $def->addElement($el, $type, $contents, 'Common');
            }
        }

        $this->purifier = new \HTMLPurifier($config);
    }

    public static function sanitize(string $html): string
    {
        static $instance = null;
        $instance ??= new self();

        if (trim($html) === '') {
            return '';
        }

        $clean = $instance->purifier->purify($html);

        return self::hardenBlankTargets($clean);
    }

    /**
     * I link target="_blank" devono portare rel="noopener noreferrer"
     * (protezione reverse tabnabbing) indipendentemente da quanto autorato.
     */
    private static function hardenBlankTargets(string $html): string
    {
        if (!str_contains($html, '_blank')) {
            return $html;
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div id="__bw__">' . $html . '</div></body></html>',
            LIBXML_NOWARNING | LIBXML_NOERROR
        );
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        $wrapper = $dom->getElementById('__bw__');
        if (!$wrapper) {
            return $html;
        }

        foreach (iterator_to_array($dom->getElementsByTagName('a')) as $link) {
            if ($link instanceof \DOMElement && $link->getAttribute('target') === '_blank') {
                $link->setAttribute('rel', 'noopener noreferrer');
            }
        }

        $inner = '';
        foreach ($wrapper->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }
        return $inner;
    }
}
