<?php

declare(strict_types=1);

namespace App\Modules\Blog\Providers;

use App\Contracts\ExportableModule;
use App\Modules\Blog\Repositories\BlogArticleRepository;
use App\Modules\Blog\Repositories\BlogCommentRepository;

class BlogExportProvider implements ExportableModule
{
    public function getDataSources(): array
    {
        return [
            [
                'key'        => 'articles',
                'label'      => 'Articoli',
                'icon'       => 'fa-file-lines',
                'permission' => 'blog.admin',
                'fields'     => [
                    ['name' => 'id',            'label' => 'ID',              'type' => 'integer',  'sortable' => true,  'filterable' => false],
                    ['name' => 'title',         'label' => 'Titolo',          'type' => 'string',   'sortable' => true,  'filterable' => true],
                    ['name' => 'slug',          'label' => 'Slug',            'type' => 'string',   'sortable' => false, 'filterable' => false],
                    ['name' => 'status',        'label' => 'Stato',           'type' => 'enum',     'sortable' => true,  'filterable' => true, 'enum_values' => ['draft', 'scheduled', 'published']],
                    ['name' => 'is_pinned',     'label' => 'In evidenza',     'type' => 'boolean',  'sortable' => true,  'filterable' => true],
                    ['name' => 'visibility',    'label' => 'Visibilità',      'type' => 'string',   'sortable' => false, 'filterable' => false],
                    ['name' => 'category_name', 'label' => 'Categoria',       'type' => 'string',   'sortable' => false, 'filterable' => true],
                    ['name' => 'author_name',   'label' => 'Autore',          'type' => 'string',   'sortable' => false, 'filterable' => true],
                    ['name' => 'reading_time',  'label' => 'Tempo lettura',   'type' => 'integer',  'sortable' => true,  'filterable' => false],
                    ['name' => 'published_at',  'label' => 'Pubblicato il',   'type' => 'datetime', 'sortable' => true,  'filterable' => true],
                    ['name' => 'comment_count', 'label' => 'Commenti',        'type' => 'integer',  'sortable' => false, 'filterable' => false],
                    ['name' => 'created_at',    'label' => 'Data creazione',  'type' => 'datetime', 'sortable' => true,  'filterable' => true],
                ],
            ],
            [
                'key'        => 'comments',
                'label'      => 'Commenti',
                'icon'       => 'fa-comments',
                'permission' => 'blog.comment.moderate',
                'fields'     => [
                    ['name' => 'id',            'label' => 'ID',            'type' => 'integer',  'sortable' => true,  'filterable' => false],
                    ['name' => 'article_title', 'label' => 'Articolo',      'type' => 'string',   'sortable' => false, 'filterable' => true],
                    ['name' => 'user_name',     'label' => 'Utente',        'type' => 'string',   'sortable' => false, 'filterable' => true],
                    ['name' => 'body',          'label' => 'Contenuto',     'type' => 'string',   'sortable' => false, 'filterable' => true],
                    ['name' => 'parent_id',     'label' => 'Risposta a',    'type' => 'integer',  'sortable' => false, 'filterable' => false],
                    ['name' => 'created_at',    'label' => 'Data',          'type' => 'datetime', 'sortable' => true,  'filterable' => true],
                ],
            ],
        ];
    }

    public function getExportData(
        string $sourceKey,
        array  $filters = [],
        string $sortBy = 'created_at',
        string $sortDir = 'DESC',
        int    $limit = 10000
    ): array {
        switch ($sourceKey) {
            case 'articles':
                return $this->exportArticles($filters, $sortBy, $sortDir, $limit);
            case 'comments':
                return $this->exportComments($filters, $sortBy, $sortDir, $limit);
            default:
                return [];
        }
    }

    public function getExportModuleName(): string
    {
        return 'Blog';
    }

    public function getExportModuleIcon(): string
    {
        return 'fa-newspaper';
    }

    public function getSingleRecord(string $sourceKey, int $recordId): ?array
    {
        if ($sourceKey === 'articles') {
            $repo    = app(BlogArticleRepository::class);
            $article = $repo->findForEdit($recordId);
            if (!$article) {
                return null;
            }

            $tags = $repo->getArticleTags($recordId);
            $article['tags'] = implode(', ', array_column($tags, 'name'));

            // Testo semplice per il template PDF "Scheda Articolo": il motore
            // Reports/DompdfExportEngine esegue sempre htmlspecialchars() sui
            // placeholder {{ campo }}, quindi il markup HTML di "content"
            // andrebbe mostrato escapato/illeggibile — qui si fornisce invece
            // una versione senza tag.
            $article['content_text'] = trim(strip_tags((string) ($article['content'] ?? '')));

            // Comment count
            $pdo  = app(\PDO::class);
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM blog_comments WHERE article_id = ? AND deleted_at IS NULL');
            $stmt->execute([$recordId]);
            $article['comment_count'] = (int) $stmt->fetchColumn();

            // Category name
            if (!empty($article['category_id'])) {
                $catStmt = $pdo->prepare('SELECT name FROM blog_categories WHERE id = ?');
                $catStmt->execute([$article['category_id']]);
                $article['category_name'] = $catStmt->fetchColumn() ?: '';
            } else {
                $article['category_name'] = '';
            }

            return $article;
        }

        if ($sourceKey === 'comments') {
            $pdo  = app(\PDO::class);
            $stmt = $pdo->prepare('
                SELECT bc.*, u.name AS user_name, a.title AS article_title
                FROM blog_comments bc
                LEFT JOIN users u ON u.id = bc.user_id
                LEFT JOIN blog_articles a ON a.id = bc.article_id
                WHERE bc.id = ? AND bc.deleted_at IS NULL
            ');
            $stmt->execute([$recordId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        }

        return null;
    }

    // ── Private helpers ──────────────────────────────────────────────

    private function exportArticles(array $filters, string $sortBy, string $sortDir, int $limit): array
    {
        $allowedSorts = ['id', 'title', 'status', 'published_at', 'created_at', 'is_pinned', 'reading_time'];
        $sort = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';
        $dir  = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $filters['sort'] = $sort;
        $filters['dir']  = $dir;

        $repo = app(BlogArticleRepository::class);
        $result = $repo->listForAdmin($filters, 1, $limit);
        return $result['items'] ?? [];
    }

    private function exportComments(array $filters, string $sortBy, string $sortDir, int $limit): array
    {
        $allowedSorts = ['id', 'created_at'];
        $sort = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';
        $dir  = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $repo = app(BlogCommentRepository::class);
        $result = $repo->listForAdmin($filters, 1, $limit);
        return $result['items'] ?? [];
    }
}
