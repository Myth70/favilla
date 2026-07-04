<?php

declare(strict_types=1);

namespace App\Modules\Teams\Controllers;

use App\Core\Controller;
use App\Modules\Teams\Services\TeamsMemberService;
use App\Traits\ControllerHelpers;

class MemberController extends Controller
{
    use ControllerHelpers;
    private TeamsMemberService $service;

    public function __construct()
    {
        $this->service = app(TeamsMemberService::class);
    }

    /**
     * Lista membri di una conversazione.
     */
    public function index(string $id): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $convId = (int) $id;

        $result = $this->service->getMembersList($convId, $userId);
        if (!$result) {
            echo '';
            return;
        }

        $this->renderPartial('Teams/Views/partials/member_list', [
            'members'        => $result['members'],
            'conv'           => $result['conversation'],
            'currentUserId'  => $userId,
        ]);
    }

    /**
     * Aggiungi membri a un gruppo.
     */
    public function store(string $id): void
    {
        $userId   = (int) ($_SESSION['user_id'] ?? 0);
        $userName = (string) ($_SESSION['user_name'] ?? t('teams.exception.default_user_name'));
        $convId   = (int) $id;

        $newMemberIds = array_map('intval', array_filter((array) ($_POST['members'] ?? [])));
        if (empty($newMemberIds)) {
            flash_error(t('teams.flash.select_at_least_one_user'));
            $this->redirect(route('teams.show', ['id' => $convId]));
            return;
        }

        $result = $this->service->addMembers($convId, $userId, $userName, $newMemberIds);
        if ($result['status'] === 'not_found') {
            flash_error(t('teams.flash.conversation_not_found'));
            $this->redirect(route('teams.show', ['id' => $convId]));
            return;
        }
        if ($result['status'] === 'forbidden') {
            flash_error(t('teams.flash.not_authorized_add_members'));
            $this->redirect(route('teams.show', ['id' => $convId]));
            return;
        }

        flash_success(t('teams.flash.members_added'));

        if ($this->isHtmxRequest()) {
            $this->renderPartial('Teams/Views/partials/member_list', [
                'members'       => $result['members'],
                'conv'          => $result['conversation'],
                'currentUserId' => $userId,
            ]);
            return;
        }

        $this->redirect(route('teams.show', ['id' => $convId]));
    }

    /**
     * Rimuovi un membro da un gruppo.
     */
    public function destroy(string $id, string $userId): void
    {
        $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
        $currentUserName = (string) ($_SESSION['user_name'] ?? t('teams.exception.default_user_name'));
        $convId        = (int) $id;
        $targetUserId  = (int) $userId;

        $result = $this->service->removeMember($convId, $currentUserId, $currentUserName, $targetUserId);
        if ($result['status'] === 'not_found') {
            http_response_code(404);
            echo '';
            return;
        }
        if ($result['status'] === 'forbidden') {
            http_response_code(403);
            echo '';
            return;
        }
        if ($result['status'] === 'invalid_target') {
            http_response_code(400);
            echo '';
            return;
        }

        if ($this->isHtmxRequest()) {
            $this->renderPartial('Teams/Views/partials/member_list', [
                'members'       => $result['members'],
                'conv'          => $result['conversation'],
                'currentUserId' => $currentUserId,
            ]);
            return;
        }

        flash_success(t('teams.flash.member_removed'));
        $this->redirect(route('teams.show', ['id' => $convId]));
    }
}
