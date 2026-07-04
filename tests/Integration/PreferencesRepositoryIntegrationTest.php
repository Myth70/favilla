<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Home\Repositories\PreferencesRepository;

/**
 * upsert() usa ON DUPLICATE KEY UPDATE + VALUES() (MySQL-only): va verificato
 * sul dialetto reale. Testa insert iniziale e update parziale dello stesso utente.
 */
class PreferencesRepositoryIntegrationTest extends DatabaseIntegrationTestCase
{
    public function testUpsertInsertsThenUpdatesSameRow(): void
    {
        $userId = $this->insertRow('users', [
            'name' => 'U', 'email' => 'u@example.test', 'username' => 'u_pref', 'password' => 'x',
        ]);
        $repo = new PreferencesRepository();

        // Primo upsert: inserisce.
        $repo->upsert($userId, ['theme' => 'dark', 'primary_color' => '#000000']);
        $row = $repo->getByUserId($userId);
        $this->assertSame('dark', $row['theme']);

        // Secondo upsert: aggiorna la stessa riga (user_id è UNIQUE), niente duplicato.
        $repo->upsert($userId, ['theme' => 'light']);
        $row = $repo->getByUserId($userId);
        $this->assertSame('light', $row['theme']);
        $this->assertSame('#000000', $row['primary_color'], 'I campi non passati restano invariati');

        $count = (int) self::$pdo->query(
            "SELECT COUNT(*) FROM user_preferences WHERE user_id = {$userId}"
        )->fetchColumn();
        $this->assertSame(1, $count);
    }
}
