<?php

declare(strict_types=1);

namespace App\Modules\Feedback\Services;

use App\Core\ModuleLoader;
use App\Modules\Feedback\Repositories\FeedbackRepository;
use App\Modules\Notifications\Services\NotificationService;
use App\Services\AuditService;

class FeedbackService
{
    public const TIPI     = ['bug', 'funzionalita', 'domanda'];
    public const SEVERITA = ['bassa', 'media', 'alta', 'critica'];
    public const STATI    = ['nuova', 'in_lavorazione', 'risolta', 'chiusa', 'non_risolvibile'];
    /** Stati "aperti": fuori da questi la segnalazione è chiusa → si elimina il DOM. */
    public const STATI_APERTI = ['nuova', 'in_lavorazione'];

    private const MAX_DESCRIZIONE   = 5000;
    private const MAX_CONTEXT_BYTES = 65536;
    private const MAX_DOM_BYTES     = 500000;
    private const MAX_ERRORS        = 30;
    private const MAX_BREADCRUMB    = 30;
    private const DEDUP_WINDOW_SEC  = 300;

    public function __construct(private FeedbackRepository $repo)
    {
    }

    /**
     * Crea una segnalazione dai dati (già sanitizzati) del Controller + contesto client.
     *
     * @param array  $input         Campi testuali: tipo, severita, titolo, descrizione, passi
     * @param array  $clientContext Contesto JSON decodificato inviato dal browser
     * @param string $dom           Snapshot DOM sanitizzato (opzionale)
     * @return array{id:int, ref_code:string, duplicate:bool}
     */
    public function create(array $input, array $clientContext, string $dom = ''): array
    {
        $descrizione = trim((string) ($input['descrizione'] ?? ''));
        if ($descrizione === '') {
            throw new \InvalidArgumentException('La descrizione è obbligatoria.');
        }
        $descrizione = mb_substr($descrizione, 0, self::MAX_DESCRIZIONE);

        $user = auth();

        // Anti-doppione: stessa descrizione dallo stesso utente entro 5 min → idempotente.
        $dup = $this->repo->findRecentDuplicate($user['id'] ?? null, $descrizione, self::DEDUP_WINDOW_SEC);
        if ($dup !== null) {
            return ['id' => (int) $dup['id'], 'ref_code' => (string) $dup['ref_code'], 'duplicate' => true];
        }

        $tipo     = in_array($input['tipo'] ?? '', self::TIPI, true) ? $input['tipo'] : 'bug';
        $severita = in_array($input['severita'] ?? '', self::SEVERITA, true) ? $input['severita'] : 'media';

        $passi  = mb_substr(trim((string) ($input['passi'] ?? '')), 0, self::MAX_DESCRIZIONE);
        $titolo = trim((string) ($input['titolo'] ?? ''));
        $titolo = $titolo === ''
            ? mb_substr($descrizione, 0, 80) . (mb_strlen($descrizione) > 80 ? '…' : '')
            : mb_substr($titolo, 0, 200);

        $pageUrl = mb_substr((string) ($clientContext['url'] ?? ''), 0, 1000);
        $path    = (string) ($clientContext['path'] ?? '');
        $modulo  = $this->inferModule($path !== '' ? $path : $pageUrl);

        // Cap difensivo delle collezioni provenienti dal client.
        if (isset($clientContext['errors']) && is_array($clientContext['errors'])) {
            $clientContext['errors'] = array_slice($clientContext['errors'], -self::MAX_ERRORS);
        }
        if (isset($clientContext['breadcrumb']) && is_array($clientContext['breadcrumb'])) {
            $clientContext['breadcrumb'] = array_slice($clientContext['breadcrumb'], -self::MAX_BREADCRUMB);
        }

        $errori = is_array($clientContext['errors'] ?? null) ? $clientContext['errors'] : [];

        // Enrichment server-side (fonti attendibili, non falsificabili dal client).
        $server = [
            'user'            => $user ? [
                'id'                => $user['id'],
                'name'              => $user['name'],
                'roles'             => $user['roles'] ?? [],
                'permissions_count' => count($user['permissions'] ?? []),
            ] : null,
            'ip'              => \App\Support\ClientIp::resolve(),
            'server_time'     => date('c'),
            'app_version'     => app_version(),
            'php_version'     => PHP_VERSION,
            'modulo'          => $modulo,
            'enabled_modules' => $this->enabledModules(),
        ];

        $contesto = ['client' => $clientContext, 'server' => $server];
        $contestoJson = (string) json_encode($contesto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Se troppo grande, sfoltisci breadcrumb/errors e riprova.
        if (strlen($contestoJson) > self::MAX_CONTEXT_BYTES) {
            if (isset($contesto['client']['breadcrumb'])) {
                $contesto['client']['breadcrumb'] = array_slice($contesto['client']['breadcrumb'], -10);
            }
            if (isset($contesto['client']['errors'])) {
                $contesto['client']['errors'] = array_slice($contesto['client']['errors'], -10);
            }
            $contestoJson = (string) json_encode($contesto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (strlen($contestoJson) > self::MAX_CONTEXT_BYTES) {
                $contestoJson = substr($contestoJson, 0, self::MAX_CONTEXT_BYTES);
            }
        }

        $erroriJson = !empty($errori)
            ? (string) json_encode($errori, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;

        $dom = trim($dom);
        if ($dom !== '' && strlen($dom) > self::MAX_DOM_BYTES) {
            $dom = substr($dom, 0, self::MAX_DOM_BYTES) . "\n<!-- [troncato dal server] -->";
        }

        $routeName = mb_substr((string) ($clientContext['route_name'] ?? ''), 0, 150);
        $refCode   = $this->generateRefCode();

        $id = $this->repo->create([
            'ref_code'            => $refCode,
            'tipo'                => $tipo,
            'severita'            => $severita,
            'stato'               => 'nuova',
            'titolo'              => $titolo,
            'descrizione'         => $descrizione,
            'passi'               => $passi !== '' ? $passi : null,
            'pagina_url'          => $pageUrl !== '' ? $pageUrl : null,
            'route_name'          => $routeName !== '' ? $routeName : null,
            'modulo'              => $modulo,
            'contesto_json'       => $contestoJson,
            'errori_console_json' => $erroriJson,
            'dom_snapshot'        => $dom !== '' ? $dom : null,
            'user_agent'          => mb_substr((string) ($clientContext['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 500),
            'viewport'            => mb_substr((string) ($clientContext['viewport_str'] ?? ''), 0, 40) ?: null,
            'app_version'         => app_version(),
            'created_by'          => $user['id'] ?? null,
        ]);

        // Audit "snello": nessun blob (dom/contesto) in audit_logs.
        AuditService::log('segnalazione_creata', 'segnalazione', $id, null, [
            'ref_code' => $refCode,
            'tipo'     => $tipo,
            'severita' => $severita,
            'stato'    => 'nuova',
            'modulo'   => $modulo,
        ]);

        // Riga di log per correlazione (ref_code + utente + url).
        app_log('info', sprintf(
            '[Feedback] %s creata (id=%d, user=%s, modulo=%s, route=%s, url=%s)',
            $refCode,
            $id,
            (string) ($user['id'] ?? '-'),
            (string) ($modulo ?? '-'),
            $routeName !== '' ? $routeName : '-',
            $pageUrl
        ));

        $this->notifyAdmins($id, $refCode, $tipo, $titolo, $modulo, $user['name'] ?? 'Utente');

        return ['id' => $id, 'ref_code' => $refCode, 'duplicate' => false];
    }

    /** Dati per la console (lista + filtro moduli). */
    public function list(array $filters, int $page = 1): array
    {
        $result = $this->repo->listPaginated($filters, $page, 20);

        return [
            'items'       => $result['data'],
            'page'        => $result['page'],
            'total_pages' => $result['lastPage'],
            'total'       => $result['total'],
            'sortBy'      => $result['sort'],
            'sortDir'     => $result['dir'],
            'moduli'      => $this->repo->distinctModuli(),
        ];
    }

    public function getDetail(int $id): ?array
    {
        return $this->repo->findDetail($id);
    }

    public function assignableUsers(): array
    {
        return $this->repo->assignableUsers();
    }

    public function countOpen(): int
    {
        return $this->repo->countOpen();
    }

    public function countNew(): int
    {
        return $this->repo->countNew();
    }

    /**
     * Aggiorna stato / severità / assegnatario / note (triage admin).
     * Se lo stato cambia, notifica il segnalatore.
     */
    public function triage(int $id, array $data): bool
    {
        $before = $this->repo->find($id);
        if ($before === null) {
            throw new \RuntimeException('Segnalazione non trovata.');
        }

        $update = [];
        if (isset($data['stato']) && in_array($data['stato'], self::STATI, true)) {
            $update['stato'] = $data['stato'];
        }
        if (isset($data['severita']) && in_array($data['severita'], self::SEVERITA, true)) {
            $update['severita'] = $data['severita'];
        }
        if (array_key_exists('assegnata_a', $data)) {
            $assignee = (int) $data['assegnata_a'];
            $update['assegnata_a'] = $assignee > 0 ? $assignee : null;
        }
        if (array_key_exists('note_admin', $data)) {
            $note = mb_substr((string) $data['note_admin'], 0, self::MAX_DESCRIZIONE);
            $update['note_admin'] = $note !== '' ? $note : null;
        }

        if ($update === []) {
            return false;
        }

        $ok = $this->repo->update($id, $update);
        if (!$ok) {
            return false;
        }

        $this->auditTriage($id, $before, $update);

        $statoCambiato = isset($update['stato']) && $update['stato'] !== $before['stato'];

        // Alla CHIUSURA (stato non più aperto) elimina lo snapshot DOM: data-minimization.
        // Il DOM serve solo a riprodurre il bug finché è aperto.
        if ($statoCambiato
            && !in_array($update['stato'], self::STATI_APERTI, true)
            && !empty($before['dom_snapshot'])
        ) {
            $this->repo->clearDom($id);
            AuditService::log('segnalazione_dom_eliminato', 'segnalazione', $id, null, [
                'motivo' => 'chiusura',
                'stato'  => $update['stato'],
            ]);
        }

        if ($statoCambiato) {
            $this->notifyReporter($id, $before, $update['stato']);
        }

        return true;
    }

    public function delete(int $id): bool
    {
        $row = $this->repo->find($id);
        $ok  = $this->repo->delete($id);

        if ($ok) {
            AuditService::log('segnalazione_eliminata', 'segnalazione', $id, [
                'ref_code' => $row['ref_code'] ?? null,
                'stato'    => $row['stato'] ?? null,
            ], null);
        }

        return $ok;
    }

    // ── Metadati per la UI (label + colori badge) ─────────────────────

    public static function tipiMeta(): array
    {
        return [
            'bug'          => ['label' => t('feedback.tipi.bug'), 'icon' => 'fa-bug', 'color' => 'danger'],
            'funzionalita' => ['label' => t('feedback.tipi.funzionalita'), 'icon' => 'fa-lightbulb', 'color' => 'info'],
            'domanda'      => ['label' => t('feedback.tipi.domanda'), 'icon' => 'fa-circle-question', 'color' => 'secondary'],
        ];
    }

    public static function severitaMeta(): array
    {
        return [
            'bassa'   => ['label' => t('feedback.severita.bassa'), 'color' => 'secondary'],
            'media'   => ['label' => t('feedback.severita.media'), 'color' => 'info'],
            'alta'    => ['label' => t('feedback.severita.alta'), 'color' => 'warning'],
            'critica' => ['label' => t('feedback.severita.critica'), 'color' => 'danger'],
        ];
    }

    public static function statiMeta(): array
    {
        return [
            'nuova'           => ['label' => t('feedback.stati.nuova'), 'color' => 'primary'],
            'in_lavorazione'  => ['label' => t('feedback.stati.in_lavorazione'), 'color' => 'warning'],
            'risolta'         => ['label' => t('feedback.stati.risolta'), 'color' => 'success'],
            'chiusa'          => ['label' => t('feedback.stati.chiusa'), 'color' => 'secondary'],
            'non_risolvibile' => ['label' => t('feedback.stati.non_risolvibile'), 'color' => 'dark'],
        ];
    }

    // ── Helper interni ────────────────────────────────────────────────

    /** Voce di audit "snella" per il triage: solo campi tracciati, mai blob né testo note. */
    private function auditTriage(int $id, array $before, array $update): void
    {
        $old = [];
        $new = [];
        foreach (['stato', 'severita', 'assegnata_a'] as $k) {
            if (array_key_exists($k, $update)) {
                $old[$k] = $before[$k] ?? null;
                $new[$k] = $update[$k];
            }
        }
        if (array_key_exists('note_admin', $update)) {
            $new['note_aggiornate'] = true; // non logghiamo il testo delle note
        }
        if ($new === []) {
            return;
        }
        AuditService::log('segnalazione_aggiornata', 'segnalazione', $id, $old ?: null, $new);
    }

    private function generateRefCode(): string
    {
        do {
            $code = 'FB-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        } while ($this->repo->refCodeExists($code));

        return $code;
    }

    /** Deduce il modulo dal path della pagina (best-effort). */
    private function inferModule(string $haystack): ?string
    {
        $path     = parse_url($haystack, PHP_URL_PATH);
        $path     = is_string($path) ? $path : $haystack;
        $segments = array_values(array_filter(explode('/', $path), static fn ($s) => $s !== ''));

        $skip  = ['favilla', 'public', 'index.php', 'feedback'];
        $first = null;
        foreach ($segments as $seg) {
            if (in_array(strtolower($seg), $skip, true)) {
                continue;
            }
            $first = strtolower($seg);
            break;
        }
        if ($first === null) {
            return null;
        }

        foreach (app(ModuleLoader::class)->getModules() as $m) {
            if (strtolower((string) ($m['name'] ?? '')) === $first) {
                return (string) $m['name'];
            }
        }

        return ucfirst($first);
    }

    private function enabledModules(): array
    {
        $out = [];
        foreach (app(ModuleLoader::class)->getModules() as $m) {
            if (!empty($m['enabled'] ?? true)) {
                $out[] = (string) ($m['name'] ?? '');
            }
        }
        return array_values(array_filter($out));
    }

    private function notifyAdmins(int $id, string $ref, string $tipo, string $titolo, ?string $modulo, string $autore): void
    {
        if (!class_exists(NotificationService::class)) {
            return;
        }

        try {
            NotificationService::dispatchEventToRole(
                'feedback.created',
                'Feedback',
                'admin',
                [
                    'ref_code' => $ref,
                    'tipo'     => self::tipiMeta()[$tipo]['label'] ?? $tipo,
                    'titolo'   => $titolo,
                    'modulo'   => $modulo ?? 'n/d',
                    'autore'   => $autore,
                ],
                route('feedback.admin.show', ['id' => $id]),
                auth()['id'] ?? null
            );
        } catch (\Throwable $e) {
            app_log('warning', '[Feedback] notifyAdmins failed: ' . $e->getMessage());
        }
    }

    private function notifyReporter(int $id, array $before, string $nuovoStato): void
    {
        $reporterId = (int) ($before['created_by'] ?? 0);
        $actorId    = (int) (auth()['id'] ?? 0);

        // Niente notifica se non c'è segnalatore o se l'admin sta lavorando una propria segnalazione.
        if ($reporterId <= 0 || $reporterId === $actorId || !class_exists(NotificationService::class)) {
            return;
        }

        try {
            NotificationService::dispatchEventToUser(
                'feedback.status_changed',
                'Feedback',
                $reporterId,
                [
                    'ref_code' => (string) ($before['ref_code'] ?? ('#' . $id)),
                    'titolo'   => (string) ($before['titolo'] ?? ''),
                    'stato'    => self::statiMeta()[$nuovoStato]['label'] ?? $nuovoStato,
                ],
                route('feedback.admin.show', ['id' => $id]),
                $actorId ?: null
            );
        } catch (\Throwable $e) {
            app_log('warning', '[Feedback] notifyReporter failed: ' . $e->getMessage());
        }
    }
}
