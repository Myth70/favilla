<?php

declare(strict_types=1);

namespace App\Repositories;

class MailTemplateRepository extends BaseRepository
{
    protected string $table = 'mail_templates';
    protected bool $timestamps = true;
    protected array $fillable = ['slug', 'name', 'subject', 'body_html', 'variables'];

    public function findBySlug(string $slug): ?array
    {
        return $this->findBy('slug', $slug);
    }
}
