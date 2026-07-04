<?php

declare(strict_types=1);

namespace App\Cli\Commands;

use App\Cli\Support\CliBootstrap;
use App\Modules\Blog\Services\BlogArticleService;

class BlogPublishScheduledCommand
{
    public function handle(array $args): void
    {
        CliBootstrap::boot();

        $service   = app(BlogArticleService::class);
        $published = $service->publishScheduledArticles();

        echo "\nBlog: pubblicazione articoli schedulati\n";
        echo "========================================\n\n";
        echo '  Articoli pubblicati: ' . $published . "\n\n";
    }
}
