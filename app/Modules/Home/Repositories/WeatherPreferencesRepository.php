<?php

declare(strict_types=1);

namespace App\Modules\Home\Repositories;

use App\Repositories\BaseRepository;
use PDO;

/**
 * Località meteo preferita per utente (tabella user_weather_preferences).
 * Un record per utente (vincolo univoco su user_id).
 */
class WeatherPreferencesRepository extends BaseRepository
{
    protected string $table = 'user_weather_preferences';

    public function findByUser(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM user_weather_preferences WHERE user_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function upsertForUser(int $userId, array $data): void
    {
        if ($this->findByUser($userId) !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE user_weather_preferences
                    SET name = ?, admin1 = ?, country = ?, country_code = ?,
                        latitude = ?, longitude = ?, timezone = ?, updated_at = NOW()
                  WHERE user_id = ?'
            );
            $stmt->execute([
                $data['name'], $data['admin1'], $data['country'], $data['country_code'],
                $data['latitude'], $data['longitude'], $data['timezone'], $userId,
            ]);
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO user_weather_preferences
                (user_id, name, admin1, country, country_code, latitude, longitude, timezone, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $userId, $data['name'], $data['admin1'], $data['country'], $data['country_code'],
            $data['latitude'], $data['longitude'], $data['timezone'],
        ]);
    }
}
