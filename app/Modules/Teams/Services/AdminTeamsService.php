<?php

declare(strict_types=1);

namespace App\Modules\Teams\Services;

use App\Modules\Teams\Repositories\ConversationRepository;
use App\Modules\Teams\Repositories\MessageRepository;

class AdminTeamsService
{
    private ConversationRepository $conversationRepo;
    private MessageRepository $messageRepo;

    public function __construct()
    {
        $this->conversationRepo = app(ConversationRepository::class);
        $this->messageRepo = app(MessageRepository::class);
    }

    public function getIndexData(string $search, string $filter, int $page, int $perPage, int $defaultMonths): array
    {
        return [
            'stats' => $this->conversationRepo->adminStats(),
            'conversations' => $this->conversationRepo->adminList($search, $filter, $page, $perPage),
            'total' => $this->conversationRepo->adminCount($search, $filter),
            'cleanupCount' => $this->messageRepo->countOlderThan($defaultMonths),
        ];
    }

    public function getConversationTableData(string $search, string $filter, int $page, int $perPage): array
    {
        return [
            'conversations' => $this->conversationRepo->adminList($search, $filter, $page, $perPage),
            'total' => $this->conversationRepo->adminCount($search, $filter),
        ];
    }

    public function getCleanupPreviewCount(int $months): int
    {
        return $this->messageRepo->countOlderThan($months);
    }

    public function cleanupOldMessages(int $months): int
    {
        return $this->messageRepo->cleanupOldMessages($months);
    }

    public function archiveConversation(int $id, int $userId, string $userName): string
    {
        $conversation = $this->conversationRepo->find($id);
        if (!$conversation) {
            return 'not_found';
        }

        if ($conversation['archived_at'] !== null) {
            return 'already_archived';
        }

        $this->conversationRepo->archive($id);
        $this->messageRepo->createSystemMessage($id, t('teams.exception.archived_message', ['actor' => $userName]));
        app(TeamsNotificationService::class)->notifyArchived($id, $userId, $userName, $conversation['name'] ?? '');

        return 'archived';
    }

    public function destroyConversation(int $id): string
    {
        $conversation = $this->conversationRepo->find($id);
        if (!$conversation) {
            return 'not_found';
        }

        if ($conversation['archived_at'] === null) {
            return 'must_archive_first';
        }

        $this->conversationRepo->hardDelete($id);

        return 'deleted';
    }
}
