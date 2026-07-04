<?php

declare(strict_types=1);

namespace App\Modules\Blog\Services;

use App\Security\Sanitizer;

class BlogContentSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr',
        'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup', 'mark', 'small', 'code',
        'a', 'span',
        'ul', 'ol', 'li',
        'blockquote', 'pre',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'img', 'figure', 'figcaption',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'div',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'a'    => ['href', 'title', 'target', 'rel'],
        'img'  => ['src', 'alt', 'title', 'width', 'height'],
        'th'   => ['colspan', 'rowspan'],
        'td'   => ['colspan', 'rowspan'],
        '*'    => ['class', 'id'],
    ];

    public static function sanitize(string $html): string
    {
        $clean = Sanitizer::sanitizeHtml($html);
        if ($clean === '') {
            return '';
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div id="__bw__">' . $clean . '</div></body></html>',
            LIBXML_NOWARNING | LIBXML_NOERROR
        );
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        $xpath = new \DOMXPath($dom);
        $wrapper = $dom->getElementById('__bw__');
        if (!$wrapper) {
            return '';
        }

        foreach (iterator_to_array($xpath->query('.//*', $wrapper)) as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }
            $tagName = strtolower($element->nodeName);

            if (!in_array($tagName, self::ALLOWED_TAGS, true)) {
                self::unwrapElement($element);
                continue;
            }

            $allowedAttrs = array_unique(array_merge(
                self::ALLOWED_ATTRIBUTES['*'],
                self::ALLOWED_ATTRIBUTES[$tagName] ?? []
            ));

            $attrsToRemove = [];
            foreach ($element->attributes as $attr) {
                if (!in_array(strtolower($attr->name), $allowedAttrs, true)) {
                    $attrsToRemove[] = $attr->name;
                }
            }
            foreach ($attrsToRemove as $name) {
                $element->removeAttribute($name);
            }

            if ($tagName === 'a' && $element->hasAttribute('target') && $element->getAttribute('target') === '_blank') {
                $element->setAttribute('rel', 'noopener noreferrer');
            }
        }

        $inner = '';
        foreach ($wrapper->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }
        return $inner;
    }

    private static function unwrapElement(\DOMElement $element): void
    {
        $parent = $element->parentNode;
        if (!$parent) {
            return;
        }
        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }
}
