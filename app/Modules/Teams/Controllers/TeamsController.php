<?php

declare(strict_types=1);

namespace App\Modules\Teams\Controllers;

use App\Core\Controller;
use App\Modules\Teams\Services\TeamsService;
use App\Traits\ControllerHelpers;

class TeamsController extends Controller
{
    use ControllerHelpers;
    private TeamsService $service;

    public function __construct()
    {
        $this->service = app(TeamsService::class);
    }

    /**
     * Pagina principale Teams — lista conversazioni + pannello chat.
     */
    public function index(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $filters = $this->cleanGet(['conv_search']);
        $search = $filters['conv_search'];
        $showHidden = !empty($_GET['show_hidden']);
        $data = $this->service->getIndexData($userId, $search, $showHidden);

        if ($this->isHtmxRequest()) {
            $this->renderPartial('Teams/Views/partials/conversation_list', $data);
            return;
        }

        $this->render('Teams/Views/index', [
            'pageTitle'      => t('teams.title'),
            'breadcrumbs'    => [],
            'conversations'  => $data['conversations'],
            'activeConversation' => null,
            'messages'       => [],
            'members'        => [],
            'hasOlderMessages' => false,
            'activeId'       => null,
            'showHidden'     => $showHidden,
        ]);
    }

    /**
     * Apri una conversazione.
     */
    public function show(string $id): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $convId = (int) $id;

        $data = $this->service->getConversationData($convId, $userId);
        if (!$data) {
            flash_error(t('teams.flash.conversation_not_found'));
            $this->redirect(route('teams.index'));
            return;
        }

        if ($this->isHtmxRequest()) {
            // Aprire la conv chiama markAsRead in getConversationData: il badge
            // unread dell'utente per quella conv va a 0. Trigger refresh della
            // lista conv (last_read_at non rende dirty la conv lato server,
            // quindi serve l'emit esplicito qui).
            $this->hxTrigger(['teamsConvRefresh' => true]);
            if ($data['markedRead'] > 0) {
                $this->hxTrigger(['notifCountUpdated' => ['value' => $data['newNotifCount']]]);
            }
            $this->renderPartial('Teams/Views/partials/chat_panel', [
                'activeConversation' => $data['activeConversation'],
                'messages'           => $data['messages'],
                'members'            => $data['members'],
                'hasOlderMessages'   => $data['hasOlderMessages'],
                'othersMaxReadAt'    => $data['othersMaxReadAt'],
                'pinnedCount'        => $data['pinnedCount'] ?? 0,
                'groupHeaderInfo'    => $data['groupHeaderInfo'] ?? [],
            ]);
            return;
        }

        $listData = $this->service->getConversationListData($userId, '', false, $convId);

