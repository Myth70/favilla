<?php

declare(strict_types=1);

namespace App\Modules\Teams\Tests\Unit;

use App\Modules\Teams\Services\GroupPanelService;
use Tests\ModuleTestCase;

/**
 * Integration test (SQLite in-memory) per GroupPanelService.
 *
 * Copre:
 *  - Auth gate: non-membri ricevono null da tutti gli endpoint
 *  - Header: member_count + message_count corretti, esclude system
 *  - Media: filtra image/video, ordine DESC, cursor before_id, hasMore
 *  - Files: esclude image/video, filtri kind (docs/archives/audio/all)
 *  - Links: regex sui body, dedup, hasMore con items vuoti (pre-filter LIKE)
 */
class GroupPanelServiceTest extends ModuleTestCase
{
    private GroupPanelService $service;

    private const USER_MEMBER     = 1;
    private const USER_OTHER      = 2;
    private const USER_OUTSIDER   = 3;
    private const CONV_GROUP_ID   = 100;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                avatar_path TEXT NULL
            );
            CREATE TABLE teams_conversations (
                id INTEGER PRIMARY KEY,
                type TEXT NOT NULL DEFAULT "group",
                name TEXT NULL,
                description TEXT NULL,
                avatar_path TEXT NULL,
                created_by INTEGER NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                archived_at TEXT NULL
            );
            CREATE TABLE teams_conversation_members (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                conversation_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                role TEXT NOT NULL DEFAULT "member",
                joined_at TEXT DEFAULT CURRENT_TIMESTAMP,
                last_read_at TEXT NULL,
                notifications_muted INTEGER NOT NULL DEFAULT 0,
                hidden_at TEXT NULL,
                left_at TEXT NULL
            );
            CREATE TABLE teams_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                conversation_id INTEGER NOT NULL,
                user_id INTEGER NULL,
                body TEXT NOT NULL,
                type TEXT NOT NULL DEFAULT "text",
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at TEXT NULL
            );
            CREATE TABLE teams_message_attachments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                message_id INTEGER NOT NULL,
                original_name TEXT NOT NULL,
                stored_name TEXT NOT NULL,
                mime_type TEXT NOT NULL,
                size_bytes INTEGER NOT NULL DEFAULT 0,
                extension TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');

        // Utenti
        $this->pdo->exec("INSERT INTO users (id, name) VALUES
            (1, 'Alice'), (2, 'Bob'), (3, 'Carlo')");

        // Gruppo con Alice (creator/admin) e Bob (member). Carlo è outsider.
        $this->insertRow('teams_conversations', [
            'id'         => self::CONV_GROUP_ID,
            'type'       => 'group',
            'name'       => 'Team Alpha',
            'description' => 'Discussione interna',
            'created_by' => self::USER_MEMBER,
            'created_at' => '2024-01-15 10:00:00',
        ]);
        $this->insertRow('teams_conversation_members', [
            'conversation_id' => self::CONV_GROUP_ID,
            'user_id' => self::USER_MEMBER,
            'role' => 'admin',
        ]);
        $this->insertRow('teams_conversation_members', [
            'conversation_id' => self::CONV_GROUP_ID,
            'user_id' => self::USER_OTHER,
            'role' => 'member',
        ]);

        $this->service = new GroupPanelService();
    }

    /** Helper: inserisce un messaggio. $userId=null per messaggi di sistema. */
    private function insertMessage(?int $userId, string $body, string $type = 'text', ?string $deletedAt = null): int
    {
        return $this->insertRow('teams_messages', [
            'conversation_id' => self::CONV_GROUP_ID,
            'user_id'         => $userId,
            'body'            => $body,
            'type'            => $type,
            'deleted_at'      => $deletedAt,
        ]);
    }

    /** Helper: inserisce un allegato. */
    private function insertAttachment(int $messageId, string $mime, string $name, string $ext = '', int $size = 1000): int
    {
        return $this->insertRow('teams_message_attachments', [
            'message_id'    => $messageId,
            'original_name' => $name,
            'stored_name'   => 'stored_' . bin2hex(random_bytes(4)) . ($ext ? '.' . $ext : ''),
            'mime_type'     => $mime,
            'size_bytes'    => $size,
            'extension'     => $ext,
        ]);
    }

    // ── Auth gate ────────────────────────────────────────────────────────────

    public function testHeaderReturnsNullForOutsider(): void
    {
        $this->assertNull(
            $this->service->getHeaderData(self::CONV_GROUP_ID, self::USER_OUTSIDER)
        );
    }

    public function testMediaPageReturnsNullForOutsider(): void
    {
        $this->assertNull(
            $this->service->getMediaPage(self::CONV_GROUP_ID, self::USER_OUTSIDER)
        );
    }

    public function testFilesPageReturnsNullForOutsider(): void
    {
        $this->assertNull(
            $this->service->getFilesPage(self::CONV_GROUP_ID, self::USER_OUTSIDER)
        );
    }

    public function testLinksPageReturnsNullForOutsider(): void
    {
        $this->assertNull(
            $this->service->getLinksPage(self::CONV_GROUP_ID, self::USER_OUTSIDER)
        );
    }

    // ── Header ───────────────────────────────────────────────────────────────

    public function testHeaderReturnsBasicDataAndCounters(): void
    {
        $this->insertMessage(self::USER_MEMBER, 'Ciao!');
        $this->insertMessage(self::USER_OTHER, 'Salve');

        $info = $this->service->getHeaderData(self::CONV_GROUP_ID, self::USER_MEMBER);

        $this->assertNotNull($info);
        $this->assertSame('Team Alpha', $info['name']);
        $this->assertSame('Discussione interna', $info['description']);
        $this->assertSame(2, (int) $info['member_count']);
        $this->assertSame(2, (int) $info['message_count']);
        $this->assertSame('Alice', $info['creator_name']);
        $this->assertSame('admin', $info['my_role']);
    }

    public function testHeaderMessageCountExcludesSystemAndDeleted(): void
    {
        $this->insertMessage(self::USER_MEMBER, 'reale 1');
        $this->insertMessage(self::USER_MEMBER, 'reale 2');
        $this->insertMessage(null, 'Bob ha lasciato il gruppo', 'system'); // system
        $this->insertMessage(self::USER_MEMBER, 'cancellato', 'text', '2024-06-01 12:00:00'); // deleted

        $info = $this->service->getHeaderData(self::CONV_GROUP_ID, self::USER_MEMBER);
        $this->assertSame(2, (int) $info['message_count']);
    }

    // ── Media ────────────────────────────────────────────────────────────────

    public function testMediaPageReturnsOnlyImagesAndVideos(): void
    {
        $msg1 = $this->insertMessage(self::USER_MEMBER, '[immagine]');
        $this->insertAttachment($msg1, 'image/png', 'foto.png', 'png');
        $msg2 = $this->insertMessage(self::USER_OTHER, '[video]');
        $this->insertAttachment($msg2, 'video/mp4', 'clip.mp4', 'mp4');
        $msg3 = $this->insertMessage(self::USER_MEMBER, '[pdf]');
        $this->insertAttachment($msg3, 'application/pdf', 'report.pdf', 'pdf');

        $page = $this->service->getMediaPage(self::CONV_GROUP_ID, self::USER_MEMBER);

        $this->assertNotNull($page);
        $this->assertCount(2, $page['items']);
        $mimes = array_column($page['items'], 'mime_type');
        sort($mimes);
        $this->assertSame(['image/png', 'video/mp4'], $mimes);
    }

    public function testMediaPageOrdersDescendingByAttachmentId(): void
    {
        // Inserisco 3 immagini in sequenza, l'ultimo inserito deve essere primo
        $msgs = [];
        for ($i = 1; $i <= 3; $i++) {
            $m = $this->insertMessage(self::USER_MEMBER, "img {$i}");
            $msgs[] = $this->insertAttachment($m, 'image/jpeg', "img{$i}.jpg", 'jpg');
        }

        $page = $this->service->getMediaPage(self::CONV_GROUP_ID, self::USER_MEMBER);

        $ids = array_map(static fn (array $r): int => (int) $r['id'], $page['items']);
        $this->assertSame([$msgs[2], $msgs[1], $msgs[0]], $ids);
    }

    public function testMediaPageHasMoreFlagAndCursor(): void
    {
        // Inserisco MEDIA_PAGE_SIZE+2 immagini per forzare hasMore=true
        $limit = GroupPanelService::MEDIA_PAGE_SIZE;
        for ($i = 0; $i < $limit + 2; $i++) {
            $m = $this->insertMessage(self::USER_MEMBER, "img {$i}");
            $this->insertAttachment($m, 'image/jpeg', "i{$i}.jpg", 'jpg');
        }

        $page = $this->service->getMediaPage(self::CONV_GROUP_ID, self::USER_MEMBER);

        $this->assertCount($limit, $page['items']);
        $this->assertTrue($page['hasMore']);
        $this->assertSame((int) $page['items'][$limit - 1]['id'], $page['nextBefore']);
        $this->assertSame($limit + 2, $page['total']);
    }

    public function testMediaPageCursorPagination(): void
    {
        // 5 elementi, paginazione cursor
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $m = $this->insertMessage(self::USER_MEMBER, "img {$i}");
            $ids[] = $this->insertAttachment($m, 'image/jpeg', "img{$i}.jpg", 'jpg');
        }

        $page = $this->service->getMediaPage(self::CONV_GROUP_ID, self::USER_MEMBER, $ids[2]);
        $returnedIds = array_map(static fn (array $r): int => (int) $r['id'], $page['items']);

        // before_id=ids[2] → solo elementi con id < ids[2]
        $this->assertSame([$ids[1], $ids[0]], $returnedIds);
    }

    public function testMediaPageExcludesAttachmentsOfDeletedMessages(): void
    {
        $msg = $this->insertMessage(self::USER_MEMBER, '[img deleted]', 'text', '2024-06-01 12:00:00');
        $this->insertAttachment($msg, 'image/png', 'gone.png', 'png');

        $page = $this->service->getMediaPage(self::CONV_GROUP_ID, self::USER_MEMBER);
        $this->assertSame([], $page['items']);
    }

    // ── Files ────────────────────────────────────────────────────────────────

    public function testFilesPageExcludesImagesAndVideos(): void
    {
        $m1 = $this->insertMessage(self::USER_MEMBER, '[pdf]');
        $this->insertAttachment($m1, 'application/pdf', 'doc.pdf', 'pdf');
        $m2 = $this->insertMessage(self::USER_MEMBER, '[img]');
        $this->insertAttachment($m2, 'image/png', 'foto.png', 'png');
        $m3 = $this->insertMessage(self::USER_MEMBER, '[video]');
        $this->insertAttachment($m3, 'video/mp4', 'clip.mp4', 'mp4');

        $page = $this->service->getFilesPage(self::CONV_GROUP_ID, self::USER_MEMBER);

        $this->assertCount(1, $page['items']);
        $this->assertSame('application/pdf', $page['items'][0]['mime_type']);
    }

    public function testFilesPageKindFilterDocsOnly(): void
    {
        $m1 = $this->insertMessage(self::USER_MEMBER, '[pdf]');
        $this->insertAttachment($m1, 'application/pdf', 'doc.pdf', 'pdf');
        $m2 = $this->insertMessage(self::USER_MEMBER, '[zip]');
        $this->insertAttachment($m2, 'application/zip', 'arc.zip', 'zip');
        $m3 = $this->insertMessage(self::USER_MEMBER, '[mp3]');
        $this->insertAttachment($m3, 'audio/mpeg', 'song.mp3', 'mp3');

        $page = $this->service->getFilesPage(self::CONV_GROUP_ID, self::USER_MEMBER, null, 'docs');
        $this->assertCount(1, $page['items']);
        $this->assertSame('doc.pdf', $page['items'][0]['original_name']);
        $this->assertSame('docs', $page['kind']);
    }

    public function testFilesPageKindFilterArchivesOnly(): void
    {
        $m1 = $this->insertMessage(self::USER_MEMBER, '[pdf]');
        $this->insertAttachment($m1, 'application/pdf', 'doc.pdf', 'pdf');
        $m2 = $this->insertMessage(self::USER_MEMBER, '[zip]');
        $this->insertAttachment($m2, 'application/zip', 'arc.zip', 'zip');
        $m3 = $this->insertMessage(self::USER_MEMBER, '[rar]');
        $this->insertAttachment($m3, 'application/x-rar-compressed', 'arc.rar', 'rar');

        $page = $this->service->getFilesPage(self::CONV_GROUP_ID, self::USER_MEMBER, null, 'archives');
        $this->assertCount(2, $page['items']);
    }

    public function testFilesPageKindFilterAudioOnly(): void
    {
        $m1 = $this->insertMessage(self::USER_MEMBER, '[mp3]');
        $this->insertAttachment($m1, 'audio/mpeg', 'song.mp3', 'mp3');
        $m2 = $this->insertMessage(self::USER_MEMBER, '[pdf]');
        $this->insertAttachment($m2, 'application/pdf', 'doc.pdf', 'pdf');

        $page = $this->service->getFilesPage(self::CONV_GROUP_ID, self::USER_MEMBER, null, 'audio');
        $this->assertCount(1, $page['items']);
        $this->assertSame('song.mp3', $page['items'][0]['original_name']);
    }

    public function testFilesPageInvalidKindFallsBackToAll(): void
    {
        $m1 = $this->insertMessage(self::USER_MEMBER, '[pdf]');
        $this->insertAttachment($m1, 'application/pdf', 'doc.pdf', 'pdf');

        $page = $this->service->getFilesPage(self::CONV_GROUP_ID, self::USER_MEMBER, null, 'invalid-kind');
        $this->assertSame('all', $page['kind']);
        $this->assertCount(1, $page['items']);
    }

    // ── Links ────────────────────────────────────────────────────────────────

    public function testLinksPageExtractsUrlsFromMessageBodies(): void
    {
        $this->insertMessage(self::USER_MEMBER, 'vedi https://example.com per dettagli');
        $this->insertMessage(self::USER_OTHER, 'anche http://foo.org va bene');
        $this->insertMessage(self::USER_MEMBER, 'nessun link qui');

        $page = $this->service->getLinksPage(self::CONV_GROUP_ID, self::USER_MEMBER);

        $this->assertNotNull($page);
        $this->assertCount(2, $page['items']);
        $urls = array_column($page['items'], 'url');
        // Più recenti per primo (DESC su message id)
        $this->assertSame(['http://foo.org', 'https://example.com'], $urls);
    }

    public function testLinksPageExtractsMultipleUrlsFromSingleMessage(): void
    {
        $this->insertMessage(self::USER_MEMBER, 'vedi https://a.com e https://b.com');

        $page = $this->service->getLinksPage(self::CONV_GROUP_ID, self::USER_MEMBER);

        $this->assertCount(2, $page['items']);
        $urls = array_column($page['items'], 'url');
        sort($urls);
        $this->assertSame(['https://a.com', 'https://b.com'], $urls);
    }

    public function testLinksPageReturnsEmptyForBodyWithFalsePositiveLike(): void
    {
        // Pre-filter LIKE matcha "info@www.foo" ma TeamsLinkExtractor restituisce
        // (forse) URL standalone "www.foo" → comunque non email completa.
        $this->insertMessage(self::USER_MEMBER, 'contatta info@example.com per supporto');

        $page = $this->service->getLinksPage(self::CONV_GROUP_ID, self::USER_MEMBER);

        // Nessun URL valido perché "example.com" non ha schema né "www."
        $this->assertSame([], $page['items']);
    }

    public function testLinksPageIncludesAuthorInfo(): void
    {
        $this->insertMessage(self::USER_OTHER, 'guarda https://bob-link.com');

        $page = $this->service->getLinksPage(self::CONV_GROUP_ID, self::USER_MEMBER);

        $this->assertCount(1, $page['items']);
        $this->assertSame('Bob', $page['items'][0]['user_name']);
        $this->assertSame(self::USER_OTHER, $page['items'][0]['user_id']);
        $this->assertSame('bob-link.com', $page['items'][0]['domain']);
    }

    public function testLinksPageExcludesSystemAndDeletedMessages(): void
    {
        $this->insertMessage(self::USER_MEMBER, 'reale https://keep.com');
        $this->insertMessage(null, 'system https://hide-sys.com', 'system');
        $this->insertMessage(self::USER_MEMBER, 'eliminato https://hide-del.com', 'text', '2024-06-01 12:00:00');

        $page = $this->service->getLinksPage(self::CONV_GROUP_ID, self::USER_MEMBER);

        $urls = array_column($page['items'], 'url');
        $this->assertSame(['https://keep.com'], $urls);
    }

    public function testLinksPageHasMoreFlag(): void
    {
        $limit = GroupPanelService::LINKS_PAGE_SIZE;
        for ($i = 0; $i < $limit + 1; $i++) {
            $this->insertMessage(self::USER_MEMBER, "msg {$i} https://x{$i}.com");
        }

        $page = $this->service->getLinksPage(self::CONV_GROUP_ID, self::USER_MEMBER);

        $this->assertTrue($page['hasMore']);
        $this->assertNotNull($page['nextBefore']);
    }
}
