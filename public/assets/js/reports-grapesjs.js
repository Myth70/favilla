/**
 * GrapeJS Report Designer - Full-featured initialization
 * GrapesJS 0.22.x compatible
 */
(function () {
    'use strict';

    var saveIndicatorTimeout = null;
    var lastIndicatorAt = 0;

    /* Helpers */
    function blockLabel(iconClass, text) {
        return '<div class="rp-block-label"><i class="fa-solid ' + iconClass + '"></i><span>' + text + '</span></div>';
    }
    function debounce(fn, delay) {
        var t;
        return function () {
            var args = arguments, ctx = this;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(ctx, args); }, delay);
        };
    }
    function showSaveIndicator(message) {
        message = message || t('js.reports.save.saved', 'Salvato');
        var indicator = document.getElementById('save-indicator');
        if (!indicator) return;
        var now = Date.now();
        if (now - lastIndicatorAt < 1000) return;
        lastIndicatorAt = now;
        indicator.textContent = '\u2713 ' + message;
        indicator.classList.add('is-visible');
        if (saveIndicatorTimeout) clearTimeout(saveIndicatorTimeout);
        saveIndicatorTimeout = setTimeout(function () { indicator.classList.remove('is-visible'); }, 1200);
    }
    function notifyDesigner(message, type, options) {
        if (typeof window.notify === 'function') {
            window.notify(Object.assign({
                message: message,
                type: type || 'info',
                source: 'reports-designer'
            }, options || {}));
            return;
        }

        console.warn('[reports-designer]', message);
    }
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
    function formatHtml(html) {
        var formatted = '', indent = 0;
        var tags = html.replace(/></g, '>\n<').split('\n');
        tags.forEach(function (tag) {
            tag = tag.trim();
            if (!tag) return;
            if (tag.match(/^<\/(div|section|table|thead|tbody|tfoot|tr|ul|ol|header|footer|main|article|nav)/i)) indent = Math.max(0, indent - 1);
            formatted += '  '.repeat(indent) + tag + '\n';
            if (tag.match(/^<(div|section|table|thead|tbody|tfoot|tr|ul|ol|header|footer|main|article|nav)[\s>]/i) && !tag.match(/\/>/)) indent++;
        });
        return formatted;
    }

    /* Boot */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initReportsDesigner);
    } else {
        initReportsDesigner();
    }

    function initReportsDesigner() {
        var editorContainer = document.getElementById('grapesjs-editor');
        if (!editorContainer) return;
        if (typeof window.grapesjs === 'undefined') {
            editorContainer.innerHTML = '<div class="alert alert-danger m-3">' + t('js.reports.designer_not_loaded', 'GrapeJS non caricato. Ricarica la pagina.') + '</div>';
            return;
        }

        var templateId = document.getElementById('template-id');
        var storagePrefix = 'gjs-rp-' + (templateId ? templateId.value : 'new') + '-';

        var bootstrapCss = editorContainer.getAttribute('data-bootstrap-css') || '/assets/css/bootstrap.min.css';

        var editor = grapesjs.init({
            container: editorContainer,
            fromElement: false,
            height: '100%',
            width: 'auto',
            panels: { defaults: [] },
            storageManager: false,
            deviceManager: {
                devices: [
                    { name: 'Desktop', width: '' },
                    { name: 'Tablet', width: '768px', widthMedia: '992px' },
                    { name: 'Mobile', width: '375px', widthMedia: '480px' },
                ]
            },
            blockManager: { appendTo: '#grapesjs-blocks', blocks: [] },
            styleManager: { appendTo: '#grapesjs-styles', sectors: buildStyleSectors() },
            traitManager: { appendTo: '#grapesjs-traits' },
            layerManager: { appendTo: '#grapesjs-layers' },
            canvas: {
                styles: [bootstrapCss],
            },
            undoManager: { maximumStackLength: 50 },
            selectorManager: { appendTo: '#grapesjs-selectors', componentFirst: true },
        });

        window.__grapesjs_editor = editor;
        registerAllBlocks(editor);
        registerSmartComponents(editor);
        setupDataPersistence(editor);
        setupToolbar(editor);
        setupPanelTabs(editor);
        setupMergeFieldHelper(editor);
        setupMergeFieldsSearch();
        setupMergeFieldsDrag(editor);
        setupRichTextEditor(editor);
        setupMentionAutocomplete(editor);
        setupFullscreen();
        setupComponentToolbar(editor);
        setupCanvasKeyboard(editor);
        setupStylePresetModal();
        setupDocumentBindings();
        try { editor.runCommand('open-sm'); } catch (e) {}
    }

    /* Style Sectors */
    function buildStyleSectors() {
        return [
            {
                name: t('js.reports.style_sector.layout', 'Layout'), open: true,
                buildProps: ['display', 'position', 'float', 'clear', 'overflow'],
                properties: [{
                    name: t('js.reports.style_prop.display', 'Display'), property: 'display', type: 'select', defaults: 'block',
                    list: [{ value: 'block' }, { value: 'inline' }, { value: 'inline-block' }, { value: 'flex' }, { value: 'grid' }, { value: 'none' }, { value: 'table' }, { value: 'table-row' }, { value: 'table-cell' }]
                }],
            },
            {
                name: t('js.reports.style_sector.flex_grid', 'Flex / Grid'), open: false,
                buildProps: ['flex-direction', 'flex-wrap', 'justify-content', 'align-items', 'align-content', 'align-self', 'flex-grow', 'flex-shrink', 'flex-basis', 'order', 'gap', 'row-gap', 'column-gap'],
            },
            {
                name: t('js.reports.style_sector.dimensions', 'Dimensioni'), open: true,
                buildProps: ['width', 'min-width', 'max-width', 'height', 'min-height', 'max-height', 'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left'],
            },
            {
                name: t('js.reports.style_sector.typography', 'Tipografia'), open: true,
                buildProps: ['font-family', 'font-size', 'font-weight', 'font-style', 'color', 'line-height', 'letter-spacing', 'text-align', 'text-decoration', 'text-transform', 'text-indent', 'white-space', 'word-spacing'],
                properties: [
                    { name: 'Font', property: 'font-family', type: 'select', defaults: 'Arial, sans-serif',
                      list: [
                        { name: 'Arial', value: 'Arial, Helvetica, sans-serif' },
                        { name: 'Helvetica Neue', value: '"Helvetica Neue", Helvetica, sans-serif' },
                        { name: 'Georgia', value: 'Georgia, "Times New Roman", serif' },
                        { name: 'Times New Roman', value: '"Times New Roman", Times, serif' },
                        { name: 'Courier New', value: '"Courier New", Courier, monospace' },
                        { name: 'Verdana', value: 'Verdana, Geneva, sans-serif' },
                        { name: 'Trebuchet MS', value: '"Trebuchet MS", Helvetica, sans-serif' },
                        { name: 'Segoe UI', value: '"Segoe UI", Tahoma, Geneva, sans-serif' },
                        { name: 'System UI', value: 'system-ui, -apple-system, sans-serif' },
                      ]
                    },
                    { name: t('js.reports.style_prop.weight', 'Peso'), property: 'font-weight', type: 'select', defaults: '400',
                      list: [{ name: 'Thin (100)', value: '100' }, { name: 'Light (300)', value: '300' }, { name: 'Normal (400)', value: '400' }, { name: 'Medium (500)', value: '500' }, { name: 'Semibold (600)', value: '600' }, { name: 'Bold (700)', value: '700' }, { name: 'Extra Bold (800)', value: '800' }, { name: 'Black (900)', value: '900' }]
                    },
                    { name: t('js.reports.style_prop.alignment', 'Allineamento'), property: 'text-align', type: 'radio', defaults: 'left',
                      list: [{ value: 'left', name: t('js.reports.align.left', 'Sx'), className: 'fa fa-align-left' }, { value: 'center', name: t('js.reports.align.center', 'Centro'), className: 'fa fa-align-center' }, { value: 'right', name: t('js.reports.align.right', 'Dx'), className: 'fa fa-align-right' }, { value: 'justify', name: t('js.reports.align.justify', 'Giust.'), className: 'fa fa-align-justify' }]
                    },
                    { name: t('js.reports.style_prop.decoration', 'Decorazione'), property: 'text-decoration', type: 'radio', defaults: 'none',
                      list: [{ value: 'none', name: t('js.reports.decoration_opt.none', 'No'), className: 'fa fa-times' }, { value: 'underline', name: t('js.reports.decoration_opt.underline', 'U'), className: 'fa fa-underline' }, { value: 'line-through', name: t('js.reports.decoration_opt.strike', 'S'), className: 'fa fa-strikethrough' }]
                    },
                    { name: t('js.reports.style_prop.transform', 'Trasformazione'), property: 'text-transform', type: 'select', defaults: 'none',
                      list: [{ value: 'none', name: t('js.reports.transform_opt.none', 'Nessuna') }, { value: 'uppercase', name: t('js.reports.transform_opt.uppercase', 'MAIUSCOLO') }, { value: 'lowercase', name: t('js.reports.transform_opt.lowercase', 'minuscolo') }, { value: 'capitalize', name: t('js.reports.transform_opt.capitalize', 'Capitalizzato') }]
                    },
                ],
            },
            {
                name: t('js.reports.style_sector.background', 'Sfondo'), open: false,
                buildProps: ['background-color', 'background-image', 'background-repeat', 'background-position', 'background-size'],
                properties: [
                    { name: t('js.reports.style_prop.bg_color', 'Colore Sfondo'), property: 'background-color', type: 'color' },
                    { name: t('js.reports.style_prop.bg_size', 'Dimensione BG'), property: 'background-size', type: 'select', list: [{ value: 'auto' }, { value: 'cover' }, { value: 'contain' }, { value: '100% auto' }] },
                ],
            },
            {
                name: t('js.reports.style_sector.borders', 'Bordi'), open: false,
                buildProps: ['border', 'border-width', 'border-style', 'border-color', 'border-radius', 'border-top-left-radius', 'border-top-right-radius', 'border-bottom-left-radius', 'border-bottom-right-radius'],
                properties: [
                    { name: t('js.reports.style_prop.border_style', 'Stile Bordo'), property: 'border-style', type: 'select', defaults: 'none', list: [{ value: 'none' }, { value: 'solid' }, { value: 'dashed' }, { value: 'dotted' }, { value: 'double' }] },
                    { name: t('js.reports.style_prop.border_color', 'Colore Bordo'), property: 'border-color', type: 'color' },
                ],
            },
            {
                name: t('js.reports.style_sector.effects', 'Effetti'), open: false,
                buildProps: ['opacity', 'box-shadow', 'text-shadow', 'transform'],
                properties: [{
                    name: t('js.reports.style_prop.box_shadow', 'Ombra Box'), property: 'box-shadow', type: 'stack',
                    properties: [
                        { name: t('js.reports.style_prop.shadow_x', 'X'), property: 'box-shadow-h', type: 'integer', units: ['px'], defaults: '0' },
                        { name: t('js.reports.style_prop.shadow_y', 'Y'), property: 'box-shadow-v', type: 'integer', units: ['px'], defaults: '0' },
                        { name: t('js.reports.style_prop.blur', 'Blur'), property: 'box-shadow-blur', type: 'integer', units: ['px'], defaults: '4' },
                        { name: t('js.reports.style_prop.spread', 'Spread'), property: 'box-shadow-spread', type: 'integer', units: ['px'], defaults: '0' },
                        { name: t('js.reports.style_prop.color', 'Colore'), property: 'box-shadow-color', type: 'color', defaults: 'rgba(0,0,0,0.15)' },
                        { name: t('js.reports.style_prop.type', 'Tipo'), property: 'box-shadow-type', type: 'select', list: [{ value: '', name: t('js.reports.style_prop.outer', 'Esterna') }, { value: 'inset', name: t('js.reports.style_prop.inner', 'Interna') }] },
                    ],
                }],
            },
            {
                name: t('js.reports.style_sector.extra', 'Extra'), open: false,
                buildProps: ['cursor', 'list-style-type'],
                properties: [
                    { name: t('js.reports.style_prop.cursor', 'Cursore'), property: 'cursor', type: 'select', defaults: 'auto', list: [{ value: 'auto' }, { value: 'pointer' }, { value: 'default' }, { value: 'text' }, { value: 'move' }, { value: 'not-allowed' }] },
                ],
            },
        ];
    }

    /* Blocks */
    function registerAllBlocks(editor) {
        var bm = editor.BlockManager;

        /* -- Base -- */
        bm.add('rp-text', { label: blockLabel('fa-paragraph', t('js.reports.block.text', 'Testo')), category: t('js.reports.category.base', 'Base'), content: { type: 'text', content: '<p>Scrivi qui il tuo testo...</p>' }, activate: true });
        bm.add('rp-heading', { label: blockLabel('fa-heading', t('js.reports.block.heading1', 'Titolo H1')), category: t('js.reports.category.base', 'Base'), content: '<h1>Titolo Report</h1>' });
        bm.add('rp-heading2', { label: blockLabel('fa-heading', t('js.reports.block.heading2', 'Titolo H2')), category: t('js.reports.category.base', 'Base'), content: '<h2>Sezione</h2>' });
        bm.add('rp-heading3', { label: blockLabel('fa-heading', t('js.reports.block.heading3', 'Titolo H3')), category: t('js.reports.category.base', 'Base'), content: '<h3>Sottosezione</h3>' });
        bm.add('rp-link', { label: blockLabel('fa-link', t('js.reports.block.link', 'Link')), category: t('js.reports.category.base', 'Base'), content: { type: 'link', content: 'Clicca qui', style: { color: '#0d6efd' } } });
        bm.add('rp-image', { label: blockLabel('fa-image', t('js.reports.block.image', 'Immagine')), category: t('js.reports.category.base', 'Base'), content: { type: 'image' }, activate: true });
        bm.add('rp-list-ul', { label: blockLabel('fa-list-ul', t('js.reports.block.list_ul', 'Elenco puntato')), category: t('js.reports.category.base', 'Base'), content: '<ul><li>Elemento uno</li><li>Elemento due</li><li>Elemento tre</li></ul>' });
        bm.add('rp-list-ol', { label: blockLabel('fa-list-ol', t('js.reports.block.list_ol', 'Elenco numerato')), category: t('js.reports.category.base', 'Base'), content: '<ol><li>Primo</li><li>Secondo</li><li>Terzo</li></ol>' });
        bm.add('rp-divider', { label: blockLabel('fa-grip-lines', t('js.reports.block.divider', 'Separatore')), category: t('js.reports.category.base', 'Base'), content: '<hr style="border:0;border-top:2px solid #d0d7de;margin:20px 0;" />' });
        bm.add('rp-spacer', { label: blockLabel('fa-arrows-up-down', t('js.reports.block.spacer', 'Spazio')), category: t('js.reports.category.base', 'Base'), content: '<div style="height:32px;"></div>' });
        bm.add('rp-quote', { label: blockLabel('fa-quote-left', t('js.reports.block.quote', 'Citazione')), category: t('js.reports.category.base', 'Base'), content: '<blockquote style="border-left:4px solid #6c757d;padding:12px 20px;margin:16px 0;color:#495057;font-style:italic;">Testo della citazione</blockquote>' });
        bm.add('rp-code', { label: blockLabel('fa-code', t('js.reports.block.code', 'Codice')), category: t('js.reports.category.base', 'Base'), content: '<pre style="background:#f8f9fa;border:1px solid #dee2e6;border-radius:6px;padding:16px;font-family:monospace;font-size:13px;overflow-x:auto;"><code>// codice qui</code></pre>' });

        /* -- Layout -- */
        bm.add('rp-section', { label: blockLabel('fa-square', t('js.reports.block.section', 'Sezione')), category: t('js.reports.category.layout', 'Layout'), content: '<section style="padding:24px;min-height:80px;"></section>' });
        bm.add('rp-container', { label: blockLabel('fa-box', t('js.reports.block.container', 'Contenitore')), category: t('js.reports.category.layout', 'Layout'), content: '<div style="max-width:960px;margin:0 auto;padding:20px;"></div>' });
        bm.add('rp-two-columns', { label: blockLabel('fa-columns', t('js.reports.block.two_columns', '2 Colonne')), category: t('js.reports.category.layout', 'Layout'),
            content: '<div style="display:flex;gap:20px;"><div style="flex:1;min-height:80px;padding:16px;background:#f8f9fa;border-radius:8px;">Colonna A</div><div style="flex:1;min-height:80px;padding:16px;background:#f8f9fa;border-radius:8px;">Colonna B</div></div>' });
        bm.add('rp-three-columns', { label: blockLabel('fa-table-columns', t('js.reports.block.three_columns', '3 Colonne')), category: t('js.reports.category.layout', 'Layout'),
            content: '<div style="display:flex;gap:20px;"><div style="flex:1;min-height:80px;padding:16px;background:#f8f9fa;border-radius:8px;">A</div><div style="flex:1;min-height:80px;padding:16px;background:#f8f9fa;border-radius:8px;">B</div><div style="flex:1;min-height:80px;padding:16px;background:#f8f9fa;border-radius:8px;">C</div></div>' });
        bm.add('rp-sidebar-layout', { label: blockLabel('fa-table-cells-large', t('js.reports.block.sidebar_layout', 'Sidebar + Contenuto')), category: t('js.reports.category.layout', 'Layout'),
            content: '<div style="display:flex;gap:20px;"><div style="flex:0 0 260px;padding:16px;background:#f1f3f5;border-radius:8px;min-height:200px;">Sidebar</div><div style="flex:1;padding:16px;min-height:200px;">Contenuto principale</div></div>' });
        bm.add('rp-grid-2x2', { label: blockLabel('fa-border-all', t('js.reports.block.grid_2x2', 'Griglia 2x2')), category: t('js.reports.category.layout', 'Layout'),
            content: '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;"><div style="padding:16px;background:#f8f9fa;border-radius:8px;min-height:100px;">Cella 1</div><div style="padding:16px;background:#f8f9fa;border-radius:8px;min-height:100px;">Cella 2</div><div style="padding:16px;background:#f8f9fa;border-radius:8px;min-height:100px;">Cella 3</div><div style="padding:16px;background:#f8f9fa;border-radius:8px;min-height:100px;">Cella 4</div></div>' });
        bm.add('rp-hero', { label: blockLabel('fa-panorama', t('js.reports.block.hero', 'Hero Banner')), category: t('js.reports.category.layout', 'Layout'),
            content: '<section style="padding:48px 32px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border-radius:12px;text-align:center;color:#fff;"><h1 style="margin:0 0 12px;color:#fff;font-size:2em;">Titolo Report</h1><p style="margin:0;font-size:1.1em;opacity:0.9;">Sottotitolo o descrizione del report</p></section>' });
        bm.add('rp-card', { label: blockLabel('fa-id-card', t('js.reports.block.card', 'Card')), category: t('js.reports.category.layout', 'Layout'),
            content: '<div style="border:1px solid #dee2e6;border-radius:12px;overflow:hidden;background:#fff;"><div style="padding:16px 20px;border-bottom:1px solid #dee2e6;font-weight:600;">Titolo Card</div><div style="padding:20px;">Contenuto della card.</div></div>' });

        /* -- Dati -- */
        bm.add('rp-table', { label: blockLabel('fa-table', t('js.reports.block.table_simple', 'Tabella Semplice')), category: t('js.reports.category.data', 'Dati'),
            content: '<table style="width:100%;border-collapse:collapse;font-size:14px;"><thead><tr><th style="border:1px solid #dee2e6;padding:10px 12px;background:#f8f9fa;text-align:left;font-weight:600;">Intestazione</th><th style="border:1px solid #dee2e6;padding:10px 12px;background:#f8f9fa;text-align:left;font-weight:600;">Valore</th><th style="border:1px solid #dee2e6;padding:10px 12px;background:#f8f9fa;text-align:left;font-weight:600;">Note</th></tr></thead><tbody><tr><td style="border:1px solid #dee2e6;padding:10px 12px;">{{ campo_1 }}</td><td style="border:1px solid #dee2e6;padding:10px 12px;">{{ campo_2 }}</td><td style="border:1px solid #dee2e6;padding:10px 12px;">{{ campo_3 }}</td></tr></tbody></table>' });
        bm.add('rp-table-striped', { label: blockLabel('fa-table-list', t('js.reports.block.table_striped', 'Tabella Zebrata')), category: t('js.reports.category.data', 'Dati'),
            content: '<table style="width:100%;border-collapse:collapse;font-size:14px;"><thead><tr><th style="padding:10px 12px;background:#343a40;color:#fff;text-align:left;">Colonna A</th><th style="padding:10px 12px;background:#343a40;color:#fff;text-align:left;">Colonna B</th><th style="padding:10px 12px;background:#343a40;color:#fff;text-align:left;">Colonna C</th></tr></thead><tbody><tr style="background:#fff;"><td style="padding:10px 12px;border-bottom:1px solid #eee;">Riga 1</td><td style="padding:10px 12px;border-bottom:1px solid #eee;">Val</td><td style="padding:10px 12px;border-bottom:1px solid #eee;">Dato</td></tr><tr style="background:#f8f9fa;"><td style="padding:10px 12px;border-bottom:1px solid #eee;">Riga 2</td><td style="padding:10px 12px;border-bottom:1px solid #eee;">Val</td><td style="padding:10px 12px;border-bottom:1px solid #eee;">Dato</td></tr></tbody></table>' });
        bm.add('rp-data-list', { label: blockLabel('fa-rectangle-list', t('js.reports.block.data_list', 'Lista Dati')), category: t('js.reports.category.data', 'Dati'),
            content: '<div style="font-size:14px;">{{ #items }}<div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #eee;"><span style="font-weight:500;">{{ etichetta }}</span><span style="color:#6c757d;">{{ valore }}</span></div>{{ /items }}</div>' });
        bm.add('rp-metric', { label: blockLabel('fa-chart-line', t('js.reports.block.metric', 'Metrica KPI')), category: t('js.reports.category.data', 'Dati'),
            content: '<div style="padding:20px;border-radius:12px;background:#f0f4ff;border:1px solid #d4ddff;text-align:center;"><div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#667085;margin-bottom:8px;">Indicatore</div><div style="font-size:36px;font-weight:700;color:#1d2939;">{{ valore }}</div><div style="font-size:13px;color:#667085;margin-top:4px;">{{ descrizione }}</div></div>' });
        bm.add('rp-metric-row', { label: blockLabel('fa-chart-bar', t('js.reports.block.metric_row', 'Metriche in riga')), category: t('js.reports.category.data', 'Dati'),
            content: '<div style="display:flex;gap:16px;"><div style="flex:1;padding:20px;border-radius:10px;background:#e8f5e9;text-align:center;"><div style="font-size:11px;text-transform:uppercase;color:#2e7d32;">Totale</div><div style="font-size:28px;font-weight:700;color:#1b5e20;">{{ totale }}</div></div><div style="flex:1;padding:20px;border-radius:10px;background:#fff3e0;text-align:center;"><div style="font-size:11px;text-transform:uppercase;color:#e65100;">In Corso</div><div style="font-size:28px;font-weight:700;color:#bf360c;">{{ in_corso }}</div></div><div style="flex:1;padding:20px;border-radius:10px;background:#e3f2fd;text-align:center;"><div style="font-size:11px;text-transform:uppercase;color:#1565c0;">Completati</div><div style="font-size:28px;font-weight:700;color:#0d47a1;">{{ completati }}</div></div></div>' });
        bm.add('rp-badge', { label: blockLabel('fa-certificate', t('js.reports.block.badge', 'Badge Stato')), category: t('js.reports.category.data', 'Dati'),
            content: '<span style="display:inline-block;padding:5px 14px;border-radius:999px;background:#e0e7ff;color:#3730a3;font-size:13px;font-weight:600;">{{ stato }}</span>' });
        bm.add('rp-progress', { label: blockLabel('fa-battery-half', t('js.reports.block.progress', 'Barra Progresso')), category: t('js.reports.category.data', 'Dati'),
            content: '<div style="margin:12px 0;"><div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:13px;"><span>Completamento</span><span>{{ percentuale }}%</span></div><div style="height:10px;background:#e9ecef;border-radius:99px;overflow:hidden;"><div style="width:65%;height:100%;background:linear-gradient(90deg,#0d6efd,#6610f2);border-radius:99px;"></div></div></div>' });

        /* -- Contenuto -- */
        bm.add('rp-note', { label: blockLabel('fa-note-sticky', t('js.reports.block.note', 'Nota / Callout')), category: t('js.reports.category.content', 'Contenuto'),
            content: '<div style="padding:16px 20px;border-left:4px solid #0d6efd;background:#eff6ff;border-radius:0 8px 8px 0;margin:12px 0;"><strong style="display:block;margin-bottom:4px;">Nota</strong>Testo della nota o informazione importante.</div>' });
        bm.add('rp-warning', { label: blockLabel('fa-triangle-exclamation', t('js.reports.block.warning', 'Avviso')), category: t('js.reports.category.content', 'Contenuto'),
            content: '<div style="padding:16px 20px;border-left:4px solid #ffc107;background:#fff8e1;border-radius:0 8px 8px 0;margin:12px 0;"><strong style="display:block;margin-bottom:4px;color:#856404;">Attenzione</strong>Messaggio di avviso.</div>' });
        bm.add('rp-success', { label: blockLabel('fa-circle-check', t('js.reports.block.success', 'Successo')), category: t('js.reports.category.content', 'Contenuto'),
            content: '<div style="padding:16px 20px;border-left:4px solid #198754;background:#d1e7dd;border-radius:0 8px 8px 0;margin:12px 0;"><strong style="display:block;margin-bottom:4px;color:#0f5132;">Completato</strong>Operazione confermata.</div>' });
        bm.add('rp-signature', { label: blockLabel('fa-signature', t('js.reports.block.signature', 'Firma')), category: t('js.reports.category.content', 'Contenuto'),
            content: '<div style="margin-top:40px;display:inline-block;"><div style="width:280px;border-top:1px solid #98a2b3;padding-top:10px;font-size:13px;color:#667085;">Firma / Nome e Cognome<br><span style="font-size:11px;">Data: {{ data }}</span></div></div>' });
        bm.add('rp-page-break', { label: blockLabel('fa-scissors', t('js.reports.block.page_break', 'Interruzione Pagina')), category: t('js.reports.category.content', 'Contenuto'),
            content: '<div style="page-break-after:always;border-top:2px dashed #adb5bd;margin:24px 0;padding-top:8px;text-align:center;font-size:11px;color:#adb5bd;">\u2E3B Interruzione di pagina \u2E3B</div>' });
        bm.add('rp-header-block', { label: blockLabel('fa-window-maximize', t('js.reports.block.header_block', 'Intestazione Report')), category: t('js.reports.category.content', 'Contenuto'),
            content: '<div style="display:flex;justify-content:space-between;align-items:center;padding:16px 24px;border-bottom:2px solid #343a40;margin-bottom:24px;"><div><strong style="font-size:18px;">{{ nome_azienda }}</strong><br><span style="font-size:12px;color:#6c757d;">{{ indirizzo }}</span></div><div style="text-align:right;"><span style="font-size:13px;color:#6c757d;">Report generato il</span><br><strong>{{ data_report }}</strong></div></div>' });
        bm.add('rp-footer', { label: blockLabel('fa-window-minimize', t('js.reports.block.footer', 'Pie di Pagina')), category: t('js.reports.category.content', 'Contenuto'),
            content: '<div style="border-top:1px solid #dee2e6;padding:16px 24px;margin-top:40px;display:flex;justify-content:space-between;font-size:12px;color:#6c757d;"><span>{{ nome_azienda }} - Documento riservato</span><span>Pagina {{ pagina }} di {{ totale_pagine }}</span></div>' });
        bm.add('rp-toc', { label: blockLabel('fa-list-check', t('js.reports.block.toc', 'Indice')), category: t('js.reports.category.content', 'Contenuto'),
            content: '<div style="padding:20px;border:1px solid #dee2e6;border-radius:8px;background:#fafbfc;"><h3 style="margin:0 0 16px;font-size:16px;">Indice</h3><div style="padding:6px 0;border-bottom:1px dotted #dee2e6;"><a href="#" style="color:#0d6efd;text-decoration:none;">1. Introduzione</a></div><div style="padding:6px 0;border-bottom:1px dotted #dee2e6;"><a href="#" style="color:#0d6efd;text-decoration:none;">2. Riepilogo dati</a></div><div style="padding:6px 0;border-bottom:1px dotted #dee2e6;"><a href="#" style="color:#0d6efd;text-decoration:none;">3. Dettaglio</a></div><div style="padding:6px 0;"><a href="#" style="color:#0d6efd;text-decoration:none;">4. Conclusioni</a></div></div>' });

        /* -- Template Completi -- */
        bm.add('rp-template-invoice', { label: blockLabel('fa-file-invoice', t('js.reports.block.template_invoice', 'Template Fattura')), category: t('js.reports.category.full_templates', 'Template Completi'),
            content: '<div style="font-family:Arial,sans-serif;max-width:800px;margin:0 auto;padding:30px;"><div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:30px;"><div><h1 style="margin:0;font-size:24px;">FATTURA</h1><p style="margin:4px 0 0;color:#6c757d;">N. {{ numero_fattura }}</p></div><div style="text-align:right;"><strong>{{ nome_azienda }}</strong><br><span style="font-size:13px;color:#6c757d;">{{ indirizzo_azienda }}<br>P.IVA: {{ partita_iva }}</span></div></div><div style="display:flex;gap:40px;margin-bottom:30px;"><div><strong style="font-size:12px;text-transform:uppercase;color:#6c757d;">Destinatario</strong><br>{{ nome_cliente }}<br><span style="color:#6c757d;font-size:13px;">{{ indirizzo_cliente }}</span></div><div><strong style="font-size:12px;text-transform:uppercase;color:#6c757d;">Data</strong><br>{{ data_fattura }}</div></div><table style="width:100%;border-collapse:collapse;margin-bottom:30px;"><thead><tr><th style="padding:10px;background:#f8f9fa;border-bottom:2px solid #dee2e6;text-align:left;">Descrizione</th><th style="padding:10px;background:#f8f9fa;border-bottom:2px solid #dee2e6;text-align:right;">Qt\u00e0</th><th style="padding:10px;background:#f8f9fa;border-bottom:2px solid #dee2e6;text-align:right;">Prezzo</th><th style="padding:10px;background:#f8f9fa;border-bottom:2px solid #dee2e6;text-align:right;">Totale</th></tr></thead><tbody><tr><td style="padding:10px;border-bottom:1px solid #eee;">{{ descrizione }}</td><td style="padding:10px;border-bottom:1px solid #eee;text-align:right;">{{ quantita }}</td><td style="padding:10px;border-bottom:1px solid #eee;text-align:right;">{{ prezzo }}</td><td style="padding:10px;border-bottom:1px solid #eee;text-align:right;">{{ riga_totale }}</td></tr></tbody><tfoot><tr><td colspan="3" style="padding:10px;text-align:right;font-weight:700;">TOTALE</td><td style="padding:10px;text-align:right;font-weight:700;font-size:18px;">\u20AC {{ totale }}</td></tr></tfoot></table><div style="font-size:13px;color:#6c757d;border-top:1px solid #eee;padding-top:16px;">{{ note_fattura }}</div></div>' });
        bm.add('rp-template-report', { label: blockLabel('fa-file-lines', t('js.reports.block.template_report', 'Template Report')), category: t('js.reports.category.full_templates', 'Template Completi'),
            content: '<div style="font-family:Arial,sans-serif;max-width:800px;margin:0 auto;padding:30px;"><header style="border-bottom:3px solid #1d2939;padding-bottom:20px;margin-bottom:30px;"><h1 style="margin:0;">{{ titolo_report }}</h1><p style="margin:6px 0 0;color:#667085;">Preparato da {{ autore }} \u2014 {{ data_report }}</p></header><section style="margin-bottom:30px;"><h2 style="font-size:18px;border-bottom:1px solid #eee;padding-bottom:8px;">1. Riepilogo</h2><p>{{ riepilogo }}</p></section><section style="margin-bottom:30px;"><h2 style="font-size:18px;border-bottom:1px solid #eee;padding-bottom:8px;">2. Dettagli</h2><p>{{ dettagli }}</p></section><section style="margin-bottom:30px;"><h2 style="font-size:18px;border-bottom:1px solid #eee;padding-bottom:8px;">3. Conclusioni</h2><p>{{ conclusioni }}</p></section><footer style="border-top:1px solid #dee2e6;padding-top:16px;margin-top:40px;display:flex;justify-content:space-between;font-size:12px;color:#6c757d;"><span>{{ nome_azienda }}</span><span>Pag. {{ pagina }}</span></footer></div>' });
    }

    /* Data Persistence */
    function setupDataPersistence(editor) {
        var htmlInput = document.getElementById('template-html-input');
        if (!htmlInput) return;

        /* Load template content after editor is fully ready */
        function loadContent() {
            var existing = htmlInput.value;
            if (existing && existing.trim()) {
                /* Separate <style> blocks from HTML body */
                var cssContent = '';
                var htmlContent = existing.replace(/<style[^>]*>([\s\S]*?)<\/style>/gi, function(match, css) {
                    cssContent += css + '\n';
                    return '';
                });
                if (htmlContent.trim()) {
                    editor.setComponents(htmlContent.trim());
                }
                if (cssContent.trim()) {
                    editor.setStyle(cssContent.trim());
                }
            } else {
                /* No existing content: use starter layout based on source_type */
                var presetEl = document.getElementById('template-starter-preset');
                var preset = presetEl ? presetEl.value : 'list';
                var starter = getStarterLayout(preset);
                if (starter) editor.setComponents(starter);
            }
        }

        /* editor 'load' fires once the canvas iframe is ready */
        if (editor.getContainer()) {
            editor.on('load', loadContent);
        } else {
            loadContent();
        }

        var debouncedSave = debounce(function () {
            var html = editor.getHtml();
            var css = editor.getCss();
            htmlInput.value = (css ? '<style>' + css + '</style>' : '') + html;
            showSaveIndicator();
        }, 600);

        editor.on('component:update', debouncedSave);
        editor.on('component:add', debouncedSave);
        editor.on('component:remove', debouncedSave);
        editor.on('style:change', debouncedSave);
        editor.on('component:styleUpdate', debouncedSave);

        var form = document.querySelector('form[data-template-form]');
        if (form) {
            form.addEventListener('submit', function () {
                var html = editor.getHtml();
                var css = editor.getCss();
                htmlInput.value = (css ? '<style>' + css + '</style>' : '') + html;
            });
        }
    }

    /* Component Toolbar (floating actions on selection) */
    function setupComponentToolbar(editor) {
        editor.on('component:selected', function (component) {
            var tb = component.get('toolbar');
            if (!tb || !tb.length) {
                component.set('toolbar', [
                    { attributes: { class: 'fa fa-arrow-up', title: t('js.reports.toolbar.select_parent', 'Seleziona Genitore') }, command: 'select-parent' },
                    { attributes: { class: 'fa fa-arrows-alt', title: t('js.reports.toolbar.move', 'Sposta') }, command: 'tlb-move' },
                    { attributes: { class: 'fa fa-clone', title: t('js.reports.toolbar.duplicate', 'Duplica') }, command: 'tlb-clone' },
                    { attributes: { class: 'fa fa-trash', title: t('js.common.delete', 'Elimina') }, command: 'tlb-delete' },
                ]);
            }
        });
        editor.Commands.add('select-parent', {
            run: function (ed) {
                var sel = ed.getSelected();
                if (sel && sel.parent()) ed.select(sel.parent());
            }
        });
    }

    /* Canvas Keyboard Shortcuts */
    function setupCanvasKeyboard(editor) {
        editor.on('canvas:frame:load', function (frame) {
            var frameDoc = frame.view && frame.view.getDoc();
            if (!frameDoc) return;
            frameDoc.addEventListener('keydown', function (e) {
                if (e.ctrlKey && e.key === 'z') { e.preventDefault(); editor.UndoManager.undo(); }
                if (e.ctrlKey && e.key === 'y') { e.preventDefault(); editor.UndoManager.redo(); }
                if ((e.key === 'Delete' || e.key === 'Backspace') && ['INPUT','TEXTAREA'].indexOf(e.target.tagName) === -1) {
                    var sel = editor.getSelected();
                    if (sel) { e.preventDefault(); sel.remove(); }
                }
                if (e.ctrlKey && e.key === 'd') {
                    e.preventDefault();
                    var sel2 = editor.getSelected();
                    if (sel2) { var cl = sel2.clone(); sel2.parent().append(cl, { at: sel2.index() + 1 }); editor.select(cl); }
                }
            });
        });
    }

    /* Rich Text Editor */
    function setupRichTextEditor(editor) {
        editor.setCustomRte({
            enable: function (el, rte) {
                el.contentEditable = 'true';
                var range = document.createRange();
                range.selectNodeContents(el);
                range.collapse(false);
                var sel = window.getSelection();
                if (sel) { sel.removeAllRanges(); sel.addRange(range); }

                var existing = document.getElementById('rp-rte-toolbar');
                if (existing) { existing.style.display = 'flex'; positionRteToolbar(existing, el); existing._targetEl = el; return rte; }

                var toolbar = buildRteToolbar(el);
                toolbar._targetEl = el;
                document.body.appendChild(toolbar);
                positionRteToolbar(toolbar, el);
                return rte;
            },
            disable: function (el, rte) {
                el.contentEditable = 'false';
                var tb = document.getElementById('rp-rte-toolbar');
                if (tb) tb.style.display = 'none';
            },
        });
    }

    function positionRteToolbar(toolbar, el) {
        /* Position above the canvas iframe element */
        var canvas = document.querySelector('.gjs-cv-canvas');
        if (!canvas) return;
        var canvasRect = canvas.getBoundingClientRect();
        var frame = canvas.querySelector('iframe');
        if (!frame) return;
        var frameRect = frame.getBoundingClientRect();
        var elRect = el.getBoundingClientRect();
        /* elRect is relative to iframe viewport; offset by iframe position */
        var absTop = frameRect.top + elRect.top;
        var absLeft = frameRect.left + elRect.left;
        var tbHeight = toolbar.offsetHeight || 36;
        var topPos = absTop - tbHeight - 6;
        if (topPos < canvasRect.top) topPos = absTop + elRect.height + 6;
        var leftPos = absLeft;
        /* Clamp to viewport */
        var tbWidth = toolbar.offsetWidth || 300;
        if (leftPos + tbWidth > window.innerWidth - 10) leftPos = window.innerWidth - tbWidth - 10;
        if (leftPos < 10) leftPos = 10;
        toolbar.style.top = Math.max(0, topPos) + 'px';
        toolbar.style.left = leftPos + 'px';
    }

    function buildRteToolbar(el) {
        var toolbar = document.createElement('div');
        toolbar.id = 'rp-rte-toolbar';
        toolbar.className = 'rp-rte-toolbar';

        var buttons = [
            { cmd: 'bold', icon: 'fa-bold', title: t('js.reports.rte.bold', 'Grassetto (Ctrl+B)') },
            { cmd: 'italic', icon: 'fa-italic', title: t('js.reports.rte.italic', 'Corsivo (Ctrl+I)') },
            { cmd: 'underline', icon: 'fa-underline', title: t('js.reports.rte.underline', 'Sottolineato (Ctrl+U)') },
            { cmd: 'strikeThrough', icon: 'fa-strikethrough', title: t('js.reports.rte.strikethrough', 'Barrato') },
            { type: 'sep' },
            { cmd: 'justifyLeft', icon: 'fa-align-left', title: t('js.reports.rte.align_left', 'Allinea a sinistra') },
            { cmd: 'justifyCenter', icon: 'fa-align-center', title: t('js.reports.rte.align_center', 'Centra') },
            { cmd: 'justifyRight', icon: 'fa-align-right', title: t('js.reports.rte.align_right', 'Allinea a destra') },
            { type: 'sep' },
            { cmd: 'insertUnorderedList', icon: 'fa-list-ul', title: t('js.reports.block.list_ul', 'Elenco puntato') },
            { cmd: 'insertOrderedList', icon: 'fa-list-ol', title: t('js.reports.block.list_ol', 'Elenco numerato') },
            { type: 'sep' },
            { cmd: 'createLink', icon: 'fa-link', title: t('js.reports.rte.insert_link', 'Inserisci link'), prompt: true },
            { cmd: 'removeFormat', icon: 'fa-eraser', title: t('js.reports.rte.remove_format', 'Rimuovi formattazione') },
        ];

        buttons.forEach(function (b) {
            if (b.type === 'sep') {
                var sep = document.createElement('div');
                sep.className = 'rp-rte-sep';
                toolbar.appendChild(sep);
                return;
            }
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'rp-rte-btn';
            btn.title = b.title;
            btn.innerHTML = '<i class="fa-solid ' + b.icon + '"></i>';
            btn.addEventListener('mousedown', function (e) {
                e.preventDefault();
                if (b.prompt) {
                    var url = window.prompt(t('js.reports.rte.link_prompt', 'URL del link:'));
                    if (url) document.execCommand(b.cmd, false, url);
                } else {
                    document.execCommand(b.cmd, false, null);
                }
            });
            toolbar.appendChild(btn);
        });
        return toolbar;
    }

    /* Toolbar */
    function setupToolbar(editor) {
        var undoBtn = document.getElementById('undo-btn');
        var redoBtn = document.getElementById('redo-btn');
        var clearBtn = document.getElementById('clear-btn');
        var importBtn = document.getElementById('import-btn');
        var exportBtn = document.getElementById('export-btn');
        var previewBtn = document.getElementById('preview-btn');
        var codeBtn = document.getElementById('code-edit-btn');
        var deviceDesktop = document.getElementById('device-desktop-btn');
        var deviceTablet = document.getElementById('device-tablet-btn');
        var deviceMobile = document.getElementById('device-mobile-btn');

        function setActiveDevice(name) {
            [deviceDesktop, deviceTablet, deviceMobile].forEach(function (btn) { if (btn) btn.classList.remove('is-active'); });
            var map = { Desktop: deviceDesktop, Tablet: deviceTablet, Mobile: deviceMobile };
            if (map[name]) map[name].classList.add('is-active');
        }

        if (undoBtn) undoBtn.addEventListener('click', function () { editor.UndoManager.undo(); });
        if (redoBtn) redoBtn.addEventListener('click', function () { editor.UndoManager.redo(); });
        if (clearBtn) clearBtn.addEventListener('click', function () {
            var clearBody = t('js.reports.clear_designer.body', 'Vuoi svuotare tutto il contenuto del designer?');
            var confirmClearPromise = typeof window.appConfirm === 'function'
                ? window.appConfirm({
                    title: t('js.reports.clear_designer.title', 'Svuota designer'),
                    body: clearBody,
                    confirmLabel: t('js.reports.clear_designer.confirm_label', 'Svuota'),
                    confirmClass: 'btn-danger'
                })
                : Promise.resolve(window.confirm(clearBody));

            confirmClearPromise.then(function (ok) {
                if (!ok) return;
                editor.DomComponents.clear();
                showSaveIndicator(t('js.reports.save.cleared', 'Svuotato'));
            });
        });
        if (deviceDesktop) deviceDesktop.addEventListener('click', function () { editor.setDevice('Desktop'); setActiveDevice('Desktop'); });
        if (deviceTablet) deviceTablet.addEventListener('click', function () { editor.setDevice('Tablet'); setActiveDevice('Tablet'); });
        if (deviceMobile) deviceMobile.addEventListener('click', function () { editor.setDevice('Mobile'); setActiveDevice('Mobile'); });
        setActiveDevice('Desktop');

        if (importBtn) importBtn.addEventListener('click', function () { showCodeModal(editor, 'import'); });
        if (exportBtn) exportBtn.addEventListener('click', function () { showCodeModal(editor, 'export'); });
        if (codeBtn) codeBtn.addEventListener('click', function () { showCodeModal(editor, 'edit'); });

        if (previewBtn) previewBtn.addEventListener('click', function () {
            var html = editor.getHtml();
            var css = editor.getCss();
            var bsCss = (document.getElementById('grapesjs-editor') || {}).getAttribute('data-bootstrap-css') || '/assets/css/bootstrap.min.css';
            var full = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + t('js.reports.preview_title', 'Anteprima Report') + '</title><link rel="stylesheet" href="' + bsCss + '"><style>body{padding:40px;font-family:Arial,sans-serif;background:#fff;color:#212529;}@media print{body{padding:0;}}</style>' + (css ? '<style>' + css + '</style>' : '') + '</head><body>' + html + '</body></html>';
            var win = window.open('', 'report-preview', 'width=1200,height=900');
            if (win) { win.document.write(full); win.document.close(); }
        });
    }

    /* Code Modal (import / export / edit) */
    function showCodeModal(editor, mode) {
        var overlay = document.createElement('div');
        overlay.className = 'rp-code-modal-overlay';
        var modal = document.createElement('div');
        modal.className = 'rp-code-modal';

        var title = mode === 'import' ? t('js.reports.code_modal.title_import', 'Importa HTML') : mode === 'export' ? t('js.reports.code_modal.title_export', 'Esporta HTML') : t('js.reports.code_modal.title_edit', 'Modifica Codice HTML');
        var readOnly = mode === 'export';
        var currentHtml = editor.getHtml();
        var currentCss = editor.getCss();
        var codeContent = '';
        if (mode === 'export' || mode === 'edit') {
            codeContent = (currentCss ? '<style>\n' + currentCss + '\n</style>\n\n' : '') + formatHtml(currentHtml);
        }

        modal.innerHTML = '<div class="rp-code-modal-header"><h5 class="mb-0">' + title + '</h5><button type="button" class="btn-close" aria-label="' + t('js.common.close', 'Chiudi') + '"></button></div><div class="rp-code-modal-body"><textarea class="rp-code-textarea" ' + (readOnly ? 'readonly' : '') + ' spellcheck="false">' + escapeHtml(codeContent) + '</textarea></div><div class="rp-code-modal-footer">' + (readOnly ? '<button type="button" class="btn btn-primary btn-sm rp-code-copy"><i class="fa-solid fa-copy"></i> ' + t('js.reports.code_modal.copy', 'Copia') + '</button>' : '<button type="button" class="btn btn-primary btn-sm rp-code-apply"><i class="fa-solid fa-check"></i> ' + t('js.reports.code_modal.apply', 'Applica') + '</button>') + '<button type="button" class="btn btn-secondary btn-sm rp-code-close">' + t('js.common.close', 'Chiudi') + '</button></div>';
        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        var textarea = modal.querySelector('.rp-code-textarea');
        textarea.focus();
        if (mode === 'export') textarea.select();

        function closeModal() { overlay.remove(); }
        modal.querySelector('.btn-close').addEventListener('click', closeModal);
        modal.querySelector('.rp-code-close').addEventListener('click', closeModal);
        overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });

        var copyBtn = modal.querySelector('.rp-code-copy');
        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                textarea.select();
                navigator.clipboard.writeText(textarea.value).then(function () {
                    copyBtn.innerHTML = '<i class="fa-solid fa-check"></i> ' + t('js.reports.code_modal.copied', 'Copiato!');
                    setTimeout(function () { copyBtn.innerHTML = '<i class="fa-solid fa-copy"></i> ' + t('js.reports.code_modal.copy', 'Copia'); }, 1500);
                });
            });
        }
        var applyBtn = modal.querySelector('.rp-code-apply');
        if (applyBtn) {
            applyBtn.addEventListener('click', function () {
                var val = textarea.value.trim();
                if (val) {
                    /* Parse <style> blocks out of the HTML */
                    var cssContent = '';
                    var htmlContent = val.replace(/<style[^>]*>([\s\S]*?)<\/style>/gi, function(match, css) {
                        cssContent += css + '\n';
                        return '';
                    });
                    if (htmlContent.trim()) {
                        editor.setComponents(htmlContent.trim());
                    }
                    if (cssContent.trim()) {
                        editor.setStyle(cssContent.trim());
                    }
                    showSaveIndicator(mode === 'import' ? t('js.reports.save.imported', 'Importato') : t('js.reports.save.applied', 'Applicato'));
                }
                closeModal();
            });
        }
    }

    /* Panel Tabs */
    function setupPanelTabs(editor) {
        var tabs = document.querySelectorAll('[data-gjs-panel-tab]');
        var panels = document.querySelectorAll('[data-gjs-panel]');
        if (!tabs.length) return;
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var target = tab.getAttribute('data-gjs-panel-tab');
                tabs.forEach(function (t) { t.classList.toggle('is-active', t === tab); });
                panels.forEach(function (p) { p.classList.toggle('is-active', p.getAttribute('data-gjs-panel') === target); });
            });
        });
    }

    /* Merge Fields */
    function setupMergeFieldHelper(editor) {
        /* Delegated click for all .merge-field-btn[data-field] — works for both
           legacy bottom cards and the new toolbar dropdowns. */
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.merge-field-btn[data-field]');
            if (!btn) return;
            e.preventDefault();
            var field = btn.getAttribute('data-field');
            if (field) insertMergeField(editor, field);
        });
    }

    function insertMergeField(editor, fieldName) {
        var selected = editor.getSelected();
        var tag = '{{ ' + fieldName + ' }}';
        if (selected) {
            var tagName = (selected.get('tagName') || '').toLowerCase();
            var textual = ['p','div','span','h1','h2','h3','h4','h5','h6','li','td','th','blockquote','label','strong','em'];
            if (textual.indexOf(tagName) !== -1) {
                var content = selected.getInnerHTML ? selected.getInnerHTML() : (selected.get('content') || '');
                if (selected.components && selected.components().length === 0) {
                    selected.set('content', content + ' ' + tag);
                } else {
                    selected.append({ type: 'textnode', content: ' ' + tag });
                }
                showSaveIndicator(t('js.reports.save.field_inserted', 'Campo inserito'));
                return;
            }
        }
        editor.addComponents({ type: 'text', content: '<p>' + tag + '</p>' });
        showSaveIndicator(t('js.reports.save.field_inserted', 'Campo inserito'));
    }

    /* Fullscreen */
    function setupFullscreen() {
        var btn = document.getElementById('fullscreen-btn');
        if (!btn) return;

        function toggleFullscreen() {
            var wrap = document.querySelector('.reports-designer-editor');
            var controls = document.querySelector('.reports-designer-controls');
            if (!wrap) return;
            wrap.classList.toggle('is-fullscreen');
            var isFs = wrap.classList.contains('is-fullscreen');
            btn.innerHTML = isFs ? '<i class="fa-solid fa-compress"></i>' : '<i class="fa-solid fa-expand"></i>';
            btn.title = isFs ? t('js.reports.fullscreen.exit', 'Esci da schermo intero (Esc)') : t('js.reports.fullscreen.enter', 'Schermo intero');
            document.body.classList.toggle('rp-fullscreen-active', isFs);
            setTimeout(function () { if (window.__grapesjs_editor) window.__grapesjs_editor.refresh(); }, 200);
        }

        btn.addEventListener('click', toggleFullscreen);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                var wrap = document.querySelector('.reports-designer-editor');
                if (wrap && wrap.classList.contains('is-fullscreen')) {
                    e.preventDefault();
                    toggleFullscreen();
                }
            }
        });
    }

    /* ================================================================
       Style preset modal — create/edit presets from inside the designer
       ================================================================ */
    function setupStylePresetModal() {
        var modalEl = document.getElementById('stylePresetModal');
        var select  = document.getElementById('style_preset_id');
        if (!modalEl || !select || !window.bootstrap || !window.__rpStyleRoutes) return;

        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        var form  = document.getElementById('rp-style-modal-form');
        var alertBox = document.getElementById('rp-style-modal-alert');
        var titleEl = document.getElementById('rp-style-modal-title');
        var methodEl = document.getElementById('rp-style-modal-method');
        var idEl = document.getElementById('rp-style-modal-id');
        var newBtn = document.getElementById('rp-style-new-btn');
        var editBtn = document.getElementById('rp-style-edit-btn');
        var submitBtn = document.getElementById('rp-style-modal-submit');

        function resetForm(mode) {
            if (alertBox) { alertBox.classList.add('d-none'); alertBox.textContent = ''; }
            form.reset();
            methodEl.value = mode === 'edit' ? 'PUT' : '';
        }

        function fillForm(data) {
            var map = {
                'rp-style-name':            data.name || '',
                'rp-style-description':     data.description || '',
                'rp-style-primary_color':   data.primary_color || '#3b82f6',
                'rp-style-secondary_color': data.secondary_color || '#64748b',
                'rp-style-accent_color':    data.accent_color || '#f97316',
                'rp-style-header_bg_color': data.header_bg_color || '#1e293b',
                'rp-style-header_text_color': data.header_text_color || '#ffffff',
                'rp-style-zebra_color':     data.zebra_color || '#f8fafc',
                'rp-style-font-family':     data.font_family || 'dejavusans',
                'rp-style-font-size':       data.font_size_base || 9,
            };
            Object.keys(map).forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.value = map[id];
            });
            var def = document.getElementById('rp-style-is-default');
            if (def) def.checked = !!(data.is_default && +data.is_default === 1);
        }

        if (newBtn) {
            newBtn.addEventListener('click', function () {
                resetForm('create');
                idEl.value = '';
                titleEl.textContent = t('js.reports.style_preset.new_title', 'Nuovo stile');
                modal.show();
            });
        }
        if (editBtn) {
            editBtn.addEventListener('click', function () {
                var id = select.value;
                if (!id) {
                    if (alertBox) {
                        alertBox.classList.remove('d-none');
                        alertBox.textContent = t('js.reports.style_preset.select_first', 'Seleziona prima uno stile da modificare.');
                    }
                    resetForm('create');
                    titleEl.textContent = t('js.reports.style_preset.new_title', 'Nuovo stile');
                    idEl.value = '';
                    modal.show();
                    return;
                }
                var url = window.__rpStyleRoutes.edit.replace(/0$/, id);
                fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .then(function (r) { return r.ok ? r.json() : Promise.reject(r.statusText); })
                    .then(function (json) {
                        if (!json || !json.ok || !json.preset) throw new Error(t('js.reports.style_preset.invalid_response', 'Risposta non valida'));
                        resetForm('edit');
                        idEl.value = json.preset.id;
                        titleEl.textContent = t('js.reports.style_preset.edit_title_prefix', 'Modifica stile — ') + (json.preset.name || '');
                        fillForm(json.preset);
                        modal.show();
                    })
                    .catch(function (err) {
                        notifyDesigner(t('js.reports.style_preset.load_failed_prefix', 'Impossibile caricare lo stile. ') + err, 'danger', {
                            title: t('js.reports.style_preset.load_failed_title', 'Stile non caricato'),
                            channel: 'banner',
                            duration: 10000
                        });
                    });
            });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (alertBox) { alertBox.classList.add('d-none'); alertBox.textContent = ''; }
            submitBtn.disabled = true;
            var isEdit = methodEl.value === 'PUT';
            var id = idEl.value;
            var url = isEdit
                ? window.__rpStyleRoutes.update.replace(/0$/, id)
                : window.__rpStyleRoutes.store;

            var fd = new FormData(form);
            fd.delete('_style_id');
            /* _method already in the form */

            fetch(url, {
                method: 'POST', /* Router reads _method from body for PUT */
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: fd,
            })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
                .then(function (res) {
                    submitBtn.disabled = false;
                    if (!res.ok || !res.json || !res.json.ok) {
                        var msg = (res.json && res.json.message) || t('js.reports.style_preset.save_error', 'Errore durante il salvataggio.');
                        if (alertBox) { alertBox.classList.remove('d-none'); alertBox.textContent = msg; }
                        return;
                    }
                    var preset = res.json.preset;
                    if (isEdit) {
                        var opt = select.querySelector('option[value="' + preset.id + '"]');
                        if (opt) opt.textContent = preset.name;
                    } else {
                        var newOpt = document.createElement('option');
                        newOpt.value = preset.id;
                        newOpt.textContent = preset.name;
                        select.appendChild(newOpt);
                        select.value = preset.id;
                    }
                    modal.hide();
                })
                .catch(function (err) {
                    submitBtn.disabled = false;
                    if (alertBox) {
                        alertBox.classList.remove('d-none');
                        alertBox.textContent = t('js.reports.style_preset.network_error_prefix', 'Errore di rete: ') + err;
                    }
                });
        });
    }

    /* ================================================================
       Smart Components — server-side resolved via data-prm-type
       ================================================================ */
    function registerSmartComponents(editor) {
        var bm = editor.BlockManager;

        bm.add('rp-sc-data-table', {
            label: blockLabel('fa-table-cells', t('js.reports.block.sc_data_table', 'Tabella dati (auto)')),
            category: t('js.reports.category.smart_components', 'Smart Components'),
            content: '<div data-prm-type="data_table" data-prm-config=\'{"columns":"auto","striped":true}\' class="prm-sc-placeholder"><strong>Tabella dati</strong><br><span class="small text-muted">Verrà sostituita con i dati della sorgente.</span></div>',
        });
        bm.add('rp-sc-calculated', {
            label: blockLabel('fa-calculator', t('js.reports.block.sc_calculated', 'Campo calcolato')),
            category: t('js.reports.category.smart_components', 'Smart Components'),
            content: '<span data-prm-type="calculated" data-prm-config=\'{"op":"sum","field":""}\' class="prm-sc-inline"><em>[somma]</em></span>',
        });
        bm.add('rp-sc-system-date', {
            label: blockLabel('fa-calendar-day', t('js.reports.block.sc_system_date', 'Data report')),
            category: t('js.reports.category.smart_components', 'Smart Components'),
            content: '<span data-prm-type="system" data-prm-config=\'{"key":"data_report"}\' class="prm-sc-inline">{{ data_report }}</span>',
        });
        bm.add('rp-sc-system-user', {
            label: blockLabel('fa-user', t('js.reports.block.sc_system_user', 'Utente corrente')),
            category: t('js.reports.category.smart_components', 'Smart Components'),
            content: '<span data-prm-type="system" data-prm-config=\'{"key":"utente"}\' class="prm-sc-inline">{{ utente }}</span>',
        });
        bm.add('rp-sc-system-company', {
            label: blockLabel('fa-building', t('js.reports.block.sc_system_company', 'Nome azienda')),
            category: t('js.reports.category.smart_components', 'Smart Components'),
            content: '<span data-prm-type="system" data-prm-config=\'{"key":"nome_azienda"}\' class="prm-sc-inline">{{ nome_azienda }}</span>',
        });
        bm.add('rp-sc-filters', {
            label: blockLabel('fa-filter', t('js.reports.block.sc_filters', 'Riepilogo filtri')),
            category: t('js.reports.category.smart_components', 'Smart Components'),
            content: '<div data-prm-type="filters_summary" class="prm-sc-placeholder"><strong>Filtri applicati</strong><br><span class="small text-muted">Elenco filtri attivi al momento dell\'esportazione.</span></div>',
        });
        bm.add('rp-sc-logo', {
            label: blockLabel('fa-image', t('js.reports.block.sc_logo', 'Logo azienda')),
            category: t('js.reports.category.smart_components', 'Smart Components'),
            content: '<div data-prm-type="logo" class="prm-sc-placeholder"><i class="fa-solid fa-image fa-2x"></i><br><span class="small text-muted">Logo dal preset grafico</span></div>',
        });
    }

    /* ================================================================
       Starter layouts keyed by source_type
       ================================================================ */
    function getStarterLayout(preset) {
        if (preset === 'document') {
            return (
                '<div style="font-family:Arial,sans-serif;padding:24px;">'
                + '<div style="display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #111827;padding-bottom:12px;margin-bottom:20px;">'
                + '<div><div data-prm-type="logo" class="prm-sc-placeholder"><i class="fa-solid fa-image fa-2x"></i></div></div>'
                + '<div style="text-align:right;"><div style="font-size:12px;color:#6b7280;">Data</div>'
                + '<strong><span data-prm-type="system" data-prm-config=\'{"key":"data_report"}\' class="prm-sc-inline">{{ data_report }}</span></strong></div>'
                + '</div>'
                + '<h1 style="margin:0 0 16px;">Documento</h1>'
                + '<div style="display:grid;grid-template-columns:auto 1fr;gap:8px 16px;font-size:14px;">'
                + '<strong>Campo 1</strong><span>Valore...</span>'
                + '<strong>Campo 2</strong><span>Valore...</span>'
                + '</div>'
                + '<div style="margin-top:30px;border-top:1px solid #e5e7eb;padding-top:16px;font-size:12px;color:#6b7280;text-align:center;">'
                + '<span data-prm-type="system" data-prm-config=\'{"key":"nome_azienda"}\' class="prm-sc-inline">{{ nome_azienda }}</span>'
                + ' &mdash; Pagina {{ pagina }} di {{ totale_pagine }}</div>'
                + '</div>'
            );
        }
        if (preset === 'aggregate') {
            return (
                '<div style="font-family:Arial,sans-serif;padding:24px;">'
                + '<h1 style="margin:0 0 6px;">Report riepilogativo</h1>'
                + '<p style="color:#6b7280;margin:0 0 24px;">Generato il <span data-prm-type="system" data-prm-config=\'{"key":"data_report"}\' class="prm-sc-inline">{{ data_report }}</span></p>'
                + '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;">'
                + '<div style="padding:20px;background:#f0f4ff;border-radius:10px;text-align:center;"><div style="font-size:11px;text-transform:uppercase;color:#667085;">Totale</div>'
                + '<div style="font-size:28px;font-weight:700;"><span data-prm-type="calculated" data-prm-config=\'{"op":"count","field":""}\' class="prm-sc-inline">0</span></div></div>'
                + '<div style="padding:20px;background:#e8f5e9;border-radius:10px;text-align:center;"><div style="font-size:11px;text-transform:uppercase;color:#2e7d32;">Somma</div>'
                + '<div style="font-size:28px;font-weight:700;"><span data-prm-type="calculated" data-prm-config=\'{"op":"sum","field":""}\' class="prm-sc-inline">0</span></div></div>'
                + '<div style="padding:20px;background:#fff3e0;border-radius:10px;text-align:center;"><div style="font-size:11px;text-transform:uppercase;color:#e65100;">Media</div>'
                + '<div style="font-size:28px;font-weight:700;"><span data-prm-type="calculated" data-prm-config=\'{"op":"avg","field":""}\' class="prm-sc-inline">0</span></div></div>'
                + '</div>'
                + '<div data-prm-type="filters_summary" class="prm-sc-placeholder" style="margin-bottom:16px;"><strong>Filtri applicati</strong></div>'
                + '</div>'
            );
        }
        /* default: list */
        return (
            '<div style="font-family:Arial,sans-serif;padding:24px;">'
            + '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">'
            + '<h1 style="margin:0;">Elenco</h1>'
            + '<span style="color:#6b7280;font-size:13px;">Generato il '
            + '<span data-prm-type="system" data-prm-config=\'{"key":"data_report"}\' class="prm-sc-inline">{{ data_report }}</span></span>'
            + '</div>'
            + '<div data-prm-type="data_table" data-prm-config=\'{"columns":"auto","striped":true}\' class="prm-sc-placeholder">'
            + '<strong>Tabella dati</strong><br><span class="small text-muted">Tutte le colonne della sorgente verranno renderizzate automaticamente.</span>'
            + '</div>'
            + '<div style="margin-top:20px;font-size:12px;color:#6b7280;text-align:right;">Pagina {{ pagina }} di {{ totale_pagine }}</div>'
            + '</div>'
        );
    }

    /* ================================================================
       Merge fields: search filter (legacy bottom list + toolbar dropdown)
       ================================================================ */
    function setupMergeFieldsSearch() {
        /* Legacy bottom card search */
        var legacyInput = document.getElementById('merge-fields-search');
        var legacyList = document.getElementById('merge-fields-list');
        if (legacyInput && legacyList) {
            legacyInput.addEventListener('input', function () {
                filterFieldList(legacyList, legacyInput.value);
            });
        }
        /* New toolbar dropdown search (source fields) */
        var ddInput = document.getElementById('rp-source-fields-search');
        var ddList  = document.getElementById('rp-source-fields-list');
        if (ddInput && ddList) {
            ddInput.addEventListener('input', function () {
                filterFieldList(ddList, ddInput.value);
            });
            /* Keep focus in search when opening the dropdown */
            var ddMenu = document.getElementById('rp-source-fields-dropdown');
            if (ddMenu) {
                ddMenu.addEventListener('click', function (e) {
                    /* Prevent dropdown closing when interacting with the search box */
                    if (e.target === ddInput) e.stopPropagation();
                });
            }
        }
    }

    function filterFieldList(container, query) {
        var q = (query || '').trim().toLowerCase();
        container.querySelectorAll('.merge-field-btn, .rp-field-dropdown-item').forEach(function (btn) {
            var label = (btn.textContent || '').toLowerCase();
            var field = (btn.getAttribute('data-field') || '').toLowerCase();
            btn.style.display = (!q || label.indexOf(q) !== -1 || field.indexOf(q) !== -1) ? '' : 'none';
        });
    }

    /* ================================================================
       Merge fields: drag-to-insert
       ================================================================ */
    function setupMergeFieldsDrag(editor) {
        document.querySelectorAll('.merge-field-btn').forEach(function (btn) {
            btn.addEventListener('dragstart', function (e) {
                var field = btn.getAttribute('data-field');
                if (!field) return;
                var tag = '{{ ' + field + ' }}';
                e.dataTransfer.setData('text/plain', tag);
                e.dataTransfer.effectAllowed = 'copy';
            });
        });

        /* Listen for drops inside the canvas iframe */
        editor.on('canvas:frame:load', function (frame) {
            var doc = frame.view && frame.view.getDoc();
            if (!doc) return;
            doc.addEventListener('dragover', function (e) {
                if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types, 'text/plain') !== -1) {
                    e.preventDefault();
                }
            });
            doc.addEventListener('drop', function (e) {
                var text = e.dataTransfer && e.dataTransfer.getData('text/plain');
                if (!text || text.indexOf('{{') === -1) return;
                e.preventDefault();
                var target = e.target;
                if (target && target.nodeType === 1) {
                    target.appendChild(doc.createTextNode(' ' + text + ' '));
                    /* Notify editor about the change */
                    try { editor.trigger('change:canvasOffset'); } catch (_) {}
                    var comp = editor.getWrapper();
                    if (comp) {
                        var current = editor.getHtml();
                        editor.setComponents(current);
                    }
                    showSaveIndicator(t('js.reports.save.field_inserted', 'Campo inserito'));
                }
            });
        });
    }

    /* ================================================================
       @-mention autocomplete inside RTE
       ================================================================ */
    function setupMentionAutocomplete(editor) {
        var popup = null;
        var activeEl = null;
        var filterText = '';
        var selectedIndex = 0;

        function getAllFields() {
            var seen = {};
            var fields = [];
            document.querySelectorAll('.merge-field-btn').forEach(function (btn) {
                var key = btn.getAttribute('data-field');
                var label = (btn.textContent || '').replace(/\s+/g, ' ').trim();
                if (!key || seen[key]) return;
                seen[key] = true;
                fields.push({ key: key, label: label });
            });
            return fields;
        }

        function closePopup() {
            if (popup) { popup.remove(); popup = null; }
            activeEl = null;
            filterText = '';
            selectedIndex = 0;
        }

        function buildPopup(items, x, y) {
            closePopup();
            popup = document.createElement('div');
            popup.className = 'rp-mention-popup';
            popup.style.position = 'fixed';
            popup.style.left = x + 'px';
            popup.style.top = y + 'px';
            items.slice(0, 8).forEach(function (item, idx) {
                var row = document.createElement('div');
                row.className = 'rp-mention-item' + (idx === selectedIndex ? ' is-active' : '');
                row.textContent = item.label + '  (' + item.key + ')';
                row.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    insertAtCaret(item.key);
                });
                popup.appendChild(row);
            });
            document.body.appendChild(popup);
        }

        function insertAtCaret(key) {
            if (!activeEl) return;
            var doc = activeEl.ownerDocument;
            var sel = doc.defaultView.getSelection();
            if (!sel.rangeCount) return;
            var range = sel.getRangeAt(0);
            /* delete the @filter text we typed */
            var startOffset = range.startOffset - (filterText.length + 1);
            if (startOffset < 0) startOffset = 0;
            var node = range.startContainer;
            if (node.nodeType === 3) {
                node.deleteData(startOffset, filterText.length + 1);
                range.setStart(node, startOffset);
                range.setEnd(node, startOffset);
            }
            var tag = doc.createTextNode('{{ ' + key + ' }} ');
            range.insertNode(tag);
            range.setStartAfter(tag);
            range.setEndAfter(tag);
            sel.removeAllRanges();
            sel.addRange(range);
            closePopup();
            showSaveIndicator(t('js.reports.save.field_inserted', 'Campo inserito'));
        }

        function handleKeydown(e) {
            if (!activeEl) return;
            if (!popup) return;
            var items = popup.querySelectorAll('.rp-mention-item');
            if (!items.length) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); selectedIndex = (selectedIndex + 1) % items.length; updateActiveRow(items); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); selectedIndex = (selectedIndex - 1 + items.length) % items.length; updateActiveRow(items); }
            else if (e.key === 'Enter' || e.key === 'Tab') {
                e.preventDefault();
                var row = items[selectedIndex];
                if (row) {
                    var text = row.textContent || '';
                    var m = text.match(/\(([^)]+)\)\s*$/);
                    if (m) insertAtCaret(m[1]);
                }
            }
            else if (e.key === 'Escape') { e.preventDefault(); closePopup(); }
        }

        function updateActiveRow(items) {
            items.forEach(function (it, idx) { it.classList.toggle('is-active', idx === selectedIndex); });
        }

        function onInput(e) {
            var el = e.target;
            if (!el || el.contentEditable !== 'true') return;
            var doc = el.ownerDocument;
            var sel = doc.defaultView.getSelection();
            if (!sel.rangeCount) { closePopup(); return; }
            var range = sel.getRangeAt(0);
            var node = range.startContainer;
            if (node.nodeType !== 3) { closePopup(); return; }
            var text = node.textContent.substring(0, range.startOffset);
            var m = text.match(/@([\w]*)$/);
            if (!m) { closePopup(); return; }
            filterText = m[1];
            activeEl = el;
            selectedIndex = 0;
            var all = getAllFields();
            var q = filterText.toLowerCase();
            var filtered = all.filter(function (f) {
                return !q || f.key.toLowerCase().indexOf(q) !== -1 || f.label.toLowerCase().indexOf(q) !== -1;
            });
            if (!filtered.length) { closePopup(); return; }
            var rect = range.getBoundingClientRect();
            var canvas = document.querySelector('.gjs-cv-canvas iframe');
            var offsetX = 0, offsetY = 0;
            if (canvas) {
                var cr = canvas.getBoundingClientRect();
                offsetX = cr.left; offsetY = cr.top;
            }
            buildPopup(filtered, rect.left + offsetX, rect.bottom + offsetY + 4);
        }

        editor.on('canvas:frame:load', function (frame) {
            var doc = frame.view && frame.view.getDoc();
            if (!doc) return;
            doc.addEventListener('input', onInput);
            doc.addEventListener('keydown', handleKeydown, true);
            doc.addEventListener('click', function () { closePopup(); });
        });
    }

    /* ================================================================
       Document bindings (CRUD inline via modal on document templates)
       ================================================================ */
    function setupDocumentBindings() {
        var card = document.getElementById('rp-bindings-card');
        var modalEl = document.getElementById('rpBindingModal');
        if (!card || !modalEl || typeof bootstrap === 'undefined' || !window.__rpBindingRoutes) return;

        var routes = window.__rpBindingRoutes;
        var modal = new bootstrap.Modal(modalEl);
        var form = document.getElementById('rp-binding-modal-form');
        var methodInput = document.getElementById('rp-binding-modal-method');
        var titleEl = document.getElementById('rp-binding-modal-title');
        var alertEl = document.getElementById('rp-binding-modal-alert');
        var moduleInput = document.getElementById('rp-binding-module');
        var labelInput = document.getElementById('rp-binding-label');
        var operationInput = document.getElementById('rp-binding-operation');
        var advancedDetails = document.getElementById('rp-binding-advanced');
        var tbody = document.getElementById('rp-bindings-tbody');
        var newBtn = document.getElementById('rp-binding-new-btn');
        var currentId = null;
        var userEditedOp = false;

        function csrf() {
            var m = document.querySelector('meta[name="csrf-token"]');
            if (m) return m.getAttribute('content') || '';
            var i = form.querySelector('input[name="_token"], input[name="csrf_token"]');
            return i ? i.value : '';
        }

        function slugify(s) {
            return (s || '').toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '')
                .substring(0, 64);
        }

        function resetForm() {
            form.reset();
            methodInput.value = '';
            currentId = null;
            userEditedOp = false;
            alertEl.classList.add('d-none');
            alertEl.textContent = '';
            if (advancedDetails) advancedDetails.removeAttribute('open');
            Array.prototype.forEach.call(form.querySelectorAll('.is-invalid'), function (el) {
                el.classList.remove('is-invalid');
            });
        }

        function openForCreate() {
            resetForm();
            titleEl.textContent = t('js.reports.bindings.new_title', 'Nuovo collegamento');
            modal.show();
        }

        function openForEdit(id) {
            resetForm();
            currentId = id;
            methodInput.value = 'PUT';
            titleEl.textContent = t('js.reports.bindings.edit_title', 'Modifica collegamento');
            fetch(routes.edit.replace(/0$/, id), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.binding) {
                        alertEl.textContent = t('js.reports.bindings.load_failed', 'Impossibile caricare i dati del collegamento.');
                        alertEl.classList.remove('d-none');
                        return;
                    }
                    var b = data.binding;
                    moduleInput.value = b.module || '';
                    labelInput.value = b.label || '';
                    operationInput.value = b.operation || '';
                    userEditedOp = true;
                    if (advancedDetails) advancedDetails.setAttribute('open', '');
                })
                .catch(function () {
                    alertEl.textContent = t('js.reports.bindings.network_error', 'Errore di rete nel caricamento.');
                    alertEl.classList.remove('d-none');
                });
            modal.show();
        }

        labelInput.addEventListener('input', function () {
            if (!userEditedOp) operationInput.value = slugify(labelInput.value);
        });
        operationInput.addEventListener('input', function () { userEditedOp = true; });

        if (newBtn) newBtn.addEventListener('click', openForCreate);

        tbody.addEventListener('click', function (e) {
            var editBtn = e.target.closest('.rp-binding-edit-btn');
            var delBtn = e.target.closest('.rp-binding-delete-btn');
            var row = e.target.closest('tr[data-binding-id]');
            if (!row) return;
            var id = parseInt(row.getAttribute('data-binding-id'), 10);
            if (editBtn) openForEdit(id);
            else if (delBtn) deleteBinding(id, row);
        });

        function deleteBinding(id, row) {
            var deleteBody = t('js.reports.bindings.delete_body', 'Eliminare questo collegamento?');
            var confirmDeletePromise = typeof window.appConfirm === 'function'
                ? window.appConfirm({
                    title: t('js.reports.bindings.delete_title', 'Elimina collegamento'),
                    body: deleteBody,
                    confirmLabel: t('js.common.delete', 'Elimina'),
                    confirmClass: 'btn-danger'
                })
                : Promise.resolve(window.confirm(deleteBody));

            confirmDeletePromise.then(function (ok) {
                if (!ok) return;

                var fd = new FormData();
                fd.append('_method', 'DELETE');
                fd.append('_token', csrf());
                fetch(routes.destroy.replace(/0$/, id), {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                })
                    .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
                    .then(function (res) {
                        if (!res.ok || !res.data || !res.data.ok) {
                            notifyDesigner((res.data && res.data.message) || t('js.reports.bindings.delete_error', 'Errore durante l\'eliminazione.'), 'danger', {
                                title: t('js.reports.bindings.delete_failed_title', 'Eliminazione non riuscita'),
                                channel: 'banner',
                                duration: 10000
                            });
                            return;
                        }
                        row.remove();
                        ensureEmptyRow();
                    });
            });
        }

        function ensureEmptyRow() {
            if (tbody.querySelectorAll('tr[data-binding-id]').length === 0) {
                if (!document.getElementById('rp-bindings-empty')) {
                    var tr = document.createElement('tr');
                    tr.id = 'rp-bindings-empty';
                    tr.innerHTML = '<td colspan="4" class="text-center text-muted py-3">'
                        + '<i class="fa-solid fa-circle-info me-1"></i>'
                        + t('js.reports.bindings.empty_row', 'Nessun collegamento. Aggiungi il primo per mostrare un pulsante "Genera PDF" nel modulo.') + '</td>';
                    tbody.appendChild(tr);
                }
            }
        }

        function removeEmptyRow() {
            var empty = document.getElementById('rp-bindings-empty');
            if (empty) empty.remove();
        }

        function upsertRow(b) {
            removeEmptyRow();
            var existing = tbody.querySelector('tr[data-binding-id="' + b.id + '"]');
            var html = '<td></td><td></td><td><code class="small"></code></td>'
                + '<td class="text-end">'
                + '<button type="button" class="btn btn-sm btn-outline-secondary rp-binding-edit-btn" data-bs-toggle="tooltip" title="' + t('js.common.edit', 'Modifica') + '"><i class="fa-solid fa-pen"></i></button> '
                + '<button type="button" class="btn btn-sm btn-outline-danger rp-binding-delete-btn" data-bs-toggle="tooltip" title="' + t('js.common.delete', 'Elimina') + '"><i class="fa-solid fa-trash"></i></button>'
                + '</td>';
            var row = existing;
            if (!row) {
                row = document.createElement('tr');
                row.setAttribute('data-binding-id', String(b.id));
                tbody.appendChild(row);
            }
            row.innerHTML = html;
            var cells = row.children;
            cells[0].textContent = b.module || '';
            cells[1].textContent = b.label || '';
            cells[2].firstChild.textContent = b.operation || '';
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            alertEl.classList.add('d-none');
            alertEl.textContent = '';
            var isEdit = methodInput.value === 'PUT';
            var url = isEdit ? routes.update.replace(/0$/, currentId) : routes.store;
            var fd = new FormData(form);
            fetch(url, {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
            })
                .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
                .then(function (res) {
                    if (!res.ok || !res.data || !res.data.ok) {
                        var msg = (res.data && (res.data.message || res.data.error)) || t('js.reports.style_preset.save_error', 'Errore durante il salvataggio.');
                        if (res.data && res.data.errors) {
                            var parts = [];
                            Object.keys(res.data.errors).forEach(function (k) {
                                var v = res.data.errors[k];
                                parts.push(Array.isArray(v) ? v.join(' ') : String(v));
                            });
                            if (parts.length) msg = parts.join(' ');
                        }
                        alertEl.textContent = msg;
                        alertEl.classList.remove('d-none');
                        return;
                    }
                    upsertRow(res.data.binding);
                    modal.hide();
                });
        });
    }
})();
