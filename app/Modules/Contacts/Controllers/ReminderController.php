<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Controllers;

use App\Core\Controller;
use App\Modules\Contacts\Services\ContactsReminderService;
use App\Traits\ControllerHelpers;

class ReminderController extends Controller
{
    use ControllerHelpers;

    private ContactsReminderService $service;

    public function __construct()
    {
        $this->service = app(ContactsReminderService::class);
    }

    /**
     * Endpoint chiamato via HTMX fire-and-forget all'apertura dell'index.
     * Processa i reminder per l'utente corrente e ritorna JSON.
     */
    public function process(): void
    {
        $userId = (int) $_SESSION['user_id'];
        $sent   = $this->service->processForUser($userId);

        $this->json(['ok' => true, 'sent' => $sent]);
    }
}
