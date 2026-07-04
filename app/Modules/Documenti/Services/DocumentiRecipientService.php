<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Services;

/**
 * Lookup destinatari notifiche (users / permissions) sulla PDO condivisa.
 * Cache per-request per evitare query ripetute.
 */
class DocumentiRecipientService
{
    /** @var array<string, array> */
    private array $cache = [];

    /**
     * Restituisce gli ID degli utenti che hanno il permesso indicato.
     *
     * @return int[]
     */
    public function usersWithPermission(string $slug): array
    {
        if (isset($this->cache[$slug])) {
            return $this->cache[$slug];
        }

        try {
            $pdo  = app(\PDO::class);
            $stmt = $pdo->prepare(
                'SELECT DISTINCT u.id
                 FROM users u
                 JOIN user_role ur ON ur.user_id = u.id
                 JOIN role_permission rp ON rp.role_id = ur.role_id
                 JOIN permissions p ON p.id = rp.permission_id
                 WHERE p.slug = ? AND u.is_active = 1'
            );
            $stmt->execute([$slug]);
            $ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable) {
            $ids = [];
        }

        $this->cache[$slug] = array_map('intval', $ids);
        return $this->cache[$slug];
    }

    /**
     * Mappa user_id => name leggibile.
     *
     * @param  int[] $ids
     * @return array<int,string>
     */
    public function displayNamesByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $i): bool => $i > 0
        )));
        if (empty($ids)) {
            return [];
        }

        try {
            $pdo  = app(\PDO::class);
            $ph   = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id IN ({$ph})");
            $stmt->execute($ids);
            $out = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $out[(int) $row['id']] = (string) $row['name'];
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Risolve i destinatari extra da JSON (array di user_id).
     *
     * @param  string|null $json
     * @return int[]
     */
    public function resolveExtra(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $arr = json_decode($json, true);
        if (!is_array($arr)) {
            return [];
        }
        return array_map('intval', array_filter($arr, 'is_numeric'));
    }
}