        $this->render('Teams/Views/index', [
            'pageTitle'          => t('teams.title'),
            'breadcrumbs'        => [],
            'conversations'      => $listData['conversations'],
            'activeConversation' => $data['activeConversation'],
            'messages'           => $data['messages'],
            'members'            => $data['members'],
            'hasOlderMessages'   => $data['hasOlderMessages'],
            'othersMaxReadAt'    => $data['othersMaxReadAt'],
            'pinnedCount'        => $data['pinnedCount'] ?? 0,
            'groupHeaderInfo'    => $data['groupHeaderInfo'] ?? [],
            'activeId'           => $convId,
            'showHidden'         => false,
        ]);
    }

    /**
     * HTMX: refresh lista conversazioni (polling ogni 10s).
     */
    public function conversationList(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $filters = $this->cleanGet(['conv_search']);
        $search = $filters['conv_search'];

        // Prendi l'ID attivo dal query param (il JS lo passa)
        $activeId = !empty($_GET['active']) ? (int) $_GET['active'] : null;

        $showHidden = !empty($_GET['show_hidden']);
        $this->renderPartial('Teams/Views/partials/conversation_list', $this->service->getConversationListData($userId, $search, $showHidden, $activeId));
    }

    /**
     * HTMX: polling unificato `/teams/{id}/state` (ogni 3s).
     * Sostituisce i tre polling separati (messaggi, typing, lista conv).
     *
     * Query params:
     *  - `since`            timestamp ultimo messaggio visibile (filtra nuovi messaggi)
     *  - `since_state`      timestamp ultimo poll state (filtra mutazioni: reaction, edit, delete, read receipt)
     *  - `conv_state_since` timestamp ultimo poll conv-state (dirty check lista conv)
     *
     * Output (merged):
     *  - Nuovi messaggi             → rendering inline (HTMX swap=beforebegin nel target)
     *  - Messaggi mutati            → `hx-swap-oob="outerHTML"` sul wrapper `#tm-msg-{id}`
     *  - Typing indicator           → `hx-swap-oob="innerHTML"` su `#tm-typing-indicator`
     *  - Sentinel msg-state         → OOB su `#tm-poll-state-sentinel`
     *  - Sentinel conv-state        → OOB su `#tm-conv-state-sentinel`
     *  - Header `HX-Trigger: teamsConvRefresh` se la lista conv è dirty
     *
     * Se l'utente non è (più) membro: risposta 200 con solo i sentinel, così il
     * polling resta benigno; la lista conv si auto-pulirà al prossimo dirty trigger.
     */
    public function pollState(string $id): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $convId = (int) $id;
        $params     = $this->cleanGet(['since', 'since_state', 'conv_state_since']);
        $since      = $params['since'];
        $sinceState = $params['since_state'] ?? '';
        $convSince  = $params['conv_state_since'] ?? '';

        $data = $this->service->getPollStateData($convId, $userId, $since, $sinceState, $convSince);
        $now  = htmlspecialchars(
            (string) ($data['now'] ?? date('Y-m-d H:i:s')),
            ENT_QUOTES,
            'UTF-8'
        );

        if ($data === null) {
            echo '<span id="tm-poll-state-sentinel" data-state-ts="' . $now . '" hx-swap-oob="outerHTML"></span>';
            echo '<span id="tm-conv-state-sentinel" data-state-ts="' . $now . '" hx-swap-oob="outerHTML"></span>';
            return;
        }

        // Headers PRIMA dell'output: trigger refresh lista conv quando la lista
        // ha bisogno di aggiornarsi. Casi:
        //  - dirty server-side (altri eventi su conv dell'utente, vedi
        //    ConversationRepository::hasUpdatesForUserSince)
        //  - l'utente corrente ha appena letto nuovi messaggi (markAsRead): il
        //    SUO badge unread va azzerato, ma `last_read_at` non tocca
        //    `teams_conversations.updated_at` quindi il dirty-check generale non
        //    lo intercetta.
        if (!empty($data['convListDirty']) || !empty($data['messages'])) {
            $this->hxTrigger(['teamsConvRefresh' => true]);
        }

        // Batch: precalcola "letto da N membri" per i miei messaggi (new + mutated)
        // in un'unica query, evitando N+1 sull'endpoint chiamato ogni 3s.
        $myCreatedAts = [];
        foreach ($data['messages'] as $msg) {
            if ((int) $msg['user_id'] === $data['currentUserId']) {
                $myCreatedAts[] = (string) $msg['created_at'];
            }
        }
        foreach ($data['mutated'] as $msg) {
            if ((int) $msg['user_id'] === $data['currentUserId']) {
                $myCreatedAts[] = (string) $msg['created_at'];
            }
        }
        $readByMap = !empty($myCreatedAts)
            ? $this->service->countReadByForMessages($convId, $data['currentUserId'], $myCreatedAts)
            : [];
        $countReadByFor = function (array $msg) use ($data, $readByMap): int {
            return (int) $msg['user_id'] === $data['currentUserId']
                ? ($readByMap[(string) $msg['created_at']] ?? 0)
                : 0;
        };

        $reactionsMap = $data['reactionsMap'] ?? [];

        // 1. Nuovi messaggi (rendering inline, HTMX li mette beforebegin nel target)
        foreach ($data['messages'] as $msg) {
            $this->renderPartial('Teams/Views/partials/message_bubble', [
                'msg'             => $msg,
                'currentUserId'   => $data['currentUserId'],
                'showAvatar'      => true,
                'othersMaxReadAt' => $data['othersMaxReadAt'],
                'readByCount'     => $countReadByFor($msg),
                'reactions'       => $reactionsMap[(int) $msg['id']] ?? [],
            ]);
        }

        // 2. Messaggi mutati (OOB swap sul wrapper #tm-msg-{id})
        foreach ($data['mutated'] as $msg) {
            $this->renderPartial('Teams/Views/partials/message_bubble', [
                'msg'             => $msg,
                'currentUserId'   => $data['currentUserId'],
                'showAvatar'      => true,
                'othersMaxReadAt' => $data['othersMaxReadAt'],
                'readByCount'     => $countReadByFor($msg),
                'reactions'       => $reactionsMap[(int) $msg['id']] ?? [],
                'oob'             => true,
            ]);
        }

        // 3. Typing indicator (OOB innerHTML su #tm-typing-indicator).
        // Va emesso sempre, anche con typing vuoto, per pulire il typing residuo.
        $this->renderPartial('Teams/Views/partials/typing_indicator', [
            'typingUsers' => $data['typingUsers'],
            'oob'         => true,
        ]);

        // 4. Sentinel messaggi (OOB su id stabile) — fa avanzare since/since_state
        echo '<span id="tm-poll-state-sentinel" data-state-ts="' . $now . '" hx-swap-oob="outerHTML"></span>';

        // 5. Sentinel conv-state (OOB) — fa avanzare conv_state_since
        echo '<span id="tm-conv-state-sentinel" data-state-ts="' . $now . '" hx-swap-oob="outerHTML"></span>';
    }

    /**
     * Crea un nuovo gruppo.
     */
    public function store(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $userName = (string) ($_SESSION['user_name'] ?? t('teams.exception.default_user_name'));

        $clean       = $this->cleanPost(['name', 'description']);
        $name        = $clean['name'];
        $description = $clean['description'];
        $memberIds   = $_POST['members'] ?? [];

        if ($name === '') {
            flash_error(t('teams.flash.group_name_required'));
            $this->redirect(route('teams.index'));
            return;
        }

        if (empty($memberIds)) {
            flash_error(t('teams.flash.select_at_least_one_member'));
            $this->redirect(route('teams.index'));
            return;
        }

        $memberIds = array_map('intval', (array) $memberIds);

        $convId = $this->service->createGroup($userId, $userName, $name, $description ?: null, $memberIds);

        flash_success(t('teams.flash.group_created'));

        if ($this->isHtmxRequest()) {
            header('HX-Redirect: ' . route('teams.show', ['id' => $convId]));
            return;
        }
        $this->redirect(route('teams.show', ['id' => $convId]));
    }

    /**
     * Crea o trova una conversazione 1:1.
     */
    public function storeDirect(): void
    {
        $userId  = (int) ($_SESSION['user_id'] ?? 0);
        $otherId = (int) ($_POST['user_id'] ?? 0);

        if ($otherId <= 0 || $otherId === $userId) {
            flash_error(t('teams.exception.invalid_user'));
            $this->redirect(route('teams.index'));
            return;
        }

        $result = $this->service->createOrFindDirect($userId, $otherId);
        if (isset($result['error'])) {
            flash_error($result['error']);
            $this->redirect(route('teams.index'));
            return;
        }

        if (!$result['created']) {
            if ($this->isHtmxRequest()) {
                header('HX-Redirect: ' . route('teams.show', ['id' => $result['conversationId']]));
                return;
            }
            $this->redirect(route('teams.show', ['id' => $result['conversationId']]));
            return;
        }

        $convId = $result['conversationId'];

        if ($this->isHtmxRequest()) {
            header('HX-Redirect: ' . route('teams.show', ['id' => $convId]));
            return;
        }
        $this->redirect(route('teams.show', ['id' => $convId]));
    }

    /**
     * Aggiorna una conversazione (rinomina gruppo).
     */
    public function update(string $id): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $convId = (int) $id;
        $userName = (string) ($_SESSION['user_name'] ?? t('teams.exception.default_user_name'));

        $clean       = $this->cleanPost(['name', 'description']);
        $name        = $clean['name'];
        $description = $clean['description'];

        if ($name === '') {
            flash_error(t('teams.flash.group_name_required'));
            $this->redirect(route('teams.show', ['id' => $convId]));
            return;
        }

        $result = $this->service->updateGroup($convId, $userId, $userName, $name, $description ?: null);
        if (isset($result['error'])) {
            flash_error($result['error']);
            $this->redirect(route('teams.show', ['id' => $convId]));
            return;
        }

        flash_success(t('teams.flash.group_updated'));

        if ($this->isHtmxRequest()) {
            header('HX-Redirect: ' . route('teams.show', ['id' => $convId]));
            return;
        }
        $this->redirect(route('teams.show', ['id' => $convId]));
    }

    /**
     * Archivia una conversazione.
     */
    public function archive(string $id): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $convId = (int) $id;

        $userName = (string) ($_SESSION['user_name'] ?? t('teams.exception.default_user_name'));
        $result = $this->service->archiveConversation($convId, $userId, $userName);
        if (isset($result['error'])) {
            flash_error($result['error']);
            $this->redirect(route('teams.show', ['id' => $convId]));
            return;
        }

        flash_success(t('teams.flash.conversation_archived'));

        if ($this->isHtmxRequest()) {
            header('HX-Redirect: ' . route('teams.index'));
            return;
        }
        $this->redirect(route('teams.index'));
    }

    /**
     * Lascia un gruppo.
     */
    public function leave(string $id): void
    {
        $userId   = (int) ($_SESSION['user_id'] ?? 0);
        $userName = (string) ($_SESSION['user_name'] ?? t('teams.exception.default_user_name'));
        $convId   = (int) $id;

        $result = $this->service->leaveGroup($convId, $userId, $userName);
        if (isset($result['error'])) {
            flash_error($result['error']);
            $this->redirect(route('teams.index'));
            return;
        }

        flash_success(t('teams.flash.left_group'));

        if ($this->isHtmxRequest()) {
            header('HX-Redirect: ' . route('teams.index'));
            return;
        }
        $this->redirect(route('teams.index'));
    }

    /**
     * Toggle mute per una conversazione.
     */
    public function toggleMute(string $id): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $convId = (int) $id;

        $isMuted = $this->service->toggleMute($convId, $userId);
        if ($isMuted === null) {
            http_response_code(404);
            return;
        }

        if ($this->isHtmxRequest()) {
            // Variant "quick" → bottone stile group-panel (innerHTML swap nel
            // wrapper #tm-mute-btn). Default → pillola classica chat header.
            $variant = (string) ($_POST['variant'] ?? '');
            if ($variant === 'quick') {
                $this->renderPartial('Teams/Views/partials/group_panel/mute_button_quick', [
                    'convId'  => $convId,
                    'isMuted' => $isMuted,
                ]);
                return;
            }
            $icon  = $isMuted ? 'fa-bell-slash' : 'fa-bell';
            $title = $isMuted ? t('teams.show.notifications_muted') : t('teams.show.notifications_active');
            $this->renderPartial('Teams/Views/partials/mute_button', [
                'convId' => $convId,
                'icon'   => $icon,
                'title'  => $title,
            ]);
            return;
        }

        $this->redirect(route('teams.show', ['id' => $convId]));
    }

    /**
     * Cerca messaggi globalmente.
     */
    public function search(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $q = $this->cleanGet(['q'])['q'];
        $results = $this->service->searchMessages($userId, $q);

        if ($this->isHtmxRequest()) {
            $this->renderPartial('Teams/Views/partials/search_results', [
                'results' => $results,
                'q'       => $q,
            ]);
            return;
        }

        $searchData = $this->service->getSearchPageData($userId, $q);

        $this->render('Teams/Views/index', [
            'pageTitle'          => t('teams.search.page_title'),
            'breadcrumbs'        => [],
            'conversations'      => $searchData['conversations'],
            'activeConversation' => null,
            'messages'           => [],
            'members'            => [],
            'hasOlderMessages'   => false,
            'activeId'           => null,
            'searchMode'         => true,
            'searchResults'      => $results,
            'searchQuery'        => $q,
        ]);
    }

    /**
     * Cerca messaggi in una singola conversazione.
     */
    public function searchMessagesInConversation(string $id): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $convId = (int) $id;
        $q = trim((string) ($this->cleanGet(['q'])['q'] ?? ''));

        $results = $this->service->searchConversationMessages($convId, $userId, $q);

        $this->renderPartial('Teams/Views/partials/message_search_results', [
            'results' => $results,
            'q' => $q,
            'conversationId' => $convId,
        ]);
    }

    /**
     * Badge non letti globale (per sidebar, polling 60s).
     */
    public function unreadCount(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $count = $this->service->getUnreadCount($userId);

        $this->renderPartial('Teams/Views/partials/unread_badge', [
            'count' => $count,
        ]);
    }

    /**
     * Heartbeat di presenza.
     */
    public function heartbeat(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $activeConvId = !empty($_POST['active_conversation_id']) ? (int) $_POST['active_conversation_id'] : null;

        $this->service->heartbeat($userId, $activeConvId);

        http_response_code(204);
    }

    /**
     * Segnala che l'utente sta digitando.
     */
    public function typing(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $convId = (int) ($_POST['conversation_id'] ?? 0);

        if ($convId > 0) {
            $this->service->setTyping($convId, $userId);
        }

        http_response_code(204);
    }

    /**
     * Cerca utenti (per nuova conversazione o aggiunta membro).
     */
    public function userSearch(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $filters = $this->cleanGet(['q']);
        $q = $filters['q'];
        $excludeConvId = !empty($_GET['exclude_conversation']) ? (int) $_GET['exclude_conversation'] : null;
        $users = $this->service->searchUsers($q, $userId, $excludeConvId);

        $this->renderPartial('Teams/Views/partials/user_search', [
            'users' => $users,
            'q'     => $q,
        ]);
    }

    /**
     * Autocomplete membri conversazione per la mention "@" nell'editor.
     * Output JSON: lista di { id, name, username, avatar_url }.
     */
    public function mentionAutocomplete(string $id): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $convId = (int) $id;
        $filters = $this->cleanGet(['q']);
        $q = (string) ($filters['q'] ?? '');

        $members = $this->service->getMentionCandidates($convId, $userId, $q);
        if ($members === null) {
            http_response_code(404);
            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($members, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Upload avatar per un gruppo.
     */
    public function uploadAvatar(string $id): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $convId = (int) $id;

        $result = $this->service->uploadAvatar($convId, $userId, $_FILES['avatar'] ?? []);
        if (isset($result['error'])) {
            flash_error($result['error']);
            $this->redirect(route('teams.show', ['id' => $convId]));
            return;
        }

        flash_success(t('teams.flash.avatar_updated'));
        $this->redirect(route('teams.show', ['id' => $convId]));
    }

    /**
     * Nascondi una conversazione per l'utente corrente.
     */
    public function hide(string $id): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $convId = (int) $id;

        if (!$this->service->hideConversation($convId, $userId)) {
            flash_error(t('teams.flash.conversation_not_found'));
            $this->redirect(route('teams.index'));
            return;
        }

        if ($this->isHtmxRequest()) {
            header('HX-Redirect: ' . route('teams.index'));
            return;
        }
        $this->redirect(route('teams.index'));
    }

    /**
     * Mostra una conversazione precedentemente nascosta.
     */
    public function unhide(string $id): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $convId = (int) $id;

        if (!$this->service->unhideConversation($convId, $userId)) {
            http_response_code(404);
            return;
        }

        if ($this->isHtmxRequest()) {
            header('HX-Trigger: teamsConvRefresh');
            http_response_code(204);
            return;
        }
        $this->redirect(route('teams.show', ['id' => $convId]));
    }
}
