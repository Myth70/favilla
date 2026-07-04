<?php

declare(strict_types=1);

namespace App\Modules\Teams\Services;

use App\Modules\Teams\Repositories\ConversationRepository;
use App\Modules\Teams\Repositories\MessageRepository;

class TeamsReactionService
{
    private ConversationRepository $conversationRepo;
    private MessageRepository $messageRepo;

    public function __construct()
    {
        $this->conversationRepo = app(ConversationRepository::class);
        $this->messageRepo = app(MessageRepository::class);
    }

    public function toggleReaction(int $conversationId, int $messageId, int $userId, string $emoji): ?array
    {
        $conversation = $this->conversationRepo->findForUser($conversationId, $userId);
        if (!$conversation) {
            return null;
        }

        $message = $this->messageRepo->findWithUser($messageId);
        if (!$message || (int) $message['conversation_id'] !== $conversationId || !empty($message['deleted_at'])) {
            return ['status' => 'not_found'];
        }

        $this->messageRepo->toggleReaction($messageId, $userId, $emoji);

        return [
            'status' => 'ok',
            'reactions' => $this->messageRepo->getReactions($messageId),
        ];
    }
}
