<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserCreated;
use App\Modules\Home\Repositories\WidgetPreferencesRepository;

class CopyAdminWidgetLayout
{
    private const ADMIN_USER_ID = 1;

    /**
     * Copia il layout dashboard dell'admin (id=1) al nuovo utente.
     * Se l'admin non ha preferenze salvate, il nuovo utente ottiene i default di sistema.
     */
    public function handle(UserCreated $event): void
    {
        $repo = app(WidgetPreferencesRepository::class);

        $adminPrefs = $repo->getByUserId(self::ADMIN_USER_ID);

        if (empty($adminPrefs)) {
            return;
        }

        // Rimuovere id e user_id dall'admin prima di replicare
        $items = array_map(static fn (array $row) => [
            'widget_id'  => $row['widget_id'],
            'sort_order' => (int) $row['sort_order'],
            'visible'    => (int) $row['visible'],
        ], $adminPrefs);

        $repo->replaceAll($event->userId, $items);
    }
}
