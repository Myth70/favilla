<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Providers;

use App\Contracts\SearchableModule;
use PDO;

class ContactsSearchProvider implements SearchableModule
{
    public function search(string $query, int $userId, int $limit = 5): array
    {
        if (!has_permission('contacts.view')) {
            return [];
        }

        $pdo  = app(PDO::class);
        $like = '%' . $query . '%';

        // Visibilità: contatti propri + contatti condivisi con uno dei ruoli
        // dell'utente. Gli slug dei ruoli sono già in sessione.
        $roleSlugs = $_SESSION['user_roles'] ?? [];
        $roleSlugs = is_array($roleSlugs) ? array_values(array_filter(array_map('strval', $roleSlugs))) : [];

        $visClauses = ['c.user_id = ?'];
        $visParams  = [$userId];

        if (!empty($roleSlugs)) {
            $placeholders = implode(',', array_fill(0, count($roleSlugs), '?'));
            $visClauses[] = "EXISTS (SELECT 1 FROM contact_shares s
                                     INNER JOIN roles r ON r.id = s.role_id
                                     WHERE s.contatto_id = c.id
                                       AND r.slug IN ({$placeholders}))";
            $visParams    = array_merge($visParams, $roleSlugs);
        }

        $visSql = '(' . implode(' OR ', $visClauses) . ')';

        $stmt = $pdo->prepare(
            "SELECT c.id, c.nome, c.cognome, c.azienda, c.email, c.telefono,
                    cat.nome AS categoria_nome,
                    (c.user_id = ?) AS is_owner
             FROM contacts c
             LEFT JOIN contact_categories cat ON cat.id = c.categoria_id
             WHERE {$visSql}
               AND (c.nome LIKE ? OR c.cognome LIKE ? OR c.azienda LIKE ?
                    OR c.email LIKE ? OR c.telefono LIKE ?)
             ORDER BY c.nome ASC, c.cognome ASC
             LIMIT ?"
        );
        $stmt->execute(array_merge(
            [$userId],                                  // is_owner SELECT
            $visParams,                                 // user_id + role slugs
            [$like, $like, $like, $like, $like, $limit]  // LIKE + LIMIT
        ));

        $results = [];
        foreach ($stmt->fetchAll() as $row) {
            $name     = trim(($row['nome'] ?? '') . ' ' . ($row['cognome'] ?? ''));
            $subtitle = array_filter([
                $row['azienda'] ?? null,
                $row['email'] ?? null,
            ]);
            $results[] = [
                'title'    => $name ?: ($row['email'] ?? ''),
                'subtitle' => implode(' — ', $subtitle) ?: ($row['categoria_nome'] ?? 'Contatto'),
                'url'      => route('contacts.show', ['id' => $row['id']]),
                'icon'     => 'fa-address-book',
                'badge'    => empty($row['is_owner']) ? 'Condiviso' : null,
            ];
        }

        return $results;
    }

    public function getSearchLabel(): string
    {
        return 'Contatti';
    }

    public function getSearchIcon(): string
    {
        return 'fa-address-book';
    }
}
