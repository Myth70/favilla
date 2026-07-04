<?php

declare(strict_types=1);

namespace App\Modules\Teams\Services;

use App\Modules\Teams\Repositories\ConversationRepository;
use App\Modules\Teams\Repositories\MessageRepository;
use App\Services\FileUploadService;

class TeamsMessageService
{
    private ConversationRepository $conversationRepo;
    private MessageRepository $messageRepo;

    public function __construct()
    {
        $this->conversationRepo = app(ConversationRepository::class);
        $this->messageRepo = app(MessageRepository::class);
    }

    /**
     * Estrae le mention "@candidato" dal body. Restituisce la lista grezza,
     * il match effettivo coi membri viene fatto da
     * `ConversationRepository::findMembersByMentionCandidates()`.
     *
     * @return string[]
     */
    public static function extractMentionCandidates(string $body): array
    {
        if (!preg_match_all('/(?<![\w@])@([\p{L}0-9_.\-]+)/u', $body, $matches)) {
            return [];
        }
        return array_values(array_unique($matches[1]));
    }

    /**
     * Accetta uno dei formati seguenti e restituisce una lista normalizzata
     * di entry singole $_FILES (`{name, tmp_name, type, size, error}`),
     * filtrate per `error === UPLOAD_ERR_OK`:
     *
     *   - null
     *   - singolo $_FILES['attachment']: ['name'=>string, 'tmp_name'=>string, ...]
     *   - $_FILES['attachments'] in formato colonna multi-file:
     *       ['name'=>[a,b], 'tmp_name'=>[...], ...]
     *   - già una lista di entry: [ {name,tmp_name,...}, {name,tmp_name,...} ]
     */
    public static function normalizeAttachments(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }

        // Caso "colonna multi-file": chiavi sono $_FILES standard, valori sono array
        if (isset($input['name']) && is_array($input['name'])) {
            $out = [];
            $count = count($input['name']);
            for ($i = 0; $i < $count; $i++) {
                $entry = [
                    'name'     => $input['name'][$i]     ?? '',
                    'type'     => $input['type'][$i]     ?? '',
                    'tmp_name' => $input['tmp_name'][$i] ?? '',
                    'error'    => $input['error'][$i]    ?? UPLOAD_ERR_NO_FILE,
                    'size'     => $input['size'][$i]     ?? 0,
                ];
                if ((int) $entry['error'] === UPLOAD_ERR_OK && !empty($entry['tmp_name'])) {
                    $out[] = $entry;
                }
            }
            return $out;
        }

