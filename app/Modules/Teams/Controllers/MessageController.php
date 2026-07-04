<?php

declare(strict_types=1);

namespace App\Modules\Teams\Controllers;

use App\Core\Controller;
use App\Modules\Teams\Services\TeamsMessageService;
use App\Traits\ControllerHelpers;

class MessageController extends Controller
{
    use ControllerHelpers;
    private TeamsMessageService $service;

    public function __construct()
    {
        $this->service = app(TeamsMessageService::class);
    }

    /**
     * Streaming di un allegato — GET /teams/attachments/{attachmentId}.
     * uploads/teams non è servita da Apache: l'accesso passa da qui, con
     * verifica membership sulla conversazione di appartenenza.
     */
    public function attachment(string $attachmentId): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $att = $this->service->getAttachmentForUser((int) $attachmentId, $userId);
        if (!$att) {
            http_response_code(404);
            return;
        }

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $filePath = $basePath . '/public/uploads/teams/' . basename((string) $att['stored_name']);
        if (!is_file($filePath)) {
            http_response_code(404);
            return;
        }

        $mime   = (string) ($att['mime_type'] ?: 'application/octet-stream');
        $inline = $mime === 'application/pdf'
            || str_starts_with($mime, 'image/')
            || str_starts_with($mime, 'video/')
            || str_starts_with($mime, 'audio/');

