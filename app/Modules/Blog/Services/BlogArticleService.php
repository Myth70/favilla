<?php

declare(strict_types=1);

namespace App\Modules\Blog\Services;

use App\Modules\Blog\Repositories\BlogArticleRepository;
use App\Modules\Blog\Repositories\BlogTagRepository;
use App\Modules\Notifications\Services\NotificationService;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\FileUploadService;

class BlogArticleService
{
    private BlogArticleRepository $articleRepo;
    private BlogTagRepository $tagRepo;

    public function __construct()
    {
        $this->articleRepo = app(BlogArticleRepository::class);
        $this->tagRepo     = app(BlogTagRepository::class);
    }

    /**
     * Create a new article (draft).
     */
    public function createArticle(array $data, int $userId): int
    {
        $content     = BlogContentSanitizer::sanitize($data['content'] ?? '');
        $slug        = BlogSlugService::articleSlug($data['title']);
        $readingTime = $this->calculateReadingTime($content);

        $publishAt = !empty($data['publish_at'])
            ? date('Y-m-d H:i:s', strtotime($data['publish_at']))
            : null;
        $status = $publishAt ? 'scheduled' : 'draft';

        $articleId = $this->articleRepo->create([
            'title'            => $data['title'],
            'slug'             => $slug,
            'excerpt'          => !empty($data['excerpt']) ? $data['excerpt'] : $this->generateExcerpt($content),
            'meta_description' => $this->sanitizeMetaField($data['meta_description'] ?? null, 300),
            'meta_keywords'    => $this->sanitizeMetaField($data['meta_keywords'] ?? null, 255),
            'og_image'         => !empty($data['og_image']) ? $data['og_image'] : null,
            'content'          => $content,
            'cover_image'      => $data['cover_image'] ?? null,
            'category_id'      => $data['category_id'] ?: null,
            'status'           => $status,
            'publish_at'       => $publishAt,
            'is_pinned'        => (int) ($data['is_pinned'] ?? 0),
            'visibility'       => $data['visibility'] ?? 'all',
            'reading_time'     => $readingTime,
            'created_by'       => $userId,
        ]);

        $tagIds = $this->resolveTagIds($data['tags'] ?? '');
        $this->articleRepo->syncTags($articleId, $tagIds);

        AuditService::log('blog_article_created', 'blog_article', $articleId, null, ['title' => $data['title']]);

        return $articleId;
    }

    /**
     * Update an existing article.
     */
    public function updateArticle(int $id, array $data, ?string $coverImage = null): void
    {
        $existing = $this->articleRepo->findForEdit($id);
        if (!$existing) {
            return;
        }

        $content     = BlogContentSanitizer::sanitize($data['content'] ?? '');
        $readingTime = $this->calculateReadingTime($content);
        $isPublished = $existing['status'] === 'published';

        // Preserve slug on published articles: changing the title of a published
        // article must NOT silently break external links/permalinks.
        $slug = $isPublished
            ? $existing['slug']
            : BlogSlugService::articleSlug($data['title'], $id);

        $updateData = [
            'title'            => $data['title'],
            'slug'             => $slug,
            'excerpt'          => !empty($data['excerpt']) ? $data['excerpt'] : $this->generateExcerpt($content),
            'meta_description' => $this->sanitizeMetaField($data['meta_description'] ?? null, 300),
            'meta_keywords'    => $this->sanitizeMetaField($data['meta_keywords'] ?? null, 255),
            'og_image'         => !empty($data['og_image']) ? $data['og_image'] : null,
            'content'          => $content,
            'cover_image'      => $coverImage,
            'category_id'      => $data['category_id'] ?: null,
            'is_pinned'        => (int) ($data['is_pinned'] ?? 0),
            'visibility'       => $data['visibility'] ?? 'all',
            'reading_time'     => $readingTime,
        ];

        // publish_at and status are only touched for draft/scheduled articles.
        // For published articles the field is ignored to avoid losing the original timestamp.
        if (!$isPublished) {
            $publishAt = !empty($data['publish_at'])
                ? date('Y-m-d H:i:s', strtotime($data['publish_at']))
                : null;
            $updateData['publish_at'] = $publishAt;
            $updateData['status']     = $publishAt ? 'scheduled' : 'draft';
        }

        $this->articleRepo->update($id, $updateData);

        $tagIds = $this->resolveTagIds($data['tags'] ?? '');
        $this->articleRepo->syncTags($id, $tagIds);
    }

