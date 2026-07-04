<?php

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleMiddleware;
use App\Modules\Blog\Controllers\BlogAdminController;
use App\Modules\Blog\Controllers\BlogAuthorController;
use App\Modules\Blog\Controllers\BlogCommentController;
use App\Modules\Blog\Controllers\BlogInteractionController;
use App\Modules\Blog\Controllers\BlogPublicController;

// ── GROUP 1: Admin panel (Auth + Csrf + blog.admin) ─────────────
$router->group([
    'prefix'     => 'admin/blog',
    'middleware' => [AuthMiddleware::class, CsrfMiddleware::class, RoleMiddleware::withPermission('blog.admin')],
], function ($r) {
    $r->get('/', [BlogAdminController::class, 'index'])->name('blog.admin.index');
    $r->get('/articles', [BlogAdminController::class, 'articles'])->name('blog.admin.articles');

    // Trash (static routes before parametric)
    $r->get('/trash', [BlogAdminController::class, 'trash'])->name('blog.admin.trash');
    $r->post('/trash/{id}/restore', [BlogAdminController::class, 'restoreArticle'])->name('blog.admin.trash.restore');
    $r->delete('/trash/{id}', [BlogAdminController::class, 'forceDestroy'])->name('blog.admin.trash.destroy');

    // Batch actions (before parametric routes)
    $r->post('/articles/batch', [BlogAdminController::class, 'batchAction'])->name('blog.admin.articles.batch');

    // Pin/Unpin
    $r->post('/articles/{id}/pin', [BlogAdminController::class, 'pin'])->name('blog.admin.articles.pin');
    $r->post('/articles/{id}/unpin', [BlogAdminController::class, 'unpin'])->name('blog.admin.articles.unpin');

    // Categories
    $r->get('/categories', [BlogAdminController::class, 'categories'])->name('blog.admin.categories');
    $r->post('/categories', [BlogAdminController::class, 'storeCategory'])->name('blog.admin.categories.store');
    $r->put('/categories/{id}', [BlogAdminController::class, 'updateCategory'])->name('blog.admin.categories.update');
    $r->delete('/categories/{id}', [BlogAdminController::class, 'destroyCategory'])->name('blog.admin.categories.destroy');

    // Tags
    $r->get('/tags', [BlogAdminController::class, 'tags'])->name('blog.admin.tags');
    $r->post('/tags', [BlogAdminController::class, 'storeTag'])->name('blog.admin.tags.store');
    $r->delete('/tags/{id}', [BlogAdminController::class, 'destroyTag'])->name('blog.admin.tags.destroy');
});

// ── GROUP 1a: Moderazione (Auth + Csrf + blog.comment.moderate) ─────────────
// Commenti e blacklist sono separati da blog.admin per supportare moderatori non-admin.
$router->group([
    'prefix'     => 'admin/blog',
    'middleware' => [AuthMiddleware::class, CsrfMiddleware::class, RoleMiddleware::withPermission('blog.comment.moderate')],
], function ($r) {
    // Comments
    $r->get('/comments', [BlogAdminController::class, 'comments'])->name('blog.admin.comments');
    $r->post('/comments/{id}/approve', [BlogAdminController::class, 'approveComment'])->name('blog.admin.comments.approve');
    $r->post('/comments/{id}/reject', [BlogAdminController::class, 'rejectComment'])->name('blog.admin.comments.reject');
    $r->delete('/comments/{id}', [BlogAdminController::class, 'deleteComment'])->name('blog.admin.comments.delete');

    // Blacklist
    $r->get('/blacklist', [BlogAdminController::class, 'blacklist'])->name('blog.admin.blacklist');
    $r->post('/blacklist/ban', [BlogAdminController::class, 'ban'])->name('blog.admin.blacklist.ban');
    $r->post('/blacklist/{userId}/unban', [BlogAdminController::class, 'unban'])->name('blog.admin.blacklist.unban');
});

// ── GROUP 1b: Interactions (Auth + Csrf + blog.view) — like/bookmark/saved ──
$router->group([
    'prefix'     => 'blog',
    'middleware' => [AuthMiddleware::class, CsrfMiddleware::class, RoleMiddleware::withPermission('blog.view')],
], function ($r) {
    $r->get('/saved', [BlogInteractionController::class, 'saved'])->name('blog.saved');
    $r->post('/{slug}/like', [BlogInteractionController::class, 'toggleLike'])->name('blog.like');
    $r->post('/{slug}/bookmark', [BlogInteractionController::class, 'toggleBookmark'])->name('blog.bookmark');
});

// ── GROUP 2: Author CRUD (Auth + Csrf + blog.write) ─────────────
// Static routes BEFORE parametric — "my", "create", "search" must not match /{slug}
$router->group([
    'prefix'     => 'blog',
    'middleware' => [AuthMiddleware::class, CsrfMiddleware::class, RoleMiddleware::withPermission('blog.write')],
], function ($r) {
    $r->get('/my', [BlogAuthorController::class, 'index'])->name('blog.author.index');
    $r->get('/create', [BlogAuthorController::class, 'create'])->name('blog.create');
    $r->post('/store', [BlogAuthorController::class, 'store'])->name('blog.store');

    // Parametric author routes
    $r->get('/{id}/edit', [BlogAuthorController::class, 'edit'])->name('blog.edit');
    $r->put('/{id}', [BlogAuthorController::class, 'update'])->name('blog.update');
    $r->delete('/{id}', [BlogAuthorController::class, 'destroy'])->name('blog.destroy');
    $r->post('/{id}/publish', [BlogAuthorController::class, 'publish'])->name('blog.publish');
    $r->post('/{id}/unpublish', [BlogAuthorController::class, 'unpublish'])->name('blog.unpublish');
});

// ── GROUP 3: Comments (Auth + Csrf + blog.comment) ──────
$router->group([
    'prefix'     => 'blog',
    'middleware' => [AuthMiddleware::class, CsrfMiddleware::class, RoleMiddleware::withPermission('blog.comment')],
], function ($r) {
    $r->post('/{slug}/comments', [BlogCommentController::class, 'store'])->name('blog.comments.store');
    $r->post('/{slug}/comments/{id}/reply', [BlogCommentController::class, 'reply'])->name('blog.comments.reply');
});

// ── GROUP 4: Blog read routes (Auth + Csrf + blog.view) ──
// Static routes BEFORE parametric — "search", "category", "tag", "author" before /{slug}
$router->group([
    'prefix'     => 'blog',
    'middleware' => [AuthMiddleware::class, CsrfMiddleware::class, RoleMiddleware::withPermission('blog.view')],
], function ($r) {
    $r->get('/', [BlogPublicController::class, 'index'])->name('blog.index');
    $r->get('/search', [BlogPublicController::class, 'search'])->name('blog.search');
    $r->get('/category/{slug}', [BlogPublicController::class, 'category'])->name('blog.category');
    $r->get('/tag/{slug}', [BlogPublicController::class, 'tag'])->name('blog.tag');
    $r->get('/author/{id}', [BlogPublicController::class, 'author'])->name('blog.author');
    // PDF route BEFORE slug catch-all
    $r->get('/{slug}/pdf', [BlogPublicController::class, 'pdf'])->name('blog.pdf');
    $r->get('/{slug}', [BlogPublicController::class, 'show'])->name('blog.show');
});