        $name = str_replace(["\r", "\n"], '', (string) ($att['original_name'] ?: 'allegato'));

        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
            . "; filename*=UTF-8''" . rawurlencode($name));
        header('Content-Length: ' . (string) filesize($filePath));
        header('Cache-Control: private, max-age=300');
        readfile($filePath);
        exit;
    }

    /**
     * Carica messaggi piu' vecchi (infinite scroll up).
     */
    public function index(string $id): void
    {
        $userId   = (int) ($_SESSION['user_id'] ?? 0);
        $convId   = (int) $id;
        $beforeId = (int) ($_GET['before'] ?? 0);

        if ($beforeId <= 0) {
            http_response_code(400);
            return;
        }

        $result = $this->service->getOlderMessages($convId, $userId, $beforeId);
        if (!$result) {
            http_response_code(403);
            return;
        }

        $this->renderPartial('Teams/Views/partials/messages_page', [
            'messages'         => $result['messages'],
            'currentUserId'    => $userId,
            'conversationId'   => $convId,
            'hasOlderMessages' => $result['hasOlderMessages'],
            'othersMaxReadAt'  => $result['othersMaxReadAt'],
        ]);
    }

    /**
     * Invia un messaggio.
     */
    public function store(string $id): void
    {
        $userId   = (int) ($_SESSION['user_id'] ?? 0);
        $userName = (string) ($_SESSION['user_name'] ?? t('teams.exception.default_user_name'));
        $convId   = (int) $id;

        $post = $this->cleanPost(['body', 'reply_to_id']);
        $body = (string) ($post['body'] ?? '');
        $replyToId = isset($post['reply_to_id']) && $post['reply_to_id'] !== ''
            ? (int) $post['reply_to_id']
            : null;
        // Accetta `attachments[]` (nuovo, multi-file) o `attachment` (singolo, retrocompat).
        $rawAttachments = $_FILES['attachments'] ?? ($_FILES['attachment'] ?? null);
        $files = \App\Modules\Teams\Services\TeamsMessageService::normalizeAttachments($rawAttachments);
        $hasAttachment = !empty($files);

        if (!$hasAttachment && $body === '') {
            http_response_code(422);
            echo '';
            return;
        }

        if (mb_strlen($body) > 5000) {
            http_response_code(422);
            echo '';
            return;
        }

        // Rate limiting: min 1s tra messaggi
        $now = microtime(true);
        $lastMsgAt = $_SESSION['_teams_last_msg_at'] ?? 0.0;
        if (($now - $lastMsgAt) < 1.0) {
            http_response_code(429);
            echo '';
            return;
        }
        $_SESSION['_teams_last_msg_at'] = $now;

        try {
            $result = $this->service->sendMessage($convId, $userId, $userName, $body, $files, $replyToId);
        } catch (\RuntimeException $e) {
            http_response_code(422);
            echo e($e->getMessage());
            return;
        }
        if (!$result) {
            http_response_code(403);
            echo '';
            return;
        }

        header('HX-Trigger: teamsConvRefresh');

        $this->renderPartial('Teams/Views/partials/message_bubble', [
            'msg'            => $result['message'],
            'currentUserId'  => $userId,
            'showAvatar'     => true,
            'othersMaxReadAt' => $result['othersMaxReadAt'],
            'readByCount'    => $result['readByCount'],
        ]);
    }

    /**
     * Modifica un messaggio.
     */
    public function update(string $id, string $messageId): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $convId = (int) $id;
        $msgId  = (int) $messageId;

        $result = $this->service->updateMessage($convId, $msgId, $userId, $this->cleanPost(['body'])['body']);
        if ($result === null) {
            http_response_code(403);
            echo '';
            return;
        }
        if ($result['status'] === 'not_found') {
            http_response_code(404);
            echo '';
            return;
        }
        if ($result['status'] === 'forbidden' || $result['status'] === 'read_by_others') {
            http_response_code(403);
            echo '';
            return;
        }

        $this->renderPartial('Teams/Views/partials/message_bubble', [
            'msg'             => $result['message'],
            'currentUserId'   => $userId,
            'showAvatar'      => true,
            'othersMaxReadAt' => $result['othersMaxReadAt'],
            'readByCount'     => $result['readByCount'],
        ]);
    }

    /**
     * Cronologia modifiche di un messaggio.
     */
    public function history(string $id, string $messageId): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $convId = (int) $id;
        $msgId  = (int) $messageId;

        $result = $this->service->getMessageHistory($convId, $msgId, $userId);
        if ($result === null) {
            http_response_code(403);
            return;
        }
        if ($result['status'] === 'not_found') {
            http_response_code(404);
            return;
        }

        $this->renderPartial('Teams/Views/partials/edit_history', [
            'msg'   => $result['message'],
            'edits' => $result['edits'],
        ]);
    }

    /**
     * Toggle pin/unpin di un messaggio (solo admin conversazione o teams.admin).
     */
    public function togglePin(string $id, string $messageId): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $convId = (int) $id;
        $msgId  = (int) $messageId;

        $result = app(\App\Modules\Teams\Services\TeamsService::class)->togglePin($convId, $msgId, $userId);
        if ($result === null) {
            http_response_code(403);
            return;
        }
        if ($result['status'] === 'forbidden') {
            http_response_code(403);
            return;
        }
        if ($result['status'] === 'not_found') {
            http_response_code(404);
            return;
        }

        header('HX-Trigger: ' . json_encode([
            'teamsPinnedRefresh' => ['conversationId' => $convId],
        ], JSON_UNESCAPED_UNICODE));

        echo $result['pinned'] ? '1' : '0';
    }

    /**
     * Elenco dei messaggi pinned di una conversazione (HTMX partial).
     */
    public function pinnedList(string $id): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $convId = (int) $id;

        $result = app(\App\Modules\Teams\Services\TeamsService::class)->getPinnedData($convId, $userId);
        if ($result === null) {
            http_response_code(403);
            return;
        }

        $this->renderPartial('Teams/Views/partials/pinned_list', [
            'pinnedMessages' => $result['messages'],
            'pinnedCount'    => $result['count'],
        ]);
    }

    /**
     * Restituisce l'elenco degli utenti che hanno letto un messaggio (popover).
     */
    public function readers(string $id, string $messageId): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $convId = (int) $id;
        $msgId  = (int) $messageId;

        $result = $this->service->getMessageReaders($convId, $msgId, $userId);
        if ($result === null) {
            http_response_code(403);
            return;
        }
        if ($result['status'] === 'not_found') {
            http_response_code(404);
            return;
        }

        $this->renderPartial('Teams/Views/partials/read_receipts', [
            'readers' => $result['readers'],
        ]);
    }

    /**
     * Elimina un messaggio (soft-delete).
     */
    public function destroy(string $id, string $messageId): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $convId = (int) $id;
        $msgId  = (int) $messageId;

        $result = $this->service->deleteMessage($convId, $msgId, $userId);
        if ($result === null) {
            http_response_code(403);
            echo '';
            return;
        }
        if ($result['status'] === 'not_found') {
            http_response_code(404);
            echo '';
            return;
        }
        if ($result['status'] === 'forbidden' || $result['status'] === 'read_by_others') {
            http_response_code(403);
            echo '';
            return;
        }

        $this->renderPartial('Teams/Views/partials/message_bubble', [
            'msg'            => $result['message'],
            'currentUserId'  => $userId,
            'showAvatar'     => true,
            'othersMaxReadAt' => $result['othersMaxReadAt'],
            'readByCount'    => $result['readByCount'],
        ]);
    }
}