    /**
     * Publish an article.
     */
    public function publish(int $id, int $userId): void
    {
        $article = $this->articleRepo->findForEdit($id);
        if (!$article) {
            return;
        }

        $this->articleRepo->publish($id);
        AuditService::log('blog_article_published', 'blog_article', $id, null, ['title' => $article['title']]);

        try {
            NotificationService::dispatchEventToRole(
                'blog.article_published',
                'Blog',
                'admin',
                [
                    'article_id'    => $id,
                    'article_title' => $article['title'],
                    'article_slug'  => $article['slug'],
                ],
                route('blog.show', ['slug' => $article['slug']]),
                $this->resolveActorId($userId)
            );
        } catch (\Throwable $e) {
            error_log('[Blog] dispatch blog.article_published failed for article ' . $id . ': ' . $e->getMessage());
        }
    }

    /**
     * Normalize a SEO meta text field: strip tags, collapse whitespace, cap length.
     */
    private function sanitizeMetaField(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $clean = trim(preg_replace('/\s+/', ' ', strip_tags($value)));
        if ($clean === '') {
            return null;
        }
        return mb_substr($clean, 0, $max);
    }

    /**
     * Return $userId if the user still exists (not soft-deleted), null otherwise.
     * Used to avoid dispatching notifications with an orphan actor.
     */
    private function resolveActorId(?int $userId): ?int
    {
        if ($userId === null || $userId <= 0) {
            return null;
        }
        try {
            $user = app(UserRepository::class)->find($userId);
            if (!$user || !empty($user['deleted_at'])) {
                return null;
            }
            return $userId;
        } catch (\Throwable $e) {
            error_log('[Blog] resolveActorId lookup failed for user ' . $userId . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Publish all scheduled articles whose publish_at is in the past.
     * Intended to be called by the blog:publish-scheduled CLI command.
     *
     * @return int Number of articles published
     */
    public function publishScheduledArticles(): int
    {
        $articles = $this->articleRepo->getScheduledDue();

        $count = 0;
        foreach ($articles as $article) {
            $this->articleRepo->publish($article['id']);
            AuditService::log('blog_article_published', 'blog_article', $article['id'], null, [
                'title'  => $article['title'],
                'source' => 'scheduled',
            ]);

            try {
                NotificationService::dispatchEventToRole(
                    'blog.article_published',
                    'Blog',
                    'admin',
                    [
                        'article_id'    => $article['id'],
                        'article_title' => $article['title'],
                        'article_slug'  => $article['slug'],
                    ],
                    route('blog.show', ['slug' => $article['slug']]),
                    $this->resolveActorId($article['created_by'] ?? null)
                );
            } catch (\Throwable $e) {
                error_log('[Blog] scheduled-publish notification failed for article ' . $article['id'] . ': ' . $e->getMessage());
            }

            $count++;
        }

        return $count;
    }

    /**
     * Unpublish an article (revert to draft).
     */
    public function unpublish(int $id): void
    {
        $article = $this->articleRepo->findForEdit($id);
        if (!$article) {
            return;
        }

        $this->articleRepo->unpublish($id);
        AuditService::log('blog_article_unpublished', 'blog_article', $id, null, ['title' => $article['title']]);
    }

    /**
     * Soft-delete an article and clean up owned cover image.
     */
    public function deleteArticle(int $id): void
    {
        $article = $this->articleRepo->findForEdit($id);
        if (!$article) {
            return;
        }

        // Delete owned cover image (not library references)
        if (!empty($article['cover_image']) && !str_contains($article['cover_image'], '/')) {
            FileUploadService::delete($article['cover_image'], 'blog');
        }

        $this->articleRepo->delete($id);
        AuditService::log('blog_article_deleted', 'blog_article', $id, ['title' => $article['title']], null);
    }

    /**
     * Restore a soft-deleted article.
     */
    public function restoreArticle(int $id): bool
    {
        $article = $this->articleRepo->findWithTrashed($id);
        if (!$article || empty($article['deleted_at'])) {
            return false;
        }

        $result = $this->articleRepo->restore($id);
        if ($result) {
            AuditService::log('blog_article_restored', 'blog_article', $id, null, ['title' => $article['title']]);
        }
        return $result;
    }

    /**
     * Permanently delete a soft-deleted article.
     */
    public function forceDeleteArticle(int $id): bool
    {
        $article = $this->articleRepo->findWithTrashed($id);
        if (!$article || empty($article['deleted_at'])) {
            return false;
        }

        if (!empty($article['cover_image']) && !str_contains($article['cover_image'], '/')) {
            FileUploadService::delete($article['cover_image'], 'blog');
        }

        $result = $this->articleRepo->forceDelete($id);
        if ($result) {
            AuditService::log('blog_article_force_deleted', 'blog_article', $id, ['title' => $article['title']], null);
        }
        return $result;
    }

    /**
     * Toggle pin status (admin only).
     */
    public function togglePin(int $id, bool $pinned): void
    {
        $this->articleRepo->togglePin($id, $pinned);
    }

    /**
     * Handle cover image from form submission.
     * Priority: library picker > direct upload > keep existing > remove.
     *
     * @return string|null The cover image path/filename
     */
    public function handleCoverImage(array $post, array $files, ?string $existingCover): ?string
    {
        $coverImage    = $existingCover;
        $coverImageUrl = trim($post['cover_image_url'] ?? '');

        // 1. Library picker selection: strict subdirectory/filename whitelist
        //    and existence check on disk to prevent assigning a non-existent path.
        if ($coverImageUrl !== '' && $this->isLibraryCoverPathValid($coverImageUrl)) {
            if ($coverImage && !str_contains($coverImage, '/')) {
                FileUploadService::delete($coverImage, 'blog');
            }
            $coverImage = $coverImageUrl;
        }
        // 2. Direct file upload
        elseif (!empty($files['cover_image']['name'])) {
            $newCover = FileUploadService::uploadImage(
                $files['cover_image'],
                'blog',
                'cover_'
            );
            if ($coverImage && !str_contains($coverImage, '/')) {
                FileUploadService::delete($coverImage, 'blog');
            }
            $coverImage = $newCover;
        }

        // 3. Remove cover if checkbox checked
        if (!empty($post['remove_cover']) && $coverImage) {
            if (!str_contains($coverImage, '/')) {
                FileUploadService::delete($coverImage, 'blog');
            }
            $coverImage = null;
        }

        return $coverImage;
    }

    /**
     * Validate that a cover image path from the library picker:
     *  - matches the strict subdir/filename + image extension whitelist,
     *  - does not attempt path traversal,
     *  - points to an existing file under /public/uploads/.
     */
    private function isLibraryCoverPathValid(string $path): bool
    {
        if (!preg_match('#^[a-zA-Z0-9_-]+/[a-zA-Z0-9_.-]+\.(jpg|jpeg|png|webp|gif)$#i', $path)) {
            return false;
        }
        if (str_contains($path, '..')) {
            return false;
        }

        $uploadsBase = realpath(dirname(__DIR__, 4) . '/public/uploads');
        if ($uploadsBase === false) {
            return false;
        }

        $absolute = realpath($uploadsBase . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        if ($absolute === false) {
            return false;
        }

        return strpos($absolute, $uploadsBase . DIRECTORY_SEPARATOR) === 0;
    }

    /**
     * Check if user can edit an article.
     */
    public function canEditArticle(array $article, int $userId): bool
    {
        $isOwner = (int) $article['created_by'] === $userId;
        return $isOwner || has_permission('blog.admin');
    }

    /**
     * Find a published article by slug, checking visibility against user roles.
     */
    public function findForPublicView(string $slug, array $userRoles): ?array
    {
        $article = $this->articleRepo->findBySlug($slug);
        if (!$article) {
            return null;
        }

        // Admin bypasses visibility
        if (has_permission('blog.admin')) {
            return $article;
        }

        $visibility = $article['visibility'] ?? 'all';
        if ($visibility === 'all') {
            return $article;
        }

        // Check if user has any of the required roles
        $allowedRoles = array_map('trim', explode(',', $visibility));
        foreach ($userRoles as $role) {
            $roleSlug = is_array($role) ? ($role['slug'] ?? '') : (string) $role;
            if (in_array($roleSlug, $allowedRoles, true)) {
                return $article;
            }
        }

        return null;
    }

    /**
     * Generate an excerpt from HTML content by stripping tags and truncating.
     */
    public function generateExcerpt(string $htmlContent, int $maxLength = 300): string
    {
        $text = strip_tags($htmlContent);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', trim($text));

        if ($text === '' || mb_strlen($text) <= $maxLength) {
            return $text;
        }

        $truncated = mb_substr($text, 0, $maxLength);
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false && $lastSpace > $maxLength * 0.7) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return rtrim($truncated, '.,;:!? ') . '...';
    }

    /**
     * Calculate estimated reading time in minutes.
     * Uses a unicode-aware word match so accented Italian (and other
     * non-ASCII scripts) are not undercounted by the ASCII-only
     * str_word_count().
     */
    public function calculateReadingTime(string $content): int
    {
        $text = strip_tags($content);
        $wordCount = preg_match_all('/[\p{L}\p{N}]+/u', $text);
        return max(1, (int) ceil(((int) $wordCount) / 200));
    }

    /**
     * Parse comma-separated tag names into tag IDs (creating new tags as needed).
     */
    public const MAX_TAGS_PER_ARTICLE = 10;

    public function resolveTagIds(string $tagsString): array
    {
        if (empty($tagsString)) {
            return [];
        }

        $names = array_filter(array_map('trim', explode(',', $tagsString)));
        $names = array_slice($names, 0, self::MAX_TAGS_PER_ARTICLE);

        $ids = [];
        foreach ($names as $name) {
            if (mb_strlen($name) > 80) {
                continue;
            }
            $tag = $this->tagRepo->findOrCreate($name);
            if ($tag) {
                $ids[] = (int) $tag['id'];
            }
        }

        return array_unique($ids);
    }

    /**
     * Build visibility string from form data.
     */
    public static function buildVisibility(array $post, ?array $existingRoleSlugs = null): string
    {
        $type = $post['visibility_type'] ?? 'all';
        if ($type !== 'roles') {
            return 'all';
        }

        $roles = $post['visibility_roles'] ?? [];
        if (empty($roles) || !is_array($roles)) {
            return 'all';
        }

        $clean = array_filter(array_map(function ($r) {
            return preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($r)));
        }, $roles));

        if (empty($clean)) {
            return 'all';
        }

        if ($existingRoleSlugs === null) {
            try {
                $existingRoleSlugs = array_keys(app(\App\Services\RoleResolver::class)->getSlugToIdMap());
            } catch (\Throwable $e) {
                error_log('[Blog] buildVisibility: RoleResolver unavailable, skipping validation: ' . $e->getMessage());
                return implode(',', array_values($clean));
            }
        }

        $validated = array_values(array_intersect($clean, $existingRoleSlugs));
        return empty($validated) ? 'all' : implode(',', $validated);
    }
}
