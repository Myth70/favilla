<?php

declare(strict_types=1);

namespace App\Modules\Teams\Services;

use App\Modules\Teams\Repositories\ConversationRepository;
use App\Modules\Teams\Repositories\MessageRepository;

class TeamsMemberService
{
    private ConversationRepository $conversationRepo;
    private MessageRepository $messageRepo;
    private \PDO $pdo;

    public function __construct()
    {
        $this->conversationRepo = app(ConversationRepository::class);
        $this->messageRepo = app(MessageRepository::class);
        $this->pdo = app(\PDO::class);
    }

    public function getMembersList(int $conversationId, int $userId): ?array
    {
        $conversation = $this->conversationRepo->findForUser($conversationId, $userId);
        if (!$conversation) {
            return null;
        }

        return [
            'conversation' => $conversation,
            'members' => $this->conversationRepo->getActiveMembers($conversationId),
        ];
    }

    public function addMembers(int $conversationId, int $userId, string $userName, array $newMemberIds): array
    {
        $conversation = $this->conversationRepo->findForUser($conversationId, $userId);
        if (!$conversation || $conversation['type'] !== 'group') {
            return ['status' => 'not_found'];
        }

        if ($conversation['my_role'] !== 'admin' && !has_permission('teams.admin')) {
            return ['status' => 'forbidden'];
        }

        $stmt = $this->pdo->prepare('SELECT name FROM users WHERE id = ? AND is_active = 1 AND deleted_at IS NULL');
        foreach ($newMemberIds as $newMemberId) {
            $stmt->execute([$newMemberId]);
            $memberName = $stmt->fetchColumn();
            if (!$memberName) {
                continue;
            }

            try {
                $this->conversationRepo->addMember($conversationId, $newMemberId, 'member');
                $this->messageRepo->createSystemMessage($conversationId, t('teams.exception.member_added_message', ['actor' => $userName, 'member' => $memberName]));
            } catch (\Throwable $exception) {
                error_log('[Teams] TeamsMemberService::addMembers failed for member ' . $newMemberId . ': ' . $exception->getMessage());
            }
        }

        return [
            'status' => 'ok',
            'conversation' => $conversation,
            'members' => $this->conversationRepo->getActiveMembers($conversationId),
        ];
    }

    public function removeMember(int $conversationId, int $currentUserId, string $currentUserName, int $targetUserId): array
    {
        $conversation = $this->conversationRepo->findForUser($conversationId, $currentUserId);
        if (!$conversation || $conversation['type'] !== 'group') {
            return ['status' => 'not_found'];
        }

        if ($conversation['my_role'] !== 'admin' && !has_permission('teams.admin')) {
            return ['status' => 'forbidden'];
        }

        if ($targetUserId === $currentUserId) {
            return ['status' => 'invalid_target'];
        }

        $stmt = $this->pdo->prepare('SELECT name FROM users WHERE id = ?');
        $stmt->execute([$targetUserId]);
        $memberName = $stmt->fetchColumn() ?: t('teams.exception.default_user_name');

        $this->conversationRepo->removeMember($conversationId, $targetUserId);
        $this->messageRepo->createSystemMessage($conversationId, t('teams.exception.member_removed_message', ['actor' => $currentUserName, 'member' => $memberName]));

        return [
            'status' => 'ok',
            'conversation' => $conversation,
            'members' => $this->conversationRepo->getActiveMembers($conversationId),
        ];
    }
}
