<?php

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Repositories\TelegramBotRepository;
use Tests\ModuleTestCase;

class TelegramBotRepositoryTest extends ModuleTestCase
{
    private TelegramBotRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate('
            CREATE TABLE telegram_bots (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT NOT NULL,
                bot_token   TEXT NOT NULL DEFAULT "",
                is_enabled  INTEGER NOT NULL DEFAULT 1,
                is_default  INTEGER NOT NULL DEFAULT 0,
                created_by  INTEGER NULL,
                created_at  TEXT NULL,
                updated_at  TEXT NULL
            );
        ');
        $this->repo = new TelegramBotRepository();
    }

    private function addBot(string $name, int $enabled, int $default): int
    {
        return $this->insertRow('telegram_bots', [
            'name' => $name, 'bot_token' => 't', 'is_enabled' => $enabled, 'is_default' => $default,
        ]);
    }

    public function testFindDefaultEnabledPrefersDefaultThenEnabled(): void
    {
        $this->addBot('non-default', 1, 0);
        $def = $this->addBot('default', 1, 1);

        $row = $this->repo->findDefaultEnabled();
        $this->assertSame($def, (int) $row['id']);
    }

    public function testFindDefaultEnabledIgnoresDisabled(): void
    {
        $this->addBot('disabled-default', 0, 1);
        $enabled = $this->addBot('enabled', 1, 0);

        $row = $this->repo->findDefaultEnabled();
        $this->assertSame($enabled, (int) $row['id']);
    }

    public function testFindDefaultReturnsDefaultRegardlessOfEnabled(): void
    {
        $this->addBot('enabled', 1, 0);
        $def = $this->addBot('default-disabled', 0, 1);

        $this->assertSame($def, (int) $this->repo->findDefault()['id']);
    }

    public function testFindAllOrdersDefaultThenEnabled(): void
    {
        $plain = $this->addBot('plain', 0, 0);
        $def = $this->addBot('def', 1, 1);
        $enabled = $this->addBot('enabled', 1, 0);

        $ids = array_map('intval', array_column($this->repo->findAll(), 'id'));
        $this->assertSame([$def, $enabled, $plain], $ids);
    }

    public function testClearDefaultUnsetsAllOrAllButException(): void
    {
        $a = $this->addBot('a', 1, 1);
        $b = $this->addBot('b', 1, 1);

        // Mantieni $b come default, azzera gli altri.
        $this->repo->clearDefault($b);
        $this->assertSame(0, (int) $this->repo->find($a)['is_default']);
        $this->assertSame(1, (int) $this->repo->find($b)['is_default']);

        // Azzera tutti.
        $this->repo->clearDefault();
        $this->assertSame(0, (int) $this->repo->find($b)['is_default']);
    }
}
