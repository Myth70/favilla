<?php

declare(strict_types=1);

namespace App\Modules\Teams\Controllers;

use App\Core\Controller;
use App\Modules\Teams\Services\TeamsReactionService;
use App\Traits\ControllerHelpers;

class ReactionController extends Controller
{
    use ControllerHelpers;

    private TeamsReactionService $service;

    /** Emoji consentite (subset fisso — no CDN). */
    private const ALLOWED_EMOJI = [
        '👍','👎','❤️','😂','😮','😢','🔥','🎉','👀','✅',
        '🙏','💯','🚀','⭐','😊','🤔','😅','💪','🤣','👏',
    ];

    public function __construct()
    {
        $this->service = app(TeamsReactionService::class);
    }

    /**
     * Toggle reazione — POST /teams/{id}/messages/{msgId}/reactions
     * Body: emoji=<emoji>
     */
    public function toggle(string $id, string $messageId): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $convId = (int) $id;
        $msgId  = (int) $messageId;

        $emoji = $this->cleanPost(['emoji'])['emoji'] ?? '';
        if (!in_array($emoji, self::ALLOWED_EMOJI, true)) {
            http_response_code(422);
            return;
        }

        $result = $this->service->toggleReaction($convId, $msgId, $userId, $emoji);
        if ($result === null) {
            http_response_code(403);
            return;
        }
        if ($result['status'] === 'not_found') {
            http_response_code(404);
            return;
        }

        $this->renderPartial('Teams/Views/partials/reactions_bar', [
            'messageId'     => $msgId,
            'conversationId' => $convId,
            'reactions'     => $result['reactions'],
            'currentUserId' => $userId,
            'allowedEmoji'  => self::ALLOWED_EMOJI,
        ]);
    }

    /**
     * Espone ALLOWED_EMOJI come costante pubblica (usato in template).
     */
    public static function allowedEmoji(): array
    {
        return self::ALLOWED_EMOJI;
    }
}
