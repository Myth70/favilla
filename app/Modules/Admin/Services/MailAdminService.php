<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Repositories\MailLogRepository;
use App\Repositories\MailTemplateRepository;

class MailAdminService
{
    private MailTemplateRepository $templateRepo;
    private MailLogRepository $logRepo;

    public function __construct()
    {
        $this->templateRepo = app(MailTemplateRepository::class);
        $this->logRepo      = app(MailLogRepository::class);
    }

    // ---------------------------------------------------------------
    // TEMPLATE
    // ---------------------------------------------------------------

    public function allTemplates(): array
    {
        return $this->templateRepo->all();
    }

    public function findTemplate(int $id): ?array
    {
        return $this->templateRepo->find($id);
    }

    public function createTemplate(array $data): int
    {
        return $this->templateRepo->create($data);
    }

    public function updateTemplate(int $id, array $data): void
    {
        $this->templateRepo->update($id, $data);
    }

    public function deleteTemplate(int $id): void
    {
        $this->templateRepo->delete($id);
    }

    public function validateTemplate(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        if (empty($data['slug'])) {
            $errors['slug'] = t('admin.mail.valid_slug_required');
        } elseif (!preg_match('/^[a-z0-9\-]+$/', $data['slug'])) {
            $errors['slug'] = t('admin.mail.valid_slug_format');
        } else {
            $existing = $this->templateRepo->findBySlug($data['slug']);
            if ($existing && (!$excludeId || $existing['id'] != $excludeId)) {
                $errors['slug'] = t('admin.mail.valid_slug_taken');
            }
        }

        if (empty($data['name'])) {
            $errors['name'] = t('admin.mail.valid_name_required');
        }
        if (empty($data['subject'])) {
            $errors['subject'] = t('admin.mail.valid_subject_required');
        }
        if (empty($data['body_html'])) {
            $errors['body_html'] = t('admin.mail.valid_body_required');
        } elseif (preg_match('/<script[\s>]/i', $data['body_html'])) {
            $errors['body_html'] = t('admin.mail.valid_body_no_script');
        } elseif (preg_match('/\bon\w+\s*=/i', $data['body_html'])) {
            $errors['body_html'] = t('admin.mail.valid_body_no_events');
        }

        return $errors;
    }

    // ---------------------------------------------------------------
    // LOG
    // ---------------------------------------------------------------

    public function getLogsPaginated(int $page, int $perPage, array $filters): array
    {
        return $this->logRepo->listPaginated($page, $perPage, $filters);
    }

    public function getLogStats(): array
    {
        return $this->logRepo->countByStatus();
    }
}
