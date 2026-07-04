<?php

declare(strict_types=1);

namespace App\Modules\Teams\Controllers;

use App\Core\Controller;
use App\Modules\Teams\Services\GroupPanelService;
use App\Traits\ControllerHelpers;

/**
 * Endpoint dell'offcanvas "Info gruppo" stile Telegram:
 *  - header → rendering hero (avatar/nome/meta) standalone
 *  - media  → grid paginata di immagini/video
 *  - files  → lista paginata di documenti/archivi/audio
 *  - links  → URL estratti dai messaggi
 *
 * Tutti gli handler restituiscono partials HTML (HTMX target).
 * Autorizzazione: il service rifiuta i non-membri (null → 403).
 */
class GroupPanelController extends Controller
{
    use ControllerHelpers;

    private GroupPanelService $service;

    public function __construct()
    {
        $this->service = app(GroupPanelService::class);
    }

    public function header(string $id): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $convId = (int) $id;

        $info = $this->service->getHeaderData($convId, $userId);
        if ($info === null) {
            http_response_code(403);
            return;
        }

        // header.php usa $conv (id, name, description, my_role, notifications_muted):
        // già tutti presenti in $info, niente accesso diretto al Repository da qui.
        $canManage = ($info['my_role'] ?? '') === 'admin' || has_permission('teams.admin');

        $this->renderPartial('Teams/Views/partials/group_panel/header', [
            'info'      => $info,
            'conv'      => $info,
            'canManage' => $canManage,
        ]);
    }

    public function media(string $id): void
    {
        $userId   = (int) ($_SESSION['user_id'] ?? 0);
        $convId   = (int) $id;
        $beforeId = $this->parseBeforeId();

        $page = $this->service->getMediaPage($convId, $userId, $beforeId);
        if ($page === null) {
            http_response_code(403);
            return;
        }

        $this->renderPartial('Teams/Views/partials/group_panel/media_grid_page', [
            'conversationId' => $convId,
            'items'          => $page['items'],
            'hasMore'        => $page['hasMore'],
            'nextBefore'     => $page['nextBefore'],
            'isFirstPage'    => $beforeId === null,
            'total'          => $page['total'],
        ]);
    }

    public function files(string $id): void
    {
        $userId   = (int) ($_SESSION['user_id'] ?? 0);
        $convId   = (int) $id;
        $beforeId = $this->parseBeforeId();
        $kind     = $this->parseKind();

        $page = $this->service->getFilesPage($convId, $userId, $beforeId, $kind);
        if ($page === null) {
            http_response_code(403);
            return;
        }

        $this->renderPartial('Teams/Views/partials/group_panel/files_list_page', [
            'conversationId' => $convId,
            'items'          => $page['items'],
            'hasMore'        => $page['hasMore'],
            'nextBefore'     => $page['nextBefore'],
            'kind'           => $page['kind'],
            'isFirstPage'    => $beforeId === null,
        ]);
    }

    public function links(string $id): void
    {
        $userId   = (int) ($_SESSION['user_id'] ?? 0);
        $convId   = (int) $id;
        $beforeId = $this->parseBeforeId();

        $page = $this->service->getLinksPage($convId, $userId, $beforeId);
        if ($page === null) {
            http_response_code(403);
            return;
        }

        $this->renderPartial('Teams/Views/partials/group_panel/links_list_page', [
            'conversationId' => $convId,
            'items'          => $page['items'],
            'hasMore'        => $page['hasMore'],
            'nextBefore'     => $page['nextBefore'],
            'isFirstPage'    => $beforeId === null,
        ]);
    }

    private function parseBeforeId(): ?int
    {
        if (!isset($_GET['before_id'])) {
            return null;
        }
        $v = (int) $_GET['before_id'];
        return $v > 0 ? $v : null;
    }

    private function parseKind(): string
    {
        $v = (string) ($_GET['kind'] ?? 'all');
        return in_array($v, ['docs', 'archives', 'audio', 'all'], true) ? $v : 'all';
    }
}
