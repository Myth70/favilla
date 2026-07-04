<?php

declare(strict_types=1);

namespace App\Modules\Teams\Services;

use App\Modules\Notifications\Repositories\NotificationsRepository;
use App\Modules\Teams\Repositories\ConversationRepository;
use App\Modules\Teams\Repositories\MessageRepository;
use App\Modules\Teams\Repositories\PresenceRepository;
use App\Services\FileUploadService;

class TeamsService
{
    private ConversationRepository $conversationRepo;
    private MessageRepository $messageRepo;
    private NotificationsRepository $notificationsRepo;
    private PresenceRepository $presenceRepo;
    private \PDO $pdo;

    public function __construct()
    {
        $this->conversationRepo = app(ConversationRepository::class);
        $this->messageRepo = app(MessageRepository::class);
        $this->notificationsRepo = app(NotificationsRepository::class);
        $this->presenceRepo = app(PresenceRepository::class);
        $this->pdo = app(\PDO::class);
    }

    public function getIndexData(int $userId, string $search, bool $showHidden): array
    {
        $this->presenceRepo->updatePresence($userId);

        return [
            'conversations' => $this->conversationRepo->listForUser($userId, $search, $showHidden),
            'activeId' => null,
            'showHidden' => $showHidden,
        ];
    }

    public function getConversationData(int $conversationId, int $userId): ?array
    {
        $conversation = $this->conversationRepo->findForUser($conversationId, $userId);
        if (!$conversation) {
            return null;
        }

        $messages = $this->messageRepo->getLatest($conversationId, 50);
        $this->conversationRepo->markAsRead($conversationId, $userId);
        $members = $this->conversationRepo->getActiveMembers($conversationId);
        $hasOlderMessages = !empty($messages)
            ? $this->messageRepo->hasOlderMessages($conversationId, (int) $messages[0]['id'])
            : false;
        $markedRead = $this->notificationsRepo->markAsReadByLink($userId, route('teams.show', ['id' => $conversationId]));

        $this->presenceRepo->updatePresence($userId, $conversationId);

        // Header info per l'offcanvas "Info gruppo" (creatore + counter messaggi).
        // Solo per i gruppi: per le chat 1:1 l'offcanvas non c'è.
        $groupHeaderInfo = [];
        if (($conversation['type'] ?? '') === 'group') {
            $info = $this->conversationRepo->getGroupHeaderInfo($conversationId, $userId);
            if (is_array($info)) {
                $info['message_count'] = $this->messageRepo->countMessagesInConversation($conversationId);
                $groupHeaderInfo = $info;
            }
        }

        return [
            'activeConversation' => $conversation,
            'messages' => $messages,
            'members' => $members,
            'hasOlderMessages' => $hasOlderMessages,
            'markedRead' => $markedRead,
            'newNotifCount' => $markedRead > 0 ? $this->notificationsRepo->getUnreadCountForUser($userId) : null,
            'othersMaxReadAt' => $this->conversationRepo->getOthersMaxReadAt($conversationId, $userId),
            'pinnedCount' => $this->messageRepo->countPinnedMessages($conversationId),
            'groupHeaderInfo' => $groupHeaderInfo,
        ];
    }

    public function getConversationListData(int $userId, string $search, bool $showHidden, ?int $activeId = null): array
    {
        return [
            'conversations' => $this->conversationRepo->listForUser($userId, $search, $showHidden),
            'activeId' => $activeId,
            'showHidden' => $showHidden,
        ];
    }

