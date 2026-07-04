<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Providers;

use App\Contracts\SearchableModule;

class DocumentiSearchProvider implements SearchableModule
{
    public function search(string $query, int $userId, int $limit = 5): array
    {
        if (!has_permission('documenti.access')) {
            return [];
        }

        try {
            $pdo     = app(\PDO::class);
            $like    = '%' . $query . '%';
            $isAdmin = has_permission('documenti.admin');

            // Visibility coerente con DocumentoRepository::listPaginated(): stati pubblici
            // visibili a tutti, bozza/rifiutato solo all'owner. Senza questo filtro un utente
            // qualsiasi con documenti.access potrebbe scoprire titoli di bozze/documenti
            // rifiutati altrui tramite la ricerca globale (IDOR via ricerca).
            $where  = ['d.deleted_at IS NULL', '(d.titolo LIKE ? OR d.descrizione LIKE ? OR d.tag LIKE ?)'];
            $params = [$like, $like, $like];
            if (!$isAdmin) {
                $where[]  = '(d.stato NOT IN (\'bozza\',\'rifiutato\') OR d.owner_user_id = ?)';
                $params[] = $userId;
            }

            $sql = 'SELECT d.id, d.titolo, d.stato, d.scade_il,
                           c.nome AS categoria_nome
                    FROM documenti d
                    LEFT JOIN documenti_categorie c ON c.id = d.categoria_id
                    WHERE ' . implode(' AND ', $where) . '
                    ORDER BY d.updated_at DESC
                    LIMIT ?';

            $params[] = $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $results = [];
            foreach ($rows as $row) {
                $badge = in_array($row['stato'], ['bozza', 'inviato', 'in_controllo', 'controllato', 'in_approvazione', 'approvato', 'scaduto', 'rifiutato'], true)
                    ? t('documenti.stato.' . $row['stato'])
                    : null;

                $results[] = [
                    'title'    => $row['titolo'],
                    'subtitle' => $row['categoria_nome'] ?? t('documenti.search.default_subtitle'),
                    'url'      => route('documenti.show', ['id' => $row['id']]),
                    'icon'     => 'fa-file-alt',
                    'badge'    => $badge,
                ];
            }

            return $results;
        } catch (\Throwable) {
            return [];
        }
    }

    public function getSearchLabel(): string
    {
        return t('documenti.title');
    }

    public function getSearchIcon(): string
    {
        return 'fa-file-alt';
    }
}
