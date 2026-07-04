<?php

declare(strict_types=1);

namespace App\Modules\Teams\Services;

use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Teams\Repositories\ConversationRepository;
use App\Modules\Teams\Repositories\PresenceRepository;

class TeamsNotificationService
{
    private ConversationRepository $conversationRepo;
    private PresenceRepository $presenceRepo;

    public function __construct()
    {
        $this->conversationRepo = app(ConversationRepository::class);
        $this->presenceRepo     = app(PresenceRepository::class);
    }

    /**
     * Notifica i membri della conversazione di un nuovo messaggio.
     *
     * @param int[] $excludeUserIds Utenti da non notificare (es. perché già
     *                              notificati via `teams.user_mentioned`).
     */
    public function notifyNewMessage(
        int    $conversationId,
        int    $senderId,
        string $senderName,
        string $messagePreview,
        string $conversationName,
        string $conversationType = 'group',
        array  $excludeUserIds = []
    ): void {
        $members  = $this->conversationRepo->getActiveMembersWithMuteStatus($conversationId);
        $excluded = array_flip(array_map('intval', $excludeUserIds));

        foreach ($members as $member) {
            $memberId = (int) $member['user_id'];

            if ($memberId === $senderId) {
                continue;
            }
            if (isset($excluded[$memberId])) {
                continue;
            }
            if ($member['notifications_muted']) {
                continue;
            }
            if ($this->presenceRepo->isViewingConversation($memberId, $conversationId)) {
                continue;
            }

            $preview = mb_strlen($messagePreview) > 100
                ? mb_substr($messagePreview, 0, 100) . '...'
                : $messagePreview;

            try {
                if ($conversationType === 'direct') {
                    NotificationService::dispatchEventToUser(
                        'teams.new_direct_message',
                        'Teams',
                        $memberId,
                        [
                            'conversation_id' => $conversationId,
                            'sender_name'     => $senderName,
                            'message_preview' => $preview,
                        ],
                        route('teams.show', ['id' => $conversationId]),
                        $senderId
                    );
                } else {
                    NotificationService::dispatchEventToUser(
                        'teams.new_message',
                        'Teams',
                        $memberId,
                        [
                            'conversation_id'   => $conversationId,
                            'sender_name'       => $senderName,
                            'conversation_name' => $conversationName,
                            'message_preview'   => $preview,
                        ],
                        route('teams.show', ['id' => $conversationId]),
                        $senderId
                    );
                }
            } catch (\Throwable $e) {
                error_log('[TeamsNotificationService] NotificationService unavailable: ' . $e->getMessage());
            }
        }
    }

    /**
     * Notifica gli utenti menzionati in un messaggio. A differenza di
     * `notifyNewMessage()`, un mention bypassa il flag `notifications_muted`
     * (è considerato alta priorità: il mittente ha esplicitamente chiamato
     * quell'utente). Skippa solo se l'utente sta guardando la conversazione.
     *
     * @param int[] $mentionedUserIds
     */
    public function notifyMentions(
        int $conversationId,
        int $senderId,
        string $senderName,
        string $messagePreview,
        ?string $conversationName,
        string $conversationType,
        array $mentionedUserIds
    ): void {
        $preview = mb_strlen($messagePreview) > 100
            ? mb_substr($messagePreview, 0, 100) . '...'
            : $messagePreview;

        foreach ($mentionedUserIds as $uid) {
            $uid = (int) $uid;
            if ($uid <= 0 || $uid === $senderId) {
                continue;
            }
            // Skip se sta già guardando la conversazione (lo vede comunque)
            if ($this->presenceRepo->isViewingConversation($uid, $conversationId)) {
                continue;
            }
            try {
                NotificationService::dispatchEventToUser(
                    'teams.user_mentioned',
                    'Teams',
                    $uid,
                    [
                        'conversation_id'   => $conversationId,
                        'conversation_name' => $conversationName ?? ($conversationType === 'direct' ? '' : t('teams.exception.default_group_name')),
                        'sender_name'       => $senderName,
                        'message_preview'   => $preview,
                    ],
                    route('teams.show', ['id' => $conversationId]),
                    $senderId
                );
            } catch (\Throwable $e) {
                error_log('[TeamsNotificationService] notifyMentions failed: ' . $e->getMessage());
            }
        }
    }

    public function notifyPromotion(int $conversationId, int $promotedUserId, string $conversationName): void
    {
        try {
            NotificationService::dispatchEventToUser(
                'teams.member_promoted',
                'Teams',
                $promotedUserId,
                [
                    'conversation_id'   => $conversationId,
                    'conversation_name' => $conversationName,
                ],
                route('teams.show', ['id' => $conversationId]),
                null
            );
        } catch (\Throwable $e) {
            error_log('[TeamsNotificationService] notifyPromotion failed: ' . $e->getMessage());
        }
    }

    public function notifyArchived(int $conversationId, int $archivedByUserId, string $archiverName, string $conversationName): void
    {
        $members = $this->conversationRepo->getActiveMemberIds($conversationId);

        foreach ($members as $memberId) {
            $memberId = (int) $memberId;
            if ($memberId === $archivedByUserId) {
                continue;
            }

            try {
                NotificationService::dispatchEventToUser(
                    'teams.conversation_archived',
                    'Teams',
                    $memberId,
                    [
                        'conversation_id'   => $conversationId,
                        'archiver_name'     => $archiverName,
                        'conversation_name' => $conversationName,
                    ],
                    route('teams.index'),
                    $archivedByUserId
                );
            } catch (\Throwable $e) {
                error_log('[TeamsNotificationService] notifyArchived failed: ' . $e->getMessage());
            }
        }
    }
}
