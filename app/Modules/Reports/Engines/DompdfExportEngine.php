<?php

declare(strict_types=1);

namespace App\Modules\Reports\Engines;

use App\Modules\Reports\Services\SmartComponentResolver;
use Dompdf\Dompdf;
use Dompdf\Options;

class DompdfExportEngine implements ExportEngineInterface
{
    private Dompdf $dompdf;
    private SmartComponentResolver $smartComponents;

    public function __construct()
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);

        $options = new Options();
        $options->set([
            // Loghi e immagini sono incorporati come data: URI da SmartComponentResolver,
            // quindi il fetch remoto resta disattivato (anti-SSRF): un template con
            // <img src="http://..."> non deve poter far partire richieste lato server.
            // Sbloccabile solo via env per chi accetta il rischio in ambienti fidati.
            'isRemoteEnabled' => (bool) env('REPORTS_PDF_ALLOW_REMOTE', false),
            'isPhpEnabled' => false,
            // Confina qualsiasi accesso a file locali entro public/: niente lettura di
            // .env, config o file fuori dalla webroot tramite url()/<img src> nei template.
            'chroot' => $basePath . '/public',
            'defaultFont' => 'Helvetica',
            'dpi' => 96,
            'fontHeight' => 14.4,
        ]);
        $this->dompdf = new Dompdf($options);
        $this->smartComponents = new SmartComponentResolver();
    }

    /**
     * Generate PDF from rows + column config.
     * Converts to HTML table and renders via Dompdf.
     */
    public function generate(array $rows, array $columns, string $outputPath, string $title = 'Report'): void
    {
        // Normalize columns
        $columns = $this->normalizeColumns($columns);

        // Build HTML
        $html = $this->buildHtmlTable($rows, $columns, $title);

        // Render and save
        $this->dompdf->loadHtml($html);
        $this->dompdf->setPaper('A4', 'landscape');
        $this->dompdf->render();

        file_put_contents($outputPath, $this->dompdf->output());
    }

    /**
     * Generate from HTML template (GrapeJS templates).
     * Merges data into HTML and renders to PDF.
     *
     * @param string $htmlTemplate   HTML template with {{ }} placeholders
     * @param array  $rows           Data rows to iterate/substitute
     * @param array  $meta           Metadata for header/footer
     * @param string $outputPath     Output file path
     */
    public function generateFromHtmlTemplate(string $htmlTemplate, array $rows, array $meta, string $outputPath): void
    {
        // 1. Expand Smart Components (data-prm-type) into real HTML.
        $stylePreset = $meta['style_preset'] ?? [];
        $expanded = $this->smartComponents->resolve($htmlTemplate, $rows, $meta, $stylePreset);

        // 2. Wrap fragment in a full HTML document with sensible defaults.
        $fullHtml = $this->wrapHtmlDocument($expanded, $meta, $stylePreset);

        // 3. Merge placeholders ({{ field }} and {{#items}}...{{/items}}).
        $html = $this->mergeDataIntoTemplate($fullHtml, $rows, $meta);

        $this->dompdf->loadHtml($html, 'UTF-8');
        $orientation = $meta['orientation'] ?? 'portrait';
        // Page margins are applied via an @page CSS rule in wrapHtmlDocument(),
        // because Dompdf's setPaper() ignores any margin argument.
        $this->dompdf->setPaper('A4', $orientation);

        $this->dompdf->render();

        file_put_contents($outputPath, $this->dompdf->output());
    }

    /**
     * Ensure the template fragment is rendered inside a well-formed HTML document.
     */
    private function wrapHtmlDocument(string $fragment, array $meta, array $stylePreset): string
    {
        // If the fragment already looks like a full document, don't wrap it.
        if (stripos($fragment, '<html') !== false) {
            return $fragment;
        }

        $title = htmlspecialchars((string) ($meta['title'] ?? 'Report'), ENT_QUOTES, 'UTF-8');

        // Defense in depth: the font stack is interpolated into CSS below, so it
        // must not be able to break out of the declaration (`;`/`}` injection).
        // A legitimate font-family value only uses these characters.
        $font = preg_replace('/[^A-Za-z0-9 ,\-\'"]/', '', (string) ($stylePreset['font_family'] ?? ''));
        if ($font === null || trim($font) === '') {
            $font = 'Helvetica, Arial, sans-serif';
        }

        // Clamp the base font size to a sane range to avoid rendering DoS.
        $size = (int) ($stylePreset['font_size_base'] ?? 10);
        if ($size < 6 || $size > 48) {
            $size = 10;
        }

        // Apply user-configured page margins via @page (Dompdf honors this).
        // When margins are set we zero the body margin so the @page rule alone
        // controls spacing; otherwise fall back to the default 10mm body margin.
        $pageCss = '';
        $bodyMargin = '10mm';
        if (!empty($meta['margins']) && is_array($meta['margins'])) {
            $m = $meta['margins'];
            $top    = (float) ($m['top'] ?? 10);
            $right  = (float) ($m['right'] ?? 10);
            $bottom = (float) ($m['bottom'] ?? 10);
            $left   = (float) ($m['left'] ?? 10);
            $pageCss = '@page{margin:' . $top . 'mm ' . $right . 'mm ' . $bottom . 'mm ' . $left . 'mm;}';
            $bodyMargin = '0';
        }

        $baseCss = $pageCss
            . 'body{font-family:' . $font . ';font-size:' . $size . 'pt;color:#0f172a;margin:' . $bodyMargin . ';}'
            . 'table{border-collapse:collapse;}'
            . 'img{max-width:100%;}';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . $title . '</title>'
            . '<style>' . $baseCss . '</style></head><body>' . $fragment . '</body></html>';
    }

    public function getContentType(): string
    {
        return 'application/pdf';
    }

    public function getExtension(): string
    {
        return 'pdf';
    }

    /**
     * Build a basic HTML table from rows + columns.
     */
    private function buildHtmlTable(array $rows, array $columns, string $title, array $stylePreset = []): string
    {
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<style>';
        $html .= 'body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; margin: 10mm; }';
        $html .= 'h1 { font-size: 16pt; margin-bottom: 5px; }';
        $html .= '.meta { font-size: 8pt; color: #666; margin-bottom: 10px; }';
        $html .= 'table { width: 100%; border-collapse: collapse; margin-top: 10px; }';
        $html .= 'th { background-color: ' . ($stylePreset['header_bg'] ?? '#f0f0f0') . '; padding: 6px; text-align: left; border: 1px solid #999; font-weight: bold; }';
        $html .= 'td { padding: 6px; border: 1px solid #ddd; }';
        $html .= 'tr:nth-child(even) { background-color: #f9f9f9; }';
        $html .= '</style></head><body>';

        $html .= '<h1>' . htmlspecialchars($title) . '</h1>';
        $html .= '<div class="meta">Generato il ' . date('d/m/Y H:i:s') . ' da ' . htmlspecialchars(auth()['name'] ?? 'Sistema') . '</div>';

        if (empty($columns)) {
            $html .= '</body></html>';
            return $html;
        }

        $html .= '<table>';
        $html .= '<thead><tr>';

        foreach ($columns as $col) {
            $html .= '<th>' . htmlspecialchars($col['label'] ?? $col['name']) . '</th>';
        }

        $html .= '</tr></thead>';
        $html .= '<tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($columns as $col) {
                $value = $row[$col['name']] ?? '';
                $html .= '<td>' . htmlspecialchars((string)$value) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</body></html>';

        return $html;
    }

    /**
     * Merge data into HTML template.
     * Supports {{ field_name }} replacements and basic {{ #items }}...{{ /items }} loops.
     */
    private function mergeDataIntoTemplate(string $htmlTemplate, array $rows, array $meta): string
    {
        $html = $htmlTemplate;

        // 1. Expand {{#items}}...{{/items}} loops first (any row count, including 1).
        $html = preg_replace_callback(
            '/\{\{\s*#items\s*\}\}([\s\S]+?)\{\{\s*\/items\s*\}\}/',
            function ($matches) use ($rows) {
                $block = $matches[1];
                if (empty($rows)) {
                    return '';
                }
                $out = '';
                foreach ($rows as $row) {
                    $out .= $this->substituteRow($block, (array) $row);
                }
                return $out;
            },
            $html
        );

        // 2. Replace metadata placeholders ({{ title }}, {{ generated_by }}, ...).
        foreach ($meta as $key => $value) {
            if (is_scalar($value)) {
                $html = str_replace('{{ ' . $key . ' }}', htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $html);
            }
        }

        // 3. For non-loop templates (documents), substitute first-row field placeholders.
        $firstRow = $rows[0] ?? [];
        if (is_array($firstRow) && !empty($firstRow)) {
            $html = $this->substituteRow($html, $firstRow);
        }

        // 4. Clean up any lingering {{ field }} placeholders.
        $html = preg_replace('/\{\{\s*[a-zA-Z_][a-zA-Z0-9_]*\s*\}\}/', '', $html) ?? $html;

        return $html;
    }

    /**
     * Substitute {{ field }} placeholders for a single row.
     */
    private function substituteRow(string $block, array $row): string
    {
        foreach ($row as $fieldName => $fieldValue) {
            if (!is_scalar($fieldValue) && $fieldValue !== null) {
                continue;
            }
            $block = str_replace(
                '{{ ' . $fieldName . ' }}',
                htmlspecialchars((string) $fieldValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $block
            );
        }
        return $block;
    }

    /**
     * Normalize column definitions to standard format.
     */
    private function normalizeColumns(array $columns): array
    {
        return array_map(static function ($col) {
            if (is_string($col)) {
                return ['name' => $col, 'label' => ucfirst($col), 'type' => 'string'];
            }
            return array_merge([
                'name' => '',
                'label' => '',
                'type' => 'string',
                'align' => 'left',
                'width' => 0,
            ], (array)$col);
        }, $columns);
    }

}
