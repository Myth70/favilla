<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Core\Router;

class AdminIndexService
{
    private Router $router;

    /** @var array<string,mixed>|null */
    private ?array $catalogCache = null;

    public function __construct()
    {
        $this->router = app(Router::class);
    }

    /**
     * Build the admin catalog visible to a user with the given permission and role slugs.
     * The Service does not read the session: the caller must pass them explicitly.
     *
     * @param string[] $permissions  permission slugs available to the user
     * @param string[] $roles        role slugs of the user
     */
    public function getCatalog(array $permissions, array $roles): array
    {
        if ($this->catalogCache !== null) {
            return $this->catalogCache;
        }

        $sections = $this->filterSections($this->buildCatalog(), $permissions, $roles);

        $this->catalogCache = [
            'sections' => $sections,
            'summary'  => $this->buildSummary($sections),
        ];

        return $this->catalogCache;
    }

    /**
     * Flat list of links across all sections. Same permission/role contract as getCatalog().
     *
     * @param string[] $permissions
     * @param string[] $roles
     */
    public function getFlatCatalog(array $permissions, array $roles): array
    {
        $catalog = $this->getCatalog($permissions, $roles);
        $flat = [];

        foreach ($catalog['sections'] as $section) {
            foreach ($section['groups'] as $group) {
                foreach ($group['links'] as $link) {
                    $flat[] = [
                        'label'       => $link['label'],
                        'description' => $link['description'],
                        'icon'        => $link['icon'],
                        'url'         => $link['url'],
                        'group'       => $group['title'],
                        'section'     => $section['title'],
                        'search_text' => $link['search_text'],
                    ];
                }
            }
        }

        return $flat;
    }

    // ────────────────────────────────────────────────────────────────
    // Catalog builder: hardcoded core + autodiscovered module panels
    // ────────────────────────────────────────────────────────────────

    private function buildCatalog(): array
    {
        $base = $this->catalog();
        $discovered = $this->discoverModulePanels();

        if ($discovered === []) {
            return $base;
        }

        // Merge discovered groups into sections by title, or append new sections
        $sectionIndex = [];
        foreach ($base as $i => $section) {
            $sectionIndex[$section['title']] = $i;
        }

        foreach ($discovered as $panel) {
            $sectionTitle  = $panel['section']  ?? 'Pannelli admin dei moduli';
            $sectionEyebrow = $panel['eyebrow'] ?? 'Moduli attivi';
            $groups        = $panel['groups']   ?? [];

            if (isset($sectionIndex[$sectionTitle])) {
                $idx = $sectionIndex[$sectionTitle];
                foreach ($groups as $group) {
                    $base[$idx]['groups'][] = $this->normalizeDiscoveredGroup($group);
                }
            } else {
                $newIdx = count($base);
                $base[] = [
                    'eyebrow'     => $sectionEyebrow,
                    'title'       => $sectionTitle,
                    'description' => $panel['description'] ?? '',
                    'groups'      => array_map([$this, 'normalizeDiscoveredGroup'], $groups),
                ];
                $sectionIndex[$sectionTitle] = $newIdx;
            }
        }

        return $base;
    }