    /**
     * Orchestrator del polling unificato `/teams/{id}/state` (ogni 3s).
     * Combina nuovi messaggi + mutazioni + typing users + flag conv-list dirty.
     *
     * @return array{
     *   messages: array, mutated: array, othersMaxReadAt: ?string,
     *   currentUserId: int, now: string, reactionsMap: array,
     *   typingUsers: array, convListDirty: bool
     * }|null
     */
    public function getPollStateData(
        int $conversationId,
        int $userId,
        string $since,
        string $sinceState = '',
        string $convSince = ''
    ): ?array {
        $conversation = $this->conversationRepo->findForUser($conversationId, $userId);
        if (!$conversation || $since === '') {
            return null;
        }

        // Nuovi messaggi (esclude i propri: il mittente li ha già visualizzati lato client)
        $newMessages = array_values(array_filter(
            $this->messageRepo->getNewerThan($conversationId, $since),
            static fn (array $message): bool => (int) $message['user_id'] !== $userId
        ));

        // Messaggi mutati (reaction, edit, delete, read receipt) dopo $sinceState.
        // Esclude i messaggi appena emessi tra i "new" per evitare doppio rendering.
        $mutatedMessages = [];
        if ($sinceState !== '') {
            $newIds = array_map(static fn (array $m): int => (int) $m['id'], $newMessages);
            $mutatedMessages = array_values(array_filter(
                $this->messageRepo->getStateChangedSince($conversationId, $sinceState),
                static function (array $m) use ($newIds): bool {
                    return !in_array((int) $m['id'], $newIds, true);
                }
            ));
        }

        // Aggiorna last_read_at SOLO se ci sono effettivamente nuovi messaggi
        // (evita di triggerare touch state inutili a ogni poll vuoto).
        if (!empty($newMessages)) {
            $this->conversationRepo->markAsRead($conversationId, $userId);
        }

        // Carica le reazioni per i messaggi che il controller renderizzerà
        // (nuovi + mutati). Senza questo, il rendering OOB perde la barra
        // reactions perché message_bubble.php cade su `$reactions ?? []`.
        $allIds = array_merge(
            array_map(static fn (array $m): int => (int) $m['id'], $newMessages),
            array_map(static fn (array $m): int => (int) $m['id'], $mutatedMessages)
        );
        $reactionsMap = !empty($allIds)
            ? $this->messageRepo->getReactionsForMessages($allIds)
            : [];

        return [
            'messages'        => $newMessages,
            'mutated'         => $mutatedMessages,
            'othersMaxReadAt' => $this->conversationRepo->getOthersMaxReadAt($conversationId, $userId),
            'currentUserId'   => $userId,
            'now'             => date('Y-m-d H:i:s'),
            'reactionsMap'    => $reactionsMap,
            'typingUsers'     => $this->presenceRepo->getTypingUsers($conversationId, $userId),
            'convListDirty'   => $this->conversationRepo->hasUpdatesForUserSince($userId, $convSince),
        ];
    }

    public function countReadBy(int $conversationId, int $userId, string $messageCreatedAt): int
    {
        return $this->conversationRepo->countReadBy($conversationId, $userId, $messageCreatedAt);
    }

    /**
     * Batch wrapper di `ConversationRepository::countReadByForMessages` per
     * evitare N+1 in `pollState` quando si rendono N messaggi propri.
     *
     * @param string[] $messageCreatedAts
     * @return array<string, int> mappa created_at → count
     */
    public function countReadByForMessages(int $conversationId, int $userId, array $messageCreatedAts): array
    {
        return $this->conversationRepo->countReadByForMessages($conversationId, $userId, $messageCreatedAts);
    }

    public function createGroup(int $userId, string $userName, string $name, ?string $description, array $memberIds): int
    {
        $conversationId = $this->conversationRepo->createWithMembers([
            'type' => 'group',
            'name' => $name,
            'description' => $description,
            'created_by' => $userId,
        ], $memberIds, $userId);

        $this->messageRepo->createSystemMessage($conversationId, t('teams.exception.group_created_message', ['actor' => $userName]));

        return $conversationId;
    }

    public function createOrFindDirect(int $userId, int $otherId): array
    {
        if ($otherId <= 0 || $otherId === $userId) {
            return ['error' => t('teams.exception.invalid_user')];
        }

        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE id = ? AND is_active = 1 AND deleted_at IS NULL');
        $stmt->execute([$otherId]);
        if (!$stmt->fetchColumn()) {
            return ['error' => t('teams.exception.user_not_found_or_inactive')];
        }

        $existingId = $this->conversationRepo->findDirectBetween($userId, $otherId);
        if ($existingId) {
            return ['conversationId' => $existingId, 'created' => false];
        }

        $conversationId = $this->conversationRepo->createWithMembers([
            'type' => 'direct',
            'name' => null,
            'created_by' => $userId,
        ], [$otherId], $userId);

        return ['conversationId' => $conversationId, 'created' => true];
    }

    public function updateGroup(int $conversationId, int $userId, string $userName, string $name, ?string $description): array
    {
        $conversation = $this->conversationRepo->findForUser($conversationId, $userId);
        if (!$conversation || $conversation['type'] !== 'group') {
            return ['error' => t('teams.exception.conversation_not_found')];
        }

        if ($conversation['my_role'] !== 'admin' && !has_permission('teams.admin')) {
            return ['error' => t('teams.exception.not_authorized_edit_group')];
        }

        $oldName = $conversation['name'];
        $this->conversationRepo->update($conversationId, [
            'name' => $name,
            'description' => $description,
        ]);

        if ($oldName !== $name) {
            $this->messageRepo->createSystemMessage($conversationId, t('teams.exception.group_renamed_message', ['actor' => $userName, 'name' => $name]));
        }

        return ['conversation' => $conversation];
    }