        // Caso "lista di entry"
        if (isset($input[0]) && is_array($input[0])) {
            $out = [];
            foreach ($input as $entry) {
                if (
                    is_array($entry)
                    && (int) ($entry['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
                    && !empty($entry['tmp_name'])
                ) {
                    $out[] = $entry;
                }
            }
            return $out;
        }

        // Caso singolo $_FILES entry (backward-compat con 'attachment')
        if (isset($input['tmp_name'])) {
            if ((int) ($input['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && !empty($input['tmp_name'])) {
                return [$input];
            }
            return [];
        }

        return [];
    }

    /**
     * Allegato per lo streaming via route: null se non esiste o se l'utente
     * non è membro della conversazione di appartenenza.
     */
    public function getAttachmentForUser(int $attachmentId, int $userId): ?array
    {
        $attachment = $this->messageRepo->findAttachment($attachmentId);
        if (!$attachment) {
            return null;
        }

        if (!$this->conversationRepo->findForUser((int) $attachment['conversation_id'], $userId)) {
            return null;
        }

        return $attachment;
    }

    public function getOlderMessages(int $conversationId, int $userId, int $beforeId): ?array
    {
        $conversation = $this->conversationRepo->findForUser($conversationId, $userId);
        if (!$conversation) {
            return null;
        }

        $messages = $this->messageRepo->getOlderThan($conversationId, $beforeId, 30);

        return [
            'conversation' => $conversation,
            'messages' => $messages,
            'hasOlderMessages' => !empty($messages) ? $this->messageRepo->hasOlderMessages($conversationId, (int) $messages[0]['id']) : false,
            'othersMaxReadAt' => $this->conversationRepo->getOthersMaxReadAt($conversationId, $userId),
        ];
    }

    /**
     * Invia un messaggio. Supporta N allegati e replyToId opzionale.
     */
    public function sendMessage(
        int $conversationId,
        int $userId,
        string $userName,
        string $body,
        ?array $attachments = null,
        ?int $replyToId = null
    ): ?array {
        $conversation = $this->conversationRepo->findForUser($conversationId, $userId);
        if (!$conversation) {
            return null;
        }

        $files = self::normalizeAttachments($attachments);
        $hasAttachment = !empty($files);
        if (!$hasAttachment && trim($body) === '') {
            return null;
        }

        // Valida reply target: deve esistere, essere nella stessa conv, non deleted.
        $validReplyId = null;
        if ($replyToId !== null && $replyToId > 0
            && $this->messageRepo->isValidReplyTarget($conversationId, $replyToId)) {
            $validReplyId = $replyToId;
        }

        $messageBody = trim($body) !== '' ? $body : t('teams.exception.default_attachment_body');
        $message = $validReplyId !== null
            ? $this->messageRepo->createMessageWithReply($conversationId, $userId, $messageBody, $validReplyId, 'text')
            : $this->messageRepo->createMessage($conversationId, $userId, $messageBody, 'text');

        if ($hasAttachment) {
            $metas = [];
            foreach ($files as $file) {
                try {
                    $meta = FileUploadService::uploadFile($file, 'teams', 'tm_', 52428800);
                    $meta['original_name'] = (string) ($file['name'] ?? $meta['filename']);
                    $metas[] = $meta;
                } catch (\Throwable $e) {
                    error_log('[TeamsMessageService] upload failed: ' . $e->getMessage());
                }
            }
            if (!empty($metas)) {
                $this->messageRepo->attachFilesToMessage((int) $message['id'], $metas);
            }
            $message = $this->messageRepo->findWithUser((int) $message['id']) ?? $message;
        }

        // Mention parsing: estrai @nome o @username dal body e notifica
        // i membri della conversazione effettivamente menzionati.
        $mentionedUserIds = [];
        if (trim($body) !== '') {
            $candidates = self::extractMentionCandidates($body);
            if (!empty($candidates)) {
                $matched = $this->conversationRepo->findMembersByMentionCandidates($conversationId, $candidates);
                foreach ($matched as $u) {
                    $uid = (int) $u['id'];
                    if ($uid !== $userId) {
                        $mentionedUserIds[] = $uid;
                    }
                }
                $mentionedUserIds = array_values(array_unique($mentionedUserIds));
                if (!empty($mentionedUserIds)) {
                    $this->messageRepo->insertMentions((int) $message['id'], $mentionedUserIds);
                }
            }
        }

        $this->conversationRepo->unhideForAllMembers($conversationId);
        $this->conversationRepo->markAsRead($conversationId, $userId);

        $teamsNotif = app(TeamsNotificationService::class);
        $teamsNotif->notifyNewMessage(
            $conversationId,
            $userId,
            $userName,
            $body,
            $conversation['type'] === 'group' ? ($conversation['name'] ?? t('teams.exception.default_group_name')) : '',
            $conversation['type'] ?? 'group',
            // Esclude i menzionati: ricevono solo `teams.user_mentioned` (più mirato)
            $mentionedUserIds
        );
        if (!empty($mentionedUserIds)) {
            $teamsNotif->notifyMentions(
                $conversationId,
                $userId,
                $userName,
                $body,
                $conversation['name'] ?? null,
                $conversation['type'] ?? 'group',
                $mentionedUserIds
            );
        }

        return [
            'message' => $message,
            'conversation' => $conversation,
            'othersMaxReadAt' => $this->conversationRepo->getOthersMaxReadAt($conversationId, $userId),
            'readByCount' => $this->conversationRepo->countReadBy($conversationId, $userId, $message['created_at']),
        ];
    }

    public function updateMessage(int $conversationId, int $messageId, int $userId, string $body): ?array
    {
        $conversation = $this->conversationRepo->findForUser($conversationId, $userId);
        if (!$conversation) {
            return null;
        }

        $message = $this->messageRepo->findWithUser($messageId);
        if (!$message || (int) $message['conversation_id'] !== $conversationId) {
            return ['status' => 'not_found'];
        }

        if ((int) $message['user_id'] !== $userId && !has_permission('teams.admin')) {
            return ['status' => 'forbidden'];
        }

        if ($this->conversationRepo->isMessageReadByOthers($conversationId, $userId, $message['created_at'])) {
            return ['status' => 'read_by_others'];
        }

        $updated = $this->messageRepo->editMessage($messageId, $body, $userId);

        return [
            'status' => 'ok',
            'message' => $updated,
            'othersMaxReadAt' => $this->conversationRepo->getOthersMaxReadAt($conversationId, $userId),
            'readByCount' => $this->conversationRepo->countReadBy($conversationId, $userId, $updated['created_at']),
        ];
    }

    public function getMessageHistory(int $conversationId, int $messageId, int $userId): ?array
    {
        $conversation = $this->conversationRepo->findForUser($conversationId, $userId);
        if (!$conversation) {
            return null;
        }

        $message = $this->messageRepo->findWithUser($messageId);
        if (!$message || (int) $message['conversation_id'] !== $conversationId) {
            return ['status' => 'not_found'];
        }

        return [
            'status' => 'ok',
            'message' => $message,
            'edits' => $this->messageRepo->getEditHistory($messageId),
        ];
    }

    public function getMessageReaders(int $conversationId, int $messageId, int $userId): ?array
    {
        $conversation = $this->conversationRepo->findForUser($conversationId, $userId);
        if (!$conversation) {
            return null;
        }

        $message = $this->messageRepo->findWithUser($messageId);
        if (!$message || (int) $message['conversation_id'] !== $conversationId) {
            return ['status' => 'not_found'];
        }

        $senderUserId = (int) ($message['user_id'] ?? 0);
        $readers = $this->conversationRepo->getReadersForMessage(
            $conversationId,
            $senderUserId,
            (string) $message['created_at']
        );

        return [
            'status' => 'ok',
            'readers' => $readers,
        ];
    }

    public function deleteMessage(int $conversationId, int $messageId, int $userId): ?array
    {
        $conversation = $this->conversationRepo->findForUser($conversationId, $userId);
        if (!$conversation) {
            return null;
        }

        $message = $this->messageRepo->findWithUser($messageId);
        if (!$message || (int) $message['conversation_id'] !== $conversationId) {
            return ['status' => 'not_found'];
        }

        if ((int) $message['user_id'] !== $userId && !has_permission('teams.admin')) {
            return ['status' => 'forbidden'];
        }

        if ($this->conversationRepo->isMessageReadByOthers($conversationId, $userId, $message['created_at'])) {
            return ['status' => 'read_by_others'];
        }

        $this->messageRepo->softDelete($messageId);
        $deleted = $this->messageRepo->findWithUser($messageId);

        return [
            'status' => 'ok',
            'message' => $deleted,
            'othersMaxReadAt' => $this->conversationRepo->getOthersMaxReadAt($conversationId, $userId),
            'readByCount' => $this->conversationRepo->countReadBy($conversationId, $userId, $deleted['created_at']),
        ];
    }
}
