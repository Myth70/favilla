<?php

declare(strict_types=1);

namespace App\Security;

class Sanitizer
{
    /**
     * Trim and strip tags. Optionally truncate to a maximum length (UTF-8 aware).
     *
     * @param int $maxLength  0 = no limit (default). When > 0, the returned string
     *                        is at most $maxLength characters.
     */
    public static function clean(string $input, int $maxLength = 0): string
    {
        $clean = trim(strip_tags($input));
        if ($maxLength > 0 && mb_strlen($clean, 'UTF-8') > $maxLength) {
            $clean = mb_substr($clean, 0, $maxLength, 'UTF-8');
        }
        return $clean;
    }

    /**
     * Sanitize email address.
     */
    public static function email(string $input): string
    {
        // FILTER_SANITIZE_EMAIL rimuove caratteri silenziosamente (es. + in mario+tag@gmail.com)
        // Usiamo FILTER_VALIDATE_EMAIL: se non è email valida, stringa vuota
        $trimmed = trim($input);
        return filter_var($trimmed, FILTER_VALIDATE_EMAIL) ? $trimmed : '';
    }

    /**
     * Sanitize to integer.
     */
    public static function int(mixed $input): int
    {
        return intval($input);
    }

    /**
     * Escape HTML for safe output.
     */
    public static function html(string $input): string
    {
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize a hex color value. Returns default if invalid.
     */
    public static function color(string $input, string $default = '#3b82f6'): string
    {
        $input = trim($input);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $input) ? $input : $default;
    }

    /**
     * Sanitize rich HTML content (e.g. from Quill editor).
     * Strips <script>, <style>, <iframe> and dangerous event-handler/protocol attributes.
     * Preserves all formatting and structural HTML tags.
     */
    public static function sanitizeHtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div id="__sw__">' . $html . '</div></body></html>',
            LIBXML_NOWARNING | LIBXML_NOERROR
        );
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        // Remove dangerous block-level elements entirely
        foreach (['script', 'style', 'iframe', 'object', 'embed', 'applet', 'base', 'form', 'input', 'button', 'select', 'textarea', 'svg', 'math'] as $tag) {
            $nodes = $dom->getElementsByTagName($tag);
            while ($nodes->length > 0) {
                $node = $nodes->item(0);
                $node->parentNode->removeChild($node);
            }
        }

        // Walk all remaining elements and strip dangerous attributes
        $xpath    = new \DOMXPath($dom);
        $elements = $xpath->query('//*');
        foreach ($elements as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }
            $attrsToRemove = [];
            foreach ($element->attributes as $attr) {
                $name    = strtolower($attr->name);
                $value   = $attr->value;
                // Strip event handlers: onclick, onload, onerror, etc.
                if (str_starts_with($name, 'on')) {
                    $attrsToRemove[] = $attr->name;
                    continue;
                }
                // Strip javascript:/vbscript: protocols in URL attributes
                if (in_array($name, ['href', 'src', 'action', 'formaction', 'data', 'poster'], true)) {
                    $stripped = strtolower(preg_replace('/\s+/', '', $value));
                    if (
                        str_starts_with($stripped, 'javascript:') ||
                        str_starts_with($stripped, 'vbscript:') ||
                        str_starts_with($stripped, 'data:text/html')
                    ) {
                        $attrsToRemove[] = $attr->name;
                    }
                }
            }
            foreach ($attrsToRemove as $attrName) {
                $element->removeAttribute($attrName);
            }
        }

        // Extract inner HTML of the wrapper div
        $wrapper = $dom->getElementById('__sw__');
        if (!$wrapper) {
            return '';
        }
        $inner = '';
        foreach ($wrapper->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }

        return $inner;
    }
}