    public function archiveConversation(int $conversationId, int $userId, string $userName): array
    {
        $conversation = $this->conversationRepo->findForUser($conversationId, $userId);
        if (!$conversation || $conversation['type'] !== 'group') {
            return ['error' => t('teams.exception.conversation_not_found')];
        }

        if ($conversation['my_role'] !== 'admin' && !has_permission('teams.admin')) {
            return ['error' => t('teams.exception.not_authorized_archive')];
        }

        $this->conversationRepo->archive($conversationId);
        $this->messageRepo->createSystemMessage($conversationId, t('teams.exception.archived_message', ['actor' => $userName]));
        app(TeamsNotificationService::class)->notifyArchived($conversationId, $userId, $userName, $conversation['name'] ?? '');

        return ['conversation' => $conversation];
    }

    public function leaveGroup(int $conversationId, int $userId, string $userName): array
    {
        $conversation = $this->conversationRepo->findForUser($conversationId, $userId);
        if (!$conversation || $conversation['type'] !== 'group') {
            return ['error' => t('teams.exception.conversation_not_found')];
        }

        $this->conversationRepo->removeMember($conversationId, $userId);
        $this->messageRepo->createSystemMessage($conversationId, t('teams.exception.left_group_message', ['actor' => $userName]));

        $promotedId = null;
        if ($conversation['my_role'] === 'admin' && $this->conversationRepo->countAdmins($conversationId) === 0) {
            $promotedId = $this->conversationRepo->promoteOldestMember($conversationId);
            if ($promotedId) {
                $stmt = $this->pdo->prepare('SELECT name FROM users WHERE id = ?');
                $stmt->execute([$promotedId]);
                $promotedName = $stmt->fetchColumn() ?: t('teams.exception.default_member_name');

                $this->messageRepo->createSystemMessage($conversationId, t('teams.exception.member_promoted_message', ['member' => $promotedName]));
                app(TeamsNotificationService::class)->notifyPromotion($conversationId, $promotedId, $conversation['name'] ?? '');
            }
        }

        return [
            'conversation' => $conversation,
            'promotedId' => $promotedId,
        ];
    }

    public function toggleMute(int $conversationId, int $userId): ?bool
    {
        $conversation = $this->conversationRepo->findForUser($conversationId, $userId);
        if (!$conversation) {
            return null;
        }

        return $this->conversationRepo->toggleMute($conversationId, $userId);
    }

    public function searchMessages(int $userId, string $query): array
    {
        return $query === '' ? [] : $this->messageRepo->searchForUser($userId, $query);
    }

    public function searchConversationMessages(int $conversationId, int $userId, string $query): array
    {
        $conversation = $this->conversationRepo->findForUser($conversationId, $userId);
        if (!$conversation || trim($query) === '') {
            return [];
        }

        return $this->messageRepo->searchInConversationForUser($conversationId, $userId, trim($query));
    }

    public function getSearchPageData(int $userId, string $query): array
    {
        return [
            'results' => $this->searchMessages($userId, $query),
            'conversations' => $this->conversationRepo->listForUser($userId),
        ];
    }

    public function getUnreadCount(int $userId): int
    {
        return $this->conversationRepo->getGlobalUnreadCount($userId);
    }

    public function getUnreadConversations(int $userId, int $limit = 5): array
    {
        return $this->conversationRepo->listUnreadForUser($userId, $limit);
    }

    public function heartbeat(int $userId, ?int $activeConversationId): void
    {
        $this->presenceRepo->updatePresence($userId, $activeConversationId);

        // cleanupStaleTyping fa un UPDATE/DELETE su tutta la tabella typing;
        // chiamarlo ad ogni heartbeat (10s × N utenti attivi) è ridondante.
        // Lo eseguiamo al massimo una volta ogni 30s per sessione.
        $last = (int) ($_SESSION['_teams_last_typing_cleanup'] ?? 0);
        if (time() - $last >= 30) {
            $this->presenceRepo->cleanupStaleTyping();
            $_SESSION['_teams_last_typing_cleanup'] = time();
        }
    }

    public function setTyping(int $conversationId, int $userId): void
    {
        $conversation = $this->conversationRepo->findForUser($conversationId, $userId);
        if ($conversation) {
            $this->presenceRepo->setTyping($userId, $conversationId);
        }
    }

    public function getTypingUsers(int $conversationId, int $userId): array
    {
        return $this->presenceRepo->getTypingUsers($conversationId, $userId);
    }

    public function searchUsers(string $query, int $userId, ?int $excludeConversationId = null): array
    {
        if (mb_strlen($query) < 2) {
            return [];
        }

        $like = '%' . $query . '%';

        if ($excludeConversationId) {
            return $this->conversationRepo->searchUsersNotInConversation($like, $userId, $excludeConversationId);
        }

        return $this->conversationRepo->searchUsers($like, $userId);
    }

