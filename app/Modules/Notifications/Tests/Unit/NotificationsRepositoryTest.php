<?php

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Repositories\NotificationsRepository;
use Tests\ModuleTestCase;

/**
 * Test per NotificationsRepository.
 *
 * Nota: markAsRead(), markAllAsRead(), markAsReadByLink() usano NOW() che non
 * esiste in SQLite nativo. ModuleTestCase registra una user-defined function
 * NOW() → date('Y-m-d H:i:s'), quindi tutte le query funzionano correttamente.
 */
class NotificationsRepositoryTest extends ModuleTestCase
{
    private NotificationsRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        // users necessaria per il LEFT JOIN in getUnreadForUser() e getPagedForUser()
        $this->createUsersTable();

        $this->migrate("
            CREATE TABLE notifications (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL,
                title      TEXT    NOT NULL,
                body       TEXT,
                type       TEXT    DEFAULT 'info',
                link       TEXT,
                read_at    TEXT    DEFAULT NULL,
                created_by INTEGER,
                created_at TEXT    DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->repo = new NotificationsRepository();
    }

    // -------------------------------------------------------------------------
    // Helpers privati
    // -------------------------------------------------------------------------

    private function createNotification(int $userId, array $extra = []): int
    {
        return $this->insertRow('notifications', array_merge([
            'user_id' => $userId,
            'title'   => 'Test notification',
            'type'    => 'info',
        ], $extra));
    }

    // -------------------------------------------------------------------------
    // CRUD base
    // -------------------------------------------------------------------------

    public function testCreateAndFind(): void
    {
        $id  = $this->repo->create([
            'user_id' => 1,
            'title'   => 'Notifica di prova',
            'type'    => 'success',
        ]);
        $row = $this->repo->find($id);

        $this->assertNotNull($row);
        $this->assertSame('Notifica di prova', $row['title']);
        $this->assertSame('success', $row['type']);
        $this->assertNull($row['read_at']);
    }

    // -------------------------------------------------------------------------
    // getUnreadCountForUser()
    // -------------------------------------------------------------------------

    public function testGetUnreadCountForUser(): void
    {
        // 3 non lette + 1 già letta per user 1
        $this->createNotification(1);
        $this->createNotification(1);
        $this->createNotification(1);
        $this->createNotification(1, ['read_at' => '2026-01-01 10:00:00']);

        // 1 non letta per user 2 — non deve influire sul conteggio di user 1
        $this->createNotification(2);

        $this->assertSame(3, $this->repo->getUnreadCountForUser(1));
        $this->assertSame(1, $this->repo->getUnreadCountForUser(2));
    }

    public function testGetUnreadCountReturnsZeroForCleanUser(): void
    {
        $this->assertSame(0, $this->repo->getUnreadCountForUser(99));
    }

    // -------------------------------------------------------------------------
    // markAsRead()
    // -------------------------------------------------------------------------

    public function testMarkAsRead(): void
    {
        $id = $this->createNotification(1);

        // Prima della chiamata: read_at è NULL
        $this->assertNull($this->repo->find($id)['read_at']);

        $this->repo->markAsRead($id, 1);

        // Dopo la chiamata: read_at è valorizzato
        $this->assertNotNull($this->repo->find($id)['read_at']);
    }

    public function testMarkAsReadDoesNotAffectOtherUser(): void
    {
        $id = $this->createNotification(1);

        // User 2 tenta di marcare la notifica di user 1
        $this->repo->markAsRead($id, 2);

        // read_at deve restare NULL
        $this->assertNull($this->repo->find($id)['read_at']);
    }

    public function testMarkAsReadIsIdempotent(): void
    {
        $id = $this->createNotification(1);
        $this->repo->markAsRead($id, 1);
        $firstReadAt = $this->repo->find($id)['read_at'];

        // Seconda chiamata — la query filtra su read_at IS NULL, non cambia nulla
        $this->repo->markAsRead($id, 1);
        $secondReadAt = $this->repo->find($id)['read_at'];

        $this->assertSame($firstReadAt, $secondReadAt);
    }

    // -------------------------------------------------------------------------
    // markAllAsRead()
    // -------------------------------------------------------------------------

    public function testMarkAllAsRead(): void
    {
        $this->createNotification(1);
        $this->createNotification(1);
        $this->createNotification(1);

        $this->assertSame(3, $this->repo->getUnreadCountForUser(1));

        $this->repo->markAllAsRead(1);

        $this->assertSame(0, $this->repo->getUnreadCountForUser(1));
    }

    public function testMarkAllAsReadDoesNotAffectOtherUsers(): void
    {
        $this->createNotification(1);
        $this->createNotification(2);

        $this->repo->markAllAsRead(1);

        // User 2 deve ancora avere la sua notifica non letta
        $this->assertSame(1, $this->repo->getUnreadCountForUser(2));
    }

    public function testMarkManyAsReadUpdatesOnlySelectedUnreadNotificationsForUser(): void
    {
        $id1 = $this->createNotification(1);
        $id2 = $this->createNotification(1);
        $id3 = $this->createNotification(1, ['read_at' => '2026-01-01 10:00:00']);
        $id4 = $this->createNotification(2);

        $updated = $this->repo->markManyAsRead([$id1, $id3, $id4], 1);

        $this->assertSame(1, $updated);
        $this->assertNotNull($this->repo->find($id1)['read_at']);
        $this->assertNull($this->repo->find($id2)['read_at']);
        $this->assertSame('2026-01-01 10:00:00', $this->repo->find($id3)['read_at']);
        $this->assertNull($this->repo->find($id4)['read_at']);
    }

    // -------------------------------------------------------------------------
    // markAsReadByLink()
    // -------------------------------------------------------------------------

    public function testMarkAsReadByLink(): void
    {
        $this->createNotification(1, ['link' => '/items/1']);
        $this->createNotification(1, ['link' => '/items/1']);
        $this->createNotification(1, ['link' => '/other']);

        $marked = $this->repo->markAsReadByLink(1, '/items/1');

        $this->assertSame(2, $marked);
        // Le due con link /items/1 sono lette, quella con /other no
        $this->assertSame(1, $this->repo->getUnreadCountForUser(1));
    }

    // -------------------------------------------------------------------------
    // deleteForUser()
    // -------------------------------------------------------------------------

    public function testDeleteForUser(): void
    {
        $id1 = $this->createNotification(1);
        $id2 = $this->createNotification(1);

        $this->repo->deleteForUser($id1, 1);

        $this->assertNull($this->repo->find($id1));
        $this->assertNotNull($this->repo->find($id2));
    }

    public function testDeleteForUserDoesNotDeleteOtherUsersNotification(): void
    {
        $id = $this->createNotification(1);

        // User 2 tenta di eliminare la notifica di user 1
        $this->repo->deleteForUser($id, 2);

        $this->assertNotNull($this->repo->find($id));
    }

    public function testDeleteManyForUserDeletesOnlySelectedNotificationsForUser(): void
    {
        $id1 = $this->createNotification(1);
        $id2 = $this->createNotification(1);
        $id3 = $this->createNotification(1);
        $id4 = $this->createNotification(2);

        $deleted = $this->repo->deleteManyForUser([$id1, $id3, $id4], 1);

        $this->assertSame(2, $deleted);
        $this->assertNull($this->repo->find($id1));
        $this->assertNotNull($this->repo->find($id2));
        $this->assertNull($this->repo->find($id3));
        $this->assertNotNull($this->repo->find($id4));
    }

    // -------------------------------------------------------------------------
    // getPagedForUser()
    // -------------------------------------------------------------------------

    public function testGetPagedForUser(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createNotification(1, ['title' => "Notifica {$i}"]);
        }

        $result = $this->repo->getPagedForUser(1, 2, 2);

        $this->assertSame(5, $result['total']);
        $this->assertSame(2, $result['page']);
        $this->assertCount(2, $result['items']);
        $this->assertSame(3, $result['lastPage']);
    }

    public function testGetPagedForUserFiltersUnread(): void
    {
        $this->createNotification(1);
        $this->createNotification(1);
        $this->createNotification(1, ['read_at' => '2026-01-01 10:00:00']);

        $result = $this->repo->getPagedForUser(1, 1, 20, 'unread');

        $this->assertSame(2, $result['total']);
    }

    public function testGetPagedForUserFiltersRead(): void
    {
        $this->createNotification(1);
        $this->createNotification(1, ['read_at' => '2026-01-01 10:00:00']);
        $this->createNotification(1, ['read_at' => '2026-01-02 10:00:00']);

        $result = $this->repo->getPagedForUser(1, 1, 20, 'read');

        $this->assertSame(2, $result['total']);
    }
}
