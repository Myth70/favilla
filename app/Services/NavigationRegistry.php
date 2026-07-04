<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ModuleLoader;

/**
 * Unica fonte di voci di navigazione per le diverse "superfici" della UI:
 *   - sidebar       -> menu laterale principale (moduli system/admin)
 *   - user_menu     -> dropdown utente nell'header (moduli user-facing)
 *   - radial        -> context menu radiale (tasto destro)
 *   - quick_access  -> widget Home "Accesso rapido"
 *
 * Sorgenti (in ordine di priorita', per ciascun modulo abilitato):
 *   1. module.json -> chiave "navigation" (entry esplicite con `surfaces`)
 *   2. legacy `menu` (da app/Config/modules.php o da module.json)
 *      -> mappata implicitamente alla sola surface "sidebar"
 *
 * Le entry sono normalizzate e ordinate per `order` (ascendente).
 * Il filtro permessi e' applicato solo in forSurface(): utenti con ruolo
 * "admin" vedono tutto, gli altri vedono solo le entry senza permission
 * o con permission presente in $userPermissions.
 */
class NavigationRegistry
{
    public const SURFACE_SIDEBAR       = 'sidebar';
    public const SURFACE_USER_MENU     = 'user_menu';
    public const SURFACE_RADIAL        = 'radial';
    public const SURFACE_QUICK_ACCESS  = 'quick_access';

    /** @var list<array>|null Cached normalized entries for this registry instance */
    private ?array $entries = null;

    public function __construct(private ModuleLoader $loader)
    {
    }

    /**
     * Ritorna tutte le entry dei moduli abilitati, normalizzate e ordinate.
     *
     * @return list<array{
     *   id:string, label:string, icon:string, route:string,
     *   permission:?string, order:int, surfaces:list<string>,
     *   module:string, children:array
     * }>
     */
    public function all(): array
    {
        if ($this->entries !== null) {
            return $this->entries;
        }

        $entries = [];
        foreach ($this->loader->getModules() as $module) {
            if (!($module['enabled'] ?? true)) {
                continue;
            }
            foreach ($this->collectEntriesForModule($module) as $entry) {
                $entries[] = $entry;
            }
        }

        usort($entries, static fn (array $a, array $b): int => ($a['order'] ?? 100) <=> ($b['order'] ?? 100));

        $this->entries = $entries;
        return $entries;
    }

    /**
     * Ritorna le entry filtrate per `surface` e per i permessi utente.
     *
     * @param string     $surface         Uno dei SURFACE_* costanti.
     * @param array|null $userPermissions Slugs posseduti dall'utente. Passare null per disabilitare il filtro permessi.
     * @param array      $userRoles       Ruoli dell'utente (admin bypassa il filtro permessi).
     * @return list<array>
     */
    public function forSurface(
        string $surface,
        ?array $userPermissions = null,
        array $userRoles = []
    ): array {
        $isAdmin = in_array('admin', $userRoles, true);
        $result = [];
        foreach ($this->all() as $entry) {
            if (!in_array($surface, $entry['surfaces'], true)) {
                continue;
            }

            if ($this->isHiddenForEdition($entry, $surface)) {
                continue;
            }

            if ($userPermissions !== null && !$isAdmin) {
                $perm = $entry['permission'];
                if ($perm !== null && !in_array($perm, $userPermissions, true)) {
                    continue;
                }
            }

            $result[] = $entry;
        }
        return $result;
    }

    /**
     * Invalida la cache interna. Necessario nei test o quando la config dei
     * moduli viene mutata a runtime (raro).
     */
    public function invalidate(): void
    {
        $this->entries = null;
    }

    // ------------------------------------------------------------------
    // Private
    // ------------------------------------------------------------------

    /**
     * @return list<array>
     */
    private function collectEntriesForModule(array $module): array
    {
        $name = (string) ($module['name'] ?? '');
        if ($name === '') {
            return [];
        }

        $meta = $this->loader->readModuleJson($name);

        // Source 1: esplicita chiave "navigation" nel module.json
        if (is_array($meta['navigation'] ?? null)) {
            $out = [];
            foreach ($meta['navigation'] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $out[] = $this->normalizeEntry($entry, $name);
            }
            return $out;
        }

        // Source 2: fallback legacy "menu".
        // IMPORTANTE: leggiamo solo $module['menu'] (popolato dal ModuleLoader da
        // modules.php per moduli registrati, o da module.json per auto-discovered).
        // NON usiamo $meta['menu'] come fallback: se un modulo e' in modules.php
        // senza "menu", significa che non deve comparire in sidebar — e module.json
        // puo' contenere menu storici / non piu' usati.
        $legacyMenu = $module['menu'] ?? [];
        if (!is_array($legacyMenu) || $legacyMenu === []) {
            return [];
        }

        $out = [];
        foreach ($legacyMenu as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            // Non sovrascrive surfaces se per caso dichiarate inline.
            $entry['surfaces'] = $entry['surfaces'] ?? [self::SURFACE_SIDEBAR];
            $out[] = $this->normalizeEntry($entry, $name);
        }
        return $out;
    }

    /**
     * True se l'entry va nascosta sulla surface indicata per l'edizione corrente
     * (hidden ≠ disabled: il modulo resta attivo, sparisce solo dalla sidebar).
     */
    private function isHiddenForEdition(array $entry, string $surface): bool
    {
        if ($surface !== self::SURFACE_SIDEBAR) {
            return false;
        }

        $hidden = edition_profile()['sidebar_hidden_modules'] ?? [];
        return in_array($entry['module'], $hidden, true);
    }

    private function normalizeEntry(array $entry, string $moduleName): array
    {
        $surfaces = $entry['surfaces'] ?? [self::SURFACE_SIDEBAR];
        if (!is_array($surfaces) || $surfaces === []) {
            $surfaces = [self::SURFACE_SIDEBAR];
        }

        $route = (string) ($entry['route'] ?? '');
        $id    = (string) ($entry['id'] ?? ($route !== '' ? $route : $moduleName));

        // i18n: translate the nav label via the `nav` overlay keyed by entry id,
        // falling back to the module.json label (canonical Italian) on miss.
        $label = t_line('nav', $id, (string) ($entry['label'] ?? ''));

        return [
            'id'         => $id,
            'label'      => $label,
            'icon'       => (string) ($entry['icon'] ?? 'fa-circle'),
            'route'      => $route,
            'permission' => $entry['permission'] ?? null,
            'order'      => (int) ($entry['order'] ?? 100),
            'surfaces'   => array_values(array_map('strval', $surfaces)),
            'module'     => $moduleName,
            'children'   => is_array($entry['children'] ?? null) ? $entry['children'] : [],
        ];
    }
}