    /**
     * Toggle pin/unpin di un messaggio. Solo per admin di conversazione o
     * utenti con permission `teams.admin`.
     *
     * @return array{status:string, pinned?:bool, message?:array}|null
     */
    public function togglePin(int $conversationId, int $messageId, int $userId): ?array
    {
        $conversation = $this->conversationRepo->findForUser($conversationId, $userId);
        if (!$conversation) {
            return null;
        }

        $isAdmin = ($conversation['my_role'] ?? '') === 'admin' || has_permission('teams.admin');
        if (!$isAdmin) {
            return ['status' => 'forbidden'];
        }

        $message = $this->messageRepo->findWithUser($messageId);
        if (!$message || (int) $message['conversation_id'] !== $conversationId || !empty($message['deleted_at'])) {
            return ['status' => 'not_found'];
        }

        if (!empty($message['pinned_at'])) {
            $this->messageRepo->unpinMessage($messageId);
            $pinned = false;
        } else {
            $this->messageRepo->pinMessage($messageId, $userId);
            $pinned = true;
        }

        return [
            'status'  => 'ok',
            'pinned'  => $pinned,
            'message' => $this->messageRepo->findWithUser($messageId),
        ];
    }

    /**
     * Restituisce i messaggi pinned di una conversazione (con conteggio).
     *
     * @return array{count:int, messages:array<int, array<string, mixed>>}|null
     */
    public function getPinnedData(int $conversationId, int $userId): ?array
    {
        $conversation = $this->conversationRepo->findForUser($conversationId, $userId);
        if (!$conversation) {
            return null;
        }
        return [
            'count'    => $this->messageRepo->countPinnedMessages($conversationId),
            'messages' => $this->messageRepo->getPinnedMessages($conversationId, 20),
        ];
    }

    /**
     * Membri della conversazione che matchano $query, per autocomplete @mention.
     * Restituisce null se l'utente non è membro della conversazione.
     *
     * @return array<int, array{id:int, name:string, username:?string, avatar_url:?string}>|null
     */
    public function getMentionCandidates(int $conversationId, int $userId, string $query): ?array
    {
        $conversation = $this->conversationRepo->findForUser($conversationId, $userId);
        if (!$conversation) {
            return null;
        }
        $members = $this->conversationRepo->getMembersForAutocomplete($conversationId, $query, $userId, 8);
        $out = [];
        foreach ($members as $m) {
            $out[] = [
                'id'         => (int) $m['id'],
                'name'       => (string) $m['name'],
                'username'   => $m['username'] ?? null,
                'avatar_url' => \App\Modules\Auth\Helpers\AvatarHelper::url($m['avatar_path'] ?? null),
            ];
        }
        return $out;
    }

    public function uploadAvatar(int $conversationId, int $userId, array $file): array
    {
        $conversation = $this->conversationRepo->findForUser($conversationId, $userId);
        if (!$conversation || $conversation['type'] !== 'group') {
            return ['error' => t('teams.exception.conversation_not_found')];
        }

        if ($conversation['my_role'] !== 'admin' && !has_permission('teams.admin')) {
            return ['error' => t('teams.exception.not_authorized')];
        }

        try {
            $filename = FileUploadService::uploadImage($file, 'avatars', 'team_' . $conversationId . '_');
        } catch (\RuntimeException $exception) {
            return ['error' => $exception->getMessage()];
        }

        if (!empty($conversation['avatar_path'])) {
            FileUploadService::delete($conversation['avatar_path'], 'avatars');
        }

        $this->conversationRepo->update($conversationId, ['avatar_path' => $filename]);

        return ['filename' => $filename];
    }

    /**
     * Verifica se l'utente puo gestire l'avatar di una conversazione team.
     */
    public function canManageConversationAvatar(int $conversationId, int $userId): bool
    {
        if (has_permission('teams.admin')) {
            return true;
        }

        $conversation = $this->conversationRepo->findForUser($conversationId, $userId);
        return (bool) ($conversation && $conversation['type'] === 'group' && $conversation['my_role'] === 'admin');
    }

    /**
     * Aggiorna avatar conversazione, eliminando il precedente se presente.
     */
    public function setConversationAvatar(int $conversationId, string $filename): void
    {
        $conversation = $this->conversationRepo->find($conversationId);
        if (!$conversation) {
            return;
        }

        if (!empty($conversation['avatar_path'])) {
            FileUploadService::delete($conversation['avatar_path'], 'avatars');
        }

        $this->conversationRepo->update($conversationId, ['avatar_path' => $filename]);
    }

    public function hideConversation(int $conversationId, int $userId): bool
    {
        $conversation = $this->conversationRepo->findForUser($conversationId, $userId);
        if (!$conversation) {
            return false;
        }

        $this->conversationRepo->hideConversation($conversationId, $userId);
        return true;
    }

    public function unhideConversation(int $conversationId, int $userId): bool
    {
        $conversation = $this->conversationRepo->findForUser($conversationId, $userId);
        if (!$conversation) {
            return false;
        }

        $this->conversationRepo->unhideConversation($conversationId, $userId);
        return true;
    }
}
