<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

/**
 * Expands Smart Components (elements tagged with data-prm-type) in a GrapesJS
 * HTML template into rendered HTML, using the supplied rows/meta/style context.
 *
 * Supported types: data_table, calculated, system, filters_summary, logo.
 * Any other type is left untouched so downstream placeholder merging still runs.
 */
class SmartComponentResolver
{
    /**
     * Resolve Smart Components inside the template HTML and return the expanded HTML.
     */
    public function resolve(string $html, array $rows, array $meta, array $stylePreset): string
    {
        if ($html === '' || stripos($html, 'data-prm-type') === false) {
            return $html;
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);

        // Wrap so libxml keeps the full fragment and handles UTF-8 correctly.
        $wrapped = '<?xml encoding="UTF-8"?><div id="__prm_root__">' . $html . '</div>';
        $loaded  = $doc->loadHTML($wrapped, LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        if (!$loaded) {
            return $html;
        }

        $xpath = new \DOMXPath($doc);
        $nodes = $xpath->query('//*[@data-prm-type]');
        if ($nodes === false || $nodes->length === 0) {
            return $html;
        }

        // Iterate over a static snapshot because we mutate the DOM while walking.
        $targets = [];
        foreach ($nodes as $node) {
            $targets[] = $node;
        }

        foreach ($targets as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $type   = $node->getAttribute('data-prm-type');
            $config = $this->decodeConfig($node->getAttribute('data-prm-config'));
            $replacementHtml = $this->renderComponent($type, $config, $rows, $meta, $stylePreset);

            if ($replacementHtml === null) {
                continue;
            }

            $this->replaceNodeWithHtml($doc, $node, $replacementHtml);
        }

        $root = $doc->getElementById('__prm_root__');
        if ($root === null) {
            return $html;
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    private function decodeConfig(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function renderComponent(string $type, array $config, array $rows, array $meta, array $stylePreset): ?string
    {
        switch ($type) {
            case 'data_table':
                return $this->renderDataTable($config, $rows, $meta, $stylePreset);
            case 'calculated':
                return $this->renderCalculated($config, $rows);
            case 'system':
                return $this->renderSystem($config, $meta);
            case 'filters_summary':
                return $this->renderFiltersSummary($config, $meta);
            case 'logo':
                return $this->renderLogo($config, $stylePreset);
        }
        return null;
    }

    private function renderDataTable(array $config, array $rows, array $meta, array $stylePreset): string
    {
        $sourceFields = $meta['source_fields'] ?? [];
        $fieldMap = [];
        foreach ($sourceFields as $f) {
            $name = $f['name'] ?? $f['key'] ?? null;
            if ($name === null) {
                continue;
            }
            $fieldMap[$name] = $f;
        }

        // Columns: explicit config['columns'] OR all source fields.
        $columns = [];
        if (!empty($config['columns']) && is_array($config['columns'])) {
            foreach ($config['columns'] as $col) {
                $name  = is_array($col) ? ($col['name'] ?? null) : (is_string($col) ? $col : null);
                if ($name === null) {
                    continue;
                }
                $label = is_array($col) ? ($col['label'] ?? ($fieldMap[$name]['label'] ?? ucfirst($name))) : ($fieldMap[$name]['label'] ?? ucfirst($name));
                $align = is_array($col) ? ($col['align'] ?? 'left') : 'left';
                $columns[] = ['name' => $name, 'label' => $label, 'align' => $align];
            }
        } else {
            foreach ($fieldMap as $name => $f) {
                $columns[] = ['name' => $name, 'label' => $f['label'] ?? ucfirst($name), 'align' => 'left'];
            }
        }

        if (empty($columns)) {
            return '<div class="prm-empty" style="padding:12px;color:#6c757d;font-size:12px;">Nessuna colonna selezionata.</div>';
        }

        $headerBg = $stylePreset['header_bg_color'] ?? '#1e293b';
        $headerFg = $stylePreset['header_text_color'] ?? '#ffffff';
        $zebra    = $stylePreset['zebra_color'] ?? '#f8fafc';
        $striped  = (bool) ($config['striped'] ?? true);
        $bordered = (bool) ($config['bordered'] ?? true);

        $html  = '<table class="prm-data-table" style="width:100%;border-collapse:collapse;font-size:11pt;">';
        $html .= '<thead><tr>';
        foreach ($columns as $col) {
            $html .= '<th style="padding:6px 8px;text-align:' . $this->esc($col['align']) . ';background:' . $this->esc($headerBg) . ';color:' . $this->esc($headerFg) . ';' . ($bordered ? 'border:1px solid ' . $this->esc($headerBg) . ';' : '') . '">'
                . $this->esc($col['label']) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        $i = 0;
        foreach ($rows as $row) {
            $bg = ($striped && ($i % 2 === 1)) ? $zebra : '';
            $html .= '<tr' . ($bg ? ' style="background:' . $this->esc($bg) . ';"' : '') . '>';
            foreach ($columns as $col) {
                $value = $row[$col['name']] ?? '';
                $cellStyle = 'padding:6px 8px;text-align:' . $this->esc($col['align']) . ';'
                    . ($bordered ? 'border:1px solid #dee2e6;' : '');
                $html .= '<td style="' . $cellStyle . '">' . $this->esc((string) $value) . '</td>';
            }
            $html .= '</tr>';
            $i++;
        }

        if ($i === 0) {
            $html .= '<tr><td colspan="' . count($columns) . '" style="padding:12px;text-align:center;color:#6c757d;font-style:italic;">Nessun dato disponibile.</td></tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    private function renderCalculated(array $config, array $rows): string
    {
        $op      = strtolower((string) ($config['op'] ?? 'count'));
        $field   = (string) ($config['field'] ?? '');
        $label   = (string) ($config['label'] ?? '');
        $format  = (string) ($config['format'] ?? 'number');
        $decimals = (int) ($config['decimals'] ?? 2);

        $value = 0.0;

        switch ($op) {
            case 'count':
                $value = count($rows);
                break;
            case 'sum':
            case 'avg':
            case 'min':
            case 'max':
                $nums = [];
                foreach ($rows as $r) {
                    if (!array_key_exists($field, $r)) {
                        continue;
                    }
                    $v = $r[$field];
                    if (is_numeric($v)) {
                        $nums[] = (float) $v;
                    }
                }
                if ($op === 'sum') {
                    $value = array_sum($nums);
                } elseif ($op === 'avg') {
                    $value = empty($nums) ? 0 : array_sum($nums) / count($nums);
                } elseif ($op === 'min') {
                    $value = empty($nums) ? 0 : min($nums);
                } elseif ($op === 'max') {
                    $value = empty($nums) ? 0 : max($nums);
                }
                break;
        }

        $formatted = $this->formatNumber($value, $format, $decimals);

        if ($label === '') {
            return '<span class="prm-calculated">' . $this->esc($formatted) . '</span>';
        }

        return '<span class="prm-calculated"><span class="prm-calculated-label" style="color:#64748b;margin-right:4px;">' . $this->esc($label) . ':</span><strong>' . $this->esc($formatted) . '</strong></span>';
    }

    private function renderSystem(array $config, array $meta): string
    {
        $kind = strtolower((string) ($config['kind'] ?? ''));
        switch ($kind) {
            case 'date':
                return $this->esc(date('d/m/Y'));
            case 'datetime':
                return $this->esc(date('d/m/Y H:i'));
            case 'time':
                return $this->esc(date('H:i'));
            case 'user':
                return $this->esc((string) ($meta['generated_by'] ?? ''));
            case 'company':
                return $this->esc((string) ($meta['company_name'] ?? ''));
            case 'title':
                return $this->esc((string) ($meta['title'] ?? ''));
            case 'module':
                return $this->esc((string) ($meta['module'] ?? ''));
            case 'row_count':
                return (string) count($meta['__rows'] ?? []);
        }
        return '';
    }

    private function renderFiltersSummary(array $config, array $meta): string
    {
        $filters = $meta['filters'] ?? [];
        if (empty($filters) || !is_array($filters)) {
            $empty = $config['empty_label'] ?? 'Nessun filtro applicato.';
            return '<div class="prm-filters-empty" style="color:#64748b;font-style:italic;font-size:10pt;">' . $this->esc((string) $empty) . '</div>';
        }

        $title = (string) ($config['title'] ?? 'Filtri applicati');

        $html  = '<div class="prm-filters" style="font-size:10pt;">';
        if ($title !== '') {
            $html .= '<div class="prm-filters-title" style="font-weight:600;margin-bottom:4px;">' . $this->esc($title) . '</div>';
        }
        $html .= '<ul style="margin:0;padding-left:18px;">';
        foreach ($filters as $k => $v) {
            if ($v === '' || $v === null) {
                continue;
            }
            $val = is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
            $html .= '<li><strong>' . $this->esc((string) $k) . ':</strong> ' . $this->esc($val) . '</li>';
        }
        $html .= '</ul></div>';
        return $html;
    }

    private function renderLogo(array $config, array $stylePreset): string
    {
        $path = $stylePreset['logo_path'] ?? ($config['path'] ?? null);
        if (!$path) {
            return '';
        }

        $maxHeight = (int) ($config['max_height'] ?? 60);
        $align     = (string) ($config['align'] ?? 'left');
        $src       = $this->resolveLogoSrc((string) $path);
        if ($src === '') {
            return '';
        }

        $wrapStyle = 'text-align:' . $this->esc($align) . ';';
        $imgStyle  = 'max-height:' . $maxHeight . 'px;height:auto;';

        return '<div class="prm-logo" style="' . $wrapStyle . '"><img src="' . $this->esc($src) . '" style="' . $imgStyle . '" alt="Logo"></div>';
    }

    /**
     * Resolve a logo reference into a Dompdf-friendly src.
     *
     * I loghi caricati risiedono in public/uploads/reports/. Vengono restituiti come
     * data: URI (base64 inline) così Dompdf li renderizza senza fetch remoto abilitato.
     * Gli schemi remoti/locali (http(s)/file/phar/...) sono rifiutati: il path del logo
     * può arrivare dal data-prm-config di un template, e consentirli aprirebbe a SSRF
     * (fetch lato server) o a path traversal (lettura di file fuori dalla webroot).
     */
    private function resolveLogoSrc(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        // data: URI già inline → sicuro da incorporare così com'è.
        if (preg_match('/^data:/i', $path)) {
            return $path;
        }
        // Qualsiasi schema (http://, https://, file://, phar://, ...) → rifiutato.
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $path)) {
            return '';
        }
        $rel = ltrim(str_replace('\\', '/', $path), '/');
        if ($rel === '' || str_contains($rel, '..') || str_contains($rel, "\0")) {
            return '';
        }
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $candidates = [
            $basePath . '/public/uploads/reports/' . basename($rel),
            $basePath . '/public/' . $rel,
        ];
        foreach ($candidates as $full) {
            if (is_file($full)) {
                return $this->fileToDataUri($full);
            }
        }
        return '';
    }

    /**
     * Read a local image file and encode it as a base64 data: URI.
     */
    private function fileToDataUri(string $fullPath): string
    {
        $data = @file_get_contents($fullPath);
        if ($data === false) {
            return '';
        }
        $mime = 'image/png';
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($fullPath);
        if (is_string($detected) && str_starts_with($detected, 'image/')) {
            $mime = $detected;
        }
        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    private function replaceNodeWithHtml(\DOMDocument $doc, \DOMElement $node, string $html): void
    {
        $fragment = $doc->createDocumentFragment();
        // appendXML wants well-formed XML, but we have HTML fragments. Use a helper doc instead.
        $tmp = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $tmp->loadHTML('<?xml encoding="UTF-8"?><div id="__prm_tmp__">' . $html . '</div>', LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $tmpRoot = $tmp->getElementById('__prm_tmp__');
        if ($tmpRoot === null) {
            $node->parentNode?->removeChild($node);
            return;
        }

        $parent = $node->parentNode;
        if ($parent === null) {
            return;
        }

        foreach (iterator_to_array($tmpRoot->childNodes) as $child) {
            $imported = $doc->importNode($child, true);
            $parent->insertBefore($imported, $node);
        }
        $parent->removeChild($node);

        // Suppress "unused" warning on $fragment.
        unset($fragment);
    }

    private function formatNumber(float $value, string $format, int $decimals): string
    {
        switch ($format) {
            case 'currency':
                return '€ ' . number_format($value, max(0, $decimals), ',', '.');
            case 'integer':
                return number_format($value, 0, ',', '.');
            case 'percent':
                return number_format($value, max(0, $decimals), ',', '.') . '%';
            case 'number':
            default:
                return number_format($value, max(0, $decimals), ',', '.');
        }
    }

    private function esc(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
