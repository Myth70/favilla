<?php

declare(strict_types=1);

namespace App\Modules\Teams\Services;

use App\Modules\Teams\Repositories\ConversationRepository;
use App\Modules\Teams\Repositories\MessageRepository;
use App\Modules\Teams\Support\TeamsLinkExtractor;

/**
 * Servizio per le sezioni dell'offcanvas "Info gruppo" stile Telegram:
 *
 *  - header   → dati aggregati per l'hero del pannello
 *  - media    → allegati image/* o video/* paginati (infinite scroll)
 *  - files    → allegati non-image/non-video paginati, filtrabili per categoria
 *  - links    → URL estratti dai body dei messaggi
 *
 * Ogni metodo verifica l'autorizzazione tramite ConversationRepository::findForUser
 * (membro attivo). Null = 403 lato controller.
 */
class GroupPanelService
{
    public const MEDIA_PAGE_SIZE = 30;
    public const FILES_PAGE_SIZE = 50;
    public const LINKS_PAGE_SIZE = 50;

    private ConversationRepository $conversationRepo;
    private MessageRepository $messageRepo;

    public function __construct()
    {
        $this->conversationRepo = app(ConversationRepository::class);
        $this->messageRepo      = app(MessageRepository::class);
    }

    /**
     * Dati hero dell'offcanvas: avatar, nome, descrizione, creato il/da,
     * conteggio membri attivi, conteggio messaggi.
     *
     * @return array{
     *   id:int, name:?string, description:?string, avatar_path:?string,
     *   created_at:string, created_by:?int, creator_name:?string,
     *   member_count:int, message_count:int, my_role:string, notifications_muted:int
     * }|null
     */
    public function getHeaderData(int $conversationId, int $userId): ?array
    {
        $info = $this->conversationRepo->getGroupHeaderInfo($conversationId, $userId);
        if ($info === null) {
            return null;
        }
        $info['message_count'] = $this->messageRepo->countMessagesInConversation($conversationId);
        return $info;
    }

    /**
     * Pagina di media (image/* o video/*). Ordine DESC su attachment.id.
     *
     * Strategia hasMore: chiede $limit+1, se ne arrivano $limit+1 c'è altra
     * pagina e l'ultimo (extra) viene scartato. `nextBefore` è l'id dell'ultimo
     * elemento restituito (cursor per la richiesta successiva).
     *
     * @return array{items:array<int,array<string,mixed>>, hasMore:bool, nextBefore:?int, total:int}|null
     */
    public function getMediaPage(int $conversationId, int $userId, ?int $beforeId = null): ?array
    {
        if ($this->conversationRepo->findForUser($conversationId, $userId) === null) {
            return null;
        }

        $limit = self::MEDIA_PAGE_SIZE;
        $rows  = $this->messageRepo->getMediaAttachments($conversationId, $beforeId, $limit + 1);

        $hasMore = count($rows) > $limit;
        $items   = $hasMore ? array_slice($rows, 0, $limit) : $rows;

        return [
            'items'      => $items,
            'hasMore'    => $hasMore,
            'nextBefore' => !empty($items) ? (int) $items[count($items) - 1]['id'] : null,
            'total'      => $beforeId === null ? $this->messageRepo->countMediaAttachments($conversationId) : 0,
        ];
    }

    /**
     * Pagina di file non-media. Filtri $kind: docs|archives|audio|all.
     *
     * @return array{items:array<int,array<string,mixed>>, hasMore:bool, nextBefore:?int, kind:string}|null
     */
    public function getFilesPage(int $conversationId, int $userId, ?int $beforeId = null, string $kind = 'all'): ?array
    {
        if ($this->conversationRepo->findForUser($conversationId, $userId) === null) {
            return null;
        }

        $kind  = in_array($kind, ['docs', 'archives', 'audio', 'all'], true) ? $kind : 'all';
        $limit = self::FILES_PAGE_SIZE;
        $rows  = $this->messageRepo->getFileAttachments($conversationId, $beforeId, $limit + 1, $kind);

        $hasMore = count($rows) > $limit;
        $items   = $hasMore ? array_slice($rows, 0, $limit) : $rows;

        return [
            'items'      => $items,
            'hasMore'    => $hasMore,
            'nextBefore' => !empty($items) ? (int) $items[count($items) - 1]['id'] : null,
            'kind'       => $kind,
        ];
    }

    /**
     * Pagina di link estratti dai body dei messaggi.
     *
     * Per ogni messaggio (cursor su message_id), espande gli URL trovati
     * in righe separate. Mantiene `message_id` come `nextBefore` per il
     * cursor; un messaggio con N URL produce N righe ma resta una sola
     * unità di paginazione (semplifica il bookkeeping).
     *
     * @return array{items:array<int,array{
     *   url:string, domain:string, message_id:int, body:string,
     *   user_id:?int, user_name:?string, created_at:string
     * }>, hasMore:bool, nextBefore:?int}|null
     */
    public function getLinksPage(int $conversationId, int $userId, ?int $beforeMessageId = null): ?array
    {
        if ($this->conversationRepo->findForUser($conversationId, $userId) === null) {
            return null;
        }

        $limit = self::LINKS_PAGE_SIZE;
        // Pre-filtra LIKE su http/www, poi conferma con regex PHP.
        // Carica $limit messaggi + 1 per il flag hasMore. Un singolo messaggio
        // può espandersi in più righe (più URL): non lo conto come "pagina",
        // il cursor resta su message_id.
        $rows = $this->messageRepo->getMessagesWithLinks($conversationId, $beforeMessageId, $limit + 1);

        $hasMore = count($rows) > $limit;
        $rowsForRender = $hasMore ? array_slice($rows, 0, $limit) : $rows;

        $items = [];
        foreach ($rowsForRender as $row) {
            $urls = TeamsLinkExtractor::extract((string) $row['body']);
            foreach ($urls as $url) {
                $items[] = [
                    'url'        => $url,
                    'domain'     => TeamsLinkExtractor::domain($url),
                    'message_id' => (int) $row['id'],
                    'body'       => (string) $row['body'],
                    'user_id'    => isset($row['user_id']) ? (int) $row['user_id'] : null,
                    'user_name'  => $row['user_name'] ?? null,
                    'created_at' => (string) $row['created_at'],
                ];
            }
        }

        $nextBefore = !empty($rowsForRender) ? (int) $rowsForRender[count($rowsForRender) - 1]['id'] : null;

        return [
            'items'      => $items,
            'hasMore'    => $hasMore,
            'nextBefore' => $nextBefore,
        ];
    }
}