    /**
     * Scan all module.json files for an "admin_panel" field and collect panels.
     * Skips modules that are not enabled.
     *
     * module.json schema for admin_panel:
     * {
     *   "admin_panel": {
     *     "section":     "Pannelli admin dei moduli",  // target section title
     *     "eyebrow":     "Moduli attivi",              // used when creating a new section
     *     "description": "...",                        // used only when creating a new section
     *     "groups": [
     *       {
     *         "title":       "...",
     *         "description": "...",
     *         "icon":        "fa-solid fa-...",
     *         "module":      "...",      // defaults to module name from module.json
     *         "flows":       [],
     *         "links": [
     *           {
     *             "label":       "...",
     *             "route":       "...",
     *             "permission":  "...",
     *             "description": "...",
     *             "icon":        "fa-solid fa-...",
     *             "keywords":    "..."
     *           }
     *         ]
     *       }
     *     ]
     *   }
     * }
     *
     * @return list<array<string,mixed>>
     */
    private function discoverModulePanels(): array
    {
        $modulesDir = dirname(__DIR__, 2); // app/Modules
        $panels = [];

        $moduleJsonFiles = glob($modulesDir . '/*/module.json');
        if ($moduleJsonFiles === false) {
            return [];
        }

        foreach ($moduleJsonFiles as $file) {
            $raw = file_get_contents($file);
            if ($raw === false) {
                continue;
            }

            $json = json_decode($raw, true);
            if (!is_array($json) || empty($json['admin_panel'])) {
                continue;
            }

            $panel = $json['admin_panel'];
            if (!is_array($panel) || empty($panel['groups'])) {
                continue;
            }

            // Inject module name as default for groups that omit it
            $moduleName = $json['name'] ?? basename(dirname($file));
            foreach ($panel['groups'] as &$group) {
                if (empty($group['module'])) {
                    $group['module'] = $moduleName;
                }
            }
            unset($group);

            $panels[] = $panel;
        }

        return $panels;
    }

    /**
     * Normalize a group coming from module.json into the internal format
     * expected by $this->group() — plain array, no method call needed.
     *
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    private function normalizeDiscoveredGroup(array $raw): array
    {
        $links = [];
        foreach ($raw['links'] ?? [] as $link) {
            $links[] = $this->link(
                (string) ($link['label']       ?? ''),
                (string) ($link['route']       ?? ''),
                isset($link['permission']) && $link['permission'] !== '' ? (string) $link['permission'] : null,
                (string) ($link['description'] ?? ''),
                (string) ($link['icon']        ?? 'fa-solid fa-circle'),
                (string) ($link['keywords']    ?? ''),
                (array)  ($link['params']      ?? []),
                isset($link['tooltip']) ? (string) $link['tooltip'] : null
            );
        }

        return $this->group(
            (string) ($raw['title']       ?? ''),
            (string) ($raw['description'] ?? ''),
            (string) ($raw['icon']        ?? 'fa-solid fa-puzzle-piece'),
            (string) ($raw['module']      ?? ''),
            $links,
            array_map('strval', (array) ($raw['flows'] ?? []))
        );
    }

    /**
     * @param array<int,array<string,mixed>> $sections
     * @param string[] $permissions
     * @param string[] $roles
     */
    private function filterSections(array $sections, array $permissions, array $roles): array
    {
        $isAdmin = in_array('admin', $roles, true);
        $visibleSections = [];

        foreach ($sections as $section) {
            $visibleGroups = [];

            foreach ($section['groups'] as $group) {
                $visibleLinks = [];

                foreach ($group['links'] as $link) {
                    if (!$this->hasAccess($link['permission'] ?? null, $permissions, $isAdmin)) {
                        continue;
                    }

                    $url = $this->resolveRoute($link['route'], $link['params'] ?? []);
                    if ($url === null) {
                        continue;
                    }

                    $visibleLinks[] = [
                        'label'       => $link['label'],
                        'description' => $link['description'],
                        'icon'        => $link['icon'],
                        'route'       => $link['route'],
                        'url'         => $url,
                        'tooltip'     => $link['tooltip'] ?? $link['description'],
                        'search_text' => $this->normalizeSearchText([
                            $link['label'],
                            $link['description'],
                            $link['keywords'] ?? '',
                            $group['title'],
                            $group['module'],
                            $section['title'],
                        ]),
                    ];
                }

                if ($visibleLinks === []) {
                    continue;
                }

                $flows = [];
                foreach ($group['flows'] ?? [] as $flow) {
                    if (is_string($flow) && trim($flow) !== '') {
                        $flows[] = $flow;
                    }
                }

                $visibleGroups[] = [
                    'title'       => $group['title'],
                    'description' => $group['description'],
                    'icon'        => $group['icon'],
                    'module'      => $group['module'],
                    'links'       => $visibleLinks,
                    'flows'       => $flows,
                    'search_text' => $this->normalizeSearchText([
                        $group['title'],
                        $group['description'],
                        $group['module'],
                        implode(' ', $flows),
                        $section['title'],
                    ]),
                ];
            }

            if ($visibleGroups === []) {
                continue;
            }

            $visibleSections[] = [
                'eyebrow'     => $section['eyebrow'],
                'title'       => $section['title'],
                'description' => $section['description'],
                'groups'      => $visibleGroups,
            ];
        }

        return $visibleSections;
    }

