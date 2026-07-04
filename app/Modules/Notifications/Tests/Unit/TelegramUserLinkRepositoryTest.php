<?php

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Repositories\TelegramUserLinkRepository;
use Tests\ModuleTestCase;

class TelegramUserLinkRepositoryTest extends ModuleTestCase
{
    private TelegramUserLinkRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate('
            CREATE TABLE telegram_user_links (
                id                INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id           INTEGER NOT NULL,
                bot_id            INTEGER NOT NULL,
                telegram_user_id  TEXT NULL,
                chat_id           TEXT NULL,
                telegram_username TEXT NULL,
                link_token        TEXT NOT NULL,
                status            TEXT NOT NULL DEFAULT "pending",
                linked_at         TEXT NULL,
                last_seen_at      TEXT NULL,
                metadata_json     TEXT NULL,
                created_at        TEXT NULL,
                updated_at        TEXT NULL
            );
        ');
        $this->repo = new TelegramUserLinkRepository();
    }

    public function testEnsurePendingLinkCreatesNewPending(): void
    {
        $link = $this->repo->ensurePendingLink(1, 7, 'tok-abc');

        $this->assertSame('pending', $link['status']);
        $this->assertSame('tok-abc', $link['link_token']);
        $this->assertSame(7, (int) $link['bot_id']);
    }

    public function testEnsurePendingLinkReusesExistingPendingRow(): void
    {
        $first = $this->repo->ensurePendingLink(1, 7, 'tok-1');
        $second = $this->repo->ensurePendingLink(1, 7, 'tok-2');

        $this->assertSame((int) $first['id'], (int) $second['id'], 'Riusa la riga pending esistente');
        $this->assertSame('tok-2', $second['link_token'], 'Aggiorna il token');

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM telegram_user_links')->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testEnsurePendingLinkReturnsExistingLinkWhenAlreadyLinkedToSameBot(): void
    {
        $link = $this->repo->ensurePendingLink(1, 7, 'tok-1');
        $this->repo->markLinked((int) $link['id'], ['telegram_user_id' => '999', 'chat_id' => '888']);

        $result = $this->repo->ensurePendingLink(1, 7, 'tok-new');
        $this->assertSame('linked', $result['status'], 'Se già collegato allo stesso bot, ritorna il link esistente');
    }

    public function testMarkLinkedSetsStatusAndChatData(): void
    {
        $link = $this->repo->ensurePendingLink(1, 7, 'tok');
        $this->repo->markLinked((int) $link['id'], [
            'telegram_user_id' => '12345',
            'chat_id'          => '67890',
            'telegram_username' => 'mario',
        ]);

        $row = $this->repo->findLinkedByUserId(1);
        $this->assertNotNull($row);
        $this->assertSame('12345', $row['telegram_user_id']);
        $this->assertSame('mario', $row['telegram_username']);
        $this->assertNotNull($row['linked_at']);
    }

    public function testFindByTokenAndByUserAndBot(): void
    {
        $link = $this->repo->ensurePendingLink(1, 7, 'unique-tok');

        $this->assertSame((int) $link['id'], (int) $this->repo->findByToken(7, 'unique-tok')['id']);
        $this->assertNull($this->repo->findByToken(7, 'wrong'));
        $this->assertSame((int) $link['id'], (int) $this->repo->findByUserAndBot(1, 7)['id']);
        $this->assertNull($this->repo->findByUserAndBot(1, 999));
    }

    public function testRevokeByUserIdRevokesPendingAndLinked(): void
    {
        $this->repo->ensurePendingLink(1, 7, 'tok');

        $this->assertTrue($this->repo->revokeByUserId(1));
        $this->assertNull($this->repo->findLinkedByUserId(1));
        $this->assertNull($this->repo->findPendingByUserId(1));

        // Niente da revocare la seconda volta.
        $this->assertFalse($this->repo->revokeByUserId(1));
    }
}
