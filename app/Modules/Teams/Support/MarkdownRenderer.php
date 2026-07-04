<?php

declare(strict_types=1);

namespace App\Modules\Teams\Support;

use App\Security\Sanitizer;

/**
 * Render markdown leggero per i messaggi Teams.
 *
 * Sintassi supportata (sottoinsieme controllato, parsing locale, niente librerie):
 *   - ```code block``` (multi-riga, su righe dedicate)
 *   - `inline code`
 *   - **bold**
 *   - *italic*
 *   - > quote (a inizio riga)
 *   - https://... → link auto rel="noopener nofollow" target="_blank"
 *   - @mention → <span class="tm-mention">@nome</span>
 *
 * Workflow:
 *   1. Estrae i code block ```...``` come placeholder per evitare formatting interno.
 *   2. Estrae il code inline `...` con placeholder simile.
 *   3. Escape HTML su tutto il resto (htmlspecialchars).
 *   4. Applica i pattern restanti (bold, italic, quote, link, mention).
 *   5. Reinserisce i blocchi code (anch'essi escapati).
 *   6. nl2br finale + `Sanitizer::sanitizeHtml()` di sicurezza.
 */
class MarkdownRenderer
{
    public static function render(string $body): string
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return '';
        }

        // Step 0: rendering "big emoji" stile Telegram — se il messaggio è una
        // singola emoji isolata (1 grapheme cluster, nessun ASCII), wrappa il
        // glifo in uno span dedicato che il CSS ingrandisce a 3em.
        if (self::isSingleEmoji($trimmed)) {
            $safe = htmlspecialchars($trimmed, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return Sanitizer::sanitizeHtml('<span class="tm-big-emoji">' . $safe . '</span>');
        }

        $codeBlocks  = [];
        $inlineCodes = [];

        // 1. ```...``` code blocks (multi-line)
        $body = preg_replace_callback(
            '/```([a-zA-Z0-9_+\-]*)\n?(.+?)```/s',
            static function (array $m) use (&$codeBlocks): string {
                $lang = trim($m[1]);
                $codeBlocks[] = [
                    'lang' => $lang,
                    'code' => $m[2],
                ];
                return "\x01CODEBLOCK_" . (count($codeBlocks) - 1) . "\x01";
            },
            $body
        ) ?? $body;

        // 2. `inline code`
        $body = preg_replace_callback(
            '/`([^`\n]+)`/',
            static function (array $m) use (&$inlineCodes): string {
                $inlineCodes[] = $m[1];
                return "\x01INLINECODE_" . (count($inlineCodes) - 1) . "\x01";
            },
            $body
        ) ?? $body;

        // 3. Escape HTML
        $body = htmlspecialchars($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 4. Block-level: blockquote (riga che inizia con `> `)
        $body = preg_replace_callback(
            '/^(?:&gt; ?)(.*)$/m',
            static fn (array $m): string => '<blockquote class="tm-md-quote">' . $m[1] . '</blockquote>',
            $body
        ) ?? $body;

        // 5. Bold **...**
        $body = preg_replace('/\*\*([^*\n]+?)\*\*/', '<strong>$1</strong>', $body) ?? $body;
        // 6. Italic *...*
        $body = preg_replace('/(?<!\*)\*([^*\n]+?)\*(?!\*)/', '<em>$1</em>', $body) ?? $body;

        // 7. URLs auto-link (http/https/www)
        $body = preg_replace_callback(
            TeamsLinkExtractor::URL_PATTERN,
            static function (array $m): string {
                $url   = $m[1];
                $href  = str_starts_with($url, 'www.') ? 'https://' . $url : $url;
                // Trim trailing punctuation: . , ; ) ] !
                $trail = '';
                while ($url !== '' && in_array(substr($url, -1), ['.', ',', ';', ')', ']', '!', '?'], true)) {
                    $trail = substr($url, -1) . $trail;
                    $url = substr($url, 0, -1);
                    $href = str_starts_with($url, 'www.') ? 'https://' . $url : $url;
                }
                return '<a href="' . $href . '" target="_blank" rel="noopener nofollow">' . $url . '</a>' . $trail;
            },
            $body
        ) ?? $body;

        // 8. Mentions @nome
        $body = preg_replace(
            '/(?<![\w@])@([\p{L}0-9_.\-]+)/u',
            '<span class="tm-mention">@$1</span>',
            $body
        ) ?? $body;

        // 9. Reinserisci code block (escape interno)
        $body = preg_replace_callback(
            "/\x01CODEBLOCK_(\d+)\x01/",
            static function (array $m) use ($codeBlocks): string {
                $b = $codeBlocks[(int) $m[1]] ?? null;
                if ($b === null) {
                    return '';
                }
                $code = htmlspecialchars($b['code'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $langClass = $b['lang'] !== '' ? ' tm-md-lang-' . preg_replace('/[^a-z0-9_+\-]/i', '', $b['lang']) : '';
                return '<pre class="tm-md-codeblock"><code class="' . trim($langClass) . '">' . $code . '</code></pre>';
            },
            $body
        ) ?? $body;

        // 10. Reinserisci inline code
        $body = preg_replace_callback(
            "/\x01INLINECODE_(\d+)\x01/",
            static function (array $m) use ($inlineCodes): string {
                $code = $inlineCodes[(int) $m[1]] ?? '';
                return '<code class="tm-md-inlinecode">' . htmlspecialchars($code, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</code>';
            },
            $body
        ) ?? $body;

        // 11. nl2br finale (preserva le interruzioni di riga)
        $body = nl2br($body, false);

        // 12. Sanitize di sicurezza
        return Sanitizer::sanitizeHtml($body);
    }

    /**
     * True se la stringa è composta da una sola emoji isolata.
     *
     * Usa il match `\X` PCRE per identificare un singolo extended grapheme
     * cluster — gestisce correttamente ZWJ sequences (👨‍👩‍👧),
     * variation selectors (❤️) e skin tone modifiers (👍🏽). Il filtro ASCII
     * scarta lettere/cifre/punteggiatura latine, e il check finale sui range
     * Unicode evita falsi positivi (simboli matematici, frecce, ecc.).
     */
    private static function isSingleEmoji(string $s): bool
    {
        if (strlen($s) > 64) {
            return false;
        }
        if (preg_match('/[\x20-\x7E]/', $s)) {
            return false;
        }
        if (preg_match('/^\X$/u', $s) !== 1) {
            return false;
        }
        return preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{1F1E6}-\x{1F1FF}]/u', $s) === 1;
    }
}