    private function buildSummary(array $sections): array
    {
        $groupsCount = 0;
        $linksCount = 0;
        $modules = [];

        foreach ($sections as $section) {
            $groupsCount += count($section['groups']);

            foreach ($section['groups'] as $group) {
                $linksCount += count($group['links']);
                $modules[$group['module']] = true;
            }
        }

        return [
            'sections' => count($sections),
            'groups'   => $groupsCount,
            'links'    => $linksCount,
            'modules'  => count($modules),
        ];
    }

    private function hasAccess(?string $permission, array $permissions, bool $isAdmin): bool
    {
        if ($permission === null || $permission === '') {
            return true;
        }

        return $isAdmin || in_array($permission, $permissions, true);
    }

    private function resolveRoute(string $routeName, array $params = []): ?string
    {
        try {
            return $this->router->url($routeName, $params);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeSearchText(array $parts): string
    {
        $values = [];

        foreach ($parts as $part) {
            if (is_string($part) && trim($part) !== '') {
                $values[] = strtolower(trim($part));
            }
        }

        return implode(' ', $values);
    }

    private function catalog(): array
    {
        return [
            [
                'eyebrow'     => 'Core app',
                'title'       => 'Governance piattaforma',
                'description' => 'Accessi, configurazioni, comunicazioni e versioning dell applicazione.',
                'groups'      => [
                    $this->group(
                        'Identita e accessi',
                        'Utenti, ruoli, permessi e operativita quotidiana dell area admin.',
                        'fa-solid fa-users-gear',
                        'Admin',
                        [
                            $this->link('Dashboard', 'admin.dashboard', 'admin.users.view', 'KPI, log recenti e stato moduli.', 'fa-solid fa-gauge-high', 'panoramica overview kpi'),
                            $this->link('Utenti', 'admin.users.index', 'admin.users.view', 'Elenco account, stato attivazione e gestione accessi.', 'fa-solid fa-users', 'account persone profili'),
                            $this->link('Nuovo utente', 'admin.users.create', 'admin.users.create', 'Creazione rapida di un nuovo account.', 'fa-solid fa-user-plus', 'crea account onboarding'),
                            $this->link('Ruoli e permessi', 'admin.roles.index', 'admin.roles.manage', 'Matrice ruoli, permessi assegnati e governo RBAC.', 'fa-solid fa-user-lock', 'rbac autorizzazioni policy'),
                            $this->link('Nuovo ruolo', 'admin.roles.create', 'admin.roles.manage', 'Definisce un nuovo profilo autorizzativo.', 'fa-solid fa-shield-plus', 'crea ruolo permessi'),
                        ],
                        [
                            'Dettaglio utente',
                            'Modifica profilo',
                            'Reset password',
                            'Revoca sessioni',
                            'Impersonazione',
                        ]
                    ),
                    $this->group(
                        'Sistema e moduli',
                        'Configurazione centrale, moduli installati e automazioni operative.',
                        'fa-solid fa-server',
                        'Admin',
                        [
                            $this->link('Moduli', 'admin.modules.index', 'admin.modules.manage', 'Gestione stato, export e manutenzione dei moduli.', 'fa-solid fa-puzzle-piece', 'moduli installazione manutenzione'),
                            $this->link('Import moduli', 'admin.modules.import', 'admin.modules.manage', 'Importa un pacchetto modulo nell applicazione.', 'fa-solid fa-file-import', 'installa modulo pacchetto'),
                            $this->link('Configurazione', 'admin.settings.index', 'admin.settings.manage', 'Impostazioni applicative e parametri di sistema.', 'fa-solid fa-sliders', 'config settings app'),
                            $this->link('Backup database', 'backup.index', 'backup.manage', 'Creazione, elenco e gestione backup disponibili.', 'fa-solid fa-database', 'backup restore database'),
                            $this->link('Scheduler', 'scheduler.index', 'scheduler.view', 'Monitoraggio job pianificati e storico esecuzioni.', 'fa-solid fa-clock', 'cron job pianificazioni task'),
                            $this->link('Nuovo job scheduler', 'scheduler.create', 'scheduler.manage', 'Aggiunge una nuova attivita schedulata.', 'fa-solid fa-calendar-plus', 'crea job scheduler cron'),
                        ],
                        [
                            'Export modulo',
                            'Disinstallazione modulo',
                            'Import permessi modulo',
                            'Esecuzione manuale job',
                            'Restore backup',
                        ]
                    ),
                    $this->group(
                        'Comunicazioni e versioning',
                        'Email, dispatcher notifiche e tracciamento delle release interne.',
                        'fa-solid fa-envelope-open-text',
                        'Admin',
                        [
                            $this->link('Email', 'admin.mail.index', 'admin.mail.manage', 'Template, test invio e configurazione del canale email.', 'fa-solid fa-envelope', 'mail template smtp'),
                            $this->link('Nuovo template email', 'admin.mail.templates.create', 'admin.mail.manage', 'Crea un template mail riutilizzabile.', 'fa-solid fa-file-circle-plus', 'crea template email'),
                            $this->link('Log email', 'admin.mail.log', 'admin.mail.log', 'Storico invii, esiti e diagnostica canale.', 'fa-solid fa-list', 'storico mail log'),
                            $this->link('Invio notifiche', 'admin.notifications.send', 'notifications.admin.send', 'Invia una notifica manuale agli utenti.', 'fa-solid fa-paper-plane', 'notifiche manuali messaggi'),
                            $this->link('Dispatcher notifiche', 'admin.notifications.settings', 'notifications.admin.manage', 'Canali, eventi e template del dispatcher centralizzato.', 'fa-solid fa-bell', 'notification dispatcher eventi telegram'),
                            $this->link('Changelog', 'admin.changelog.index', 'admin.changelog.manage', 'Versioni interne, note di rilascio e storico pubblicazioni.', 'fa-solid fa-code-branch', 'release changelog versioni'),
                            $this->link('Nuova versione', 'admin.changelog.create', 'admin.changelog.manage', 'Registra una nuova release applicativa.', 'fa-solid fa-square-plus', 'crea release changelog'),
                        ],
                        [
                            'Simulazione eventi notifica',
                            'Gestione bot notifiche',
                            'Test invio email',
                            'Pubblicazione release',
                        ]
                    ),
                ],
            ],
            [
                'eyebrow'     => 'Controllo',
                'title'       => 'Sicurezza, privacy e controllo',
                'description' => 'Monitoraggio tecnico, hardening, continuita operativa e data governance.',
                'groups'      => [
                    $this->group(
                        'Sicurezza operativa',
                        'Incidenti, hardening, chiavi e segregazione dei compiti.',
                        'fa-solid fa-shield-halved',
                        'Admin',
                        [
                            $this->link('Incidenti sicurezza', 'admin.security.incidents', 'admin.security.view', 'Registro incidenti e stato della gestione operativa.', 'fa-solid fa-triangle-exclamation', 'incidenti security'),
                            $this->link('Asset sicurezza', 'admin.security.assets', 'admin.security.view', 'Mappa asset critici e relativo presidio.', 'fa-solid fa-network-wired', 'asset sicurezza inventario'),
                            $this->link('Hardening', 'admin.security.hardening', 'admin.security.view', 'Checklist di rafforzamento e posture di sistema.', 'fa-solid fa-screwdriver-wrench', 'hardening security baseline'),
                            $this->link('Log sicurezza', 'admin.security.logs', 'admin.security.view', 'Stato rotazione e retention dei log di sicurezza.', 'fa-solid fa-file-shield', 'security logs rotate purge'),
                            $this->link('Rotazione chiavi', 'admin.security.keys', 'admin.security.view', 'Registro chiavi sensibili e ultime rotazioni.', 'fa-solid fa-key', 'chiavi crittografia secrets'),
                            $this->link('Segregazione ruoli', 'admin.security.sod', 'admin.security.view', 'Vincoli SoD e incompatibilita tra ruoli.', 'fa-solid fa-scale-balanced', 'sod segregation duties'),
                            $this->link('Data retention', 'admin.retention.index', 'admin.security.view', 'Policy di conservazione, attivazione e run manuali.', 'fa-solid fa-hourglass-half', 'retention data policy'),
                        ],
                        [
                            'Rotazione log',
                            'Purge log',
                            'Rotazione chiavi registrata',
                            'Vincoli SoD',
                            'Esecuzione retention',
                        ]
                    ),
                    $this->group(
                        'Monitoraggio e salute',
                        'Audit applicativo e stato di salute dell installazione.',
                        'fa-solid fa-heart-pulse',
                        'HealthCheck',
                        [
                            $this->link('Audit e log', 'admin.logs.index', 'admin.logs.view', 'Audit trail, tentativi login, sessioni ed errori PHP.', 'fa-solid fa-list-check', 'audit log sessioni errori'),
                            $this->link('HealthCheck', 'healthcheck.index', 'healthcheck.view', 'Snapshot dello stato generale di piattaforma e servizi.', 'fa-solid fa-stethoscope', 'health status sistema'),
                            $this->link('Storico HealthCheck', 'healthcheck.history', 'healthcheck.history', 'Trend e storico dei controlli eseguiti.', 'fa-solid fa-clock-rotate-left', 'storico health check'),
                        ],
                        [
                            'Export log',
                            'Pulizia audit',
                            'Export HealthCheck',
                        ]
                    ),
                    $this->group(
                        'Reportistica e documenti',
                        'Template, stili, storico export e binding documentali del modulo Reports.',
                        'fa-solid fa-file-export',
                        'Reports',
                        [
                            $this->link('Dashboard report', 'reports.index', 'reports.view', 'Punto di ingresso alla reportistica applicativa.', 'fa-solid fa-chart-column', 'reports dashboard export'),
                            $this->link('Template report', 'reports.templates.index', 'reports.view', 'Catalogo template custom e bundled.', 'fa-solid fa-file-lines', 'template report'),
                            $this->link('Nuovo template report', 'reports.templates.new', 'reports.create', 'Crea un nuovo modello di report dal wizard.', 'fa-solid fa-file-circle-plus', 'crea template report wizard'),
                            $this->link('Storico export', 'reports.history.index', 'reports.view', 'Storico generazioni e download disponibili.', 'fa-solid fa-timeline', 'history export report'),
                        ],
                        [
                            'Preview template',
                            'Quick export',
                            'Download storico',
                        ]
                    ),
                ],
            ],
            // ── "Pannelli admin dei moduli" is populated entirely via autodiscovery.
            // Each module declares its own admin_panel in module.json.
            // See: discoverModulePanels() / normalizeDiscoveredGroup()
            [
                'eyebrow'     => 'Moduli attivi',
                'title'       => 'Pannelli admin dei moduli',
                'description' => 'Aree gestionali dedicate ai moduli applicativi con console amministrativa propria.',
                'groups'      => [],
            ],
        ];
    }

    private function group(string $title, string $description, string $icon, string $module, array $links, array $flows = []): array
    {
        return [
            'title'       => $title,
            'description' => $description,
            'icon'        => $icon,
            'module'      => $module,
            'links'       => $links,
            'flows'       => $flows,
        ];
    }

    private function link(
        string $label,
        string $route,
        ?string $permission,
        string $description,
        string $icon,
        string $keywords = '',
        array $params = [],
        ?string $tooltip = null
    ): array {
        return [
            'label'       => $label,
            'route'       => $route,
            'permission'  => $permission,
            'description' => $description,
            'icon'        => $icon,
            'keywords'    => $keywords,
            'params'      => $params,
            'tooltip'     => $tooltip,
        ];
    }
}
