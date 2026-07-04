<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Modules\Reports\Repositories\StylePresetRepository;
use App\Services\FileUploadService;

/**
 * Business logic for report style presets (CRUD + logo management).
 */
class StyleService
{
    private StylePresetRepository $repo;

    public function __construct()
    {
        $this->repo = app(StylePresetRepository::class);
    }

    /**
     * Create a new style preset.
     *
     * @param  array $data   Form data
     * @param  array $files  $_FILES array
     * @return int   New preset ID
     */
    public function create(array $data, array $files = []): int
    {
        // Handle logo uploads
        if (!empty($files['logo']['tmp_name'])) {
            $data['logo_path'] = FileUploadService::uploadImage($files['logo'], 'reports', 'style_logo_');
        }
        if (!empty($files['logo_secondary']['tmp_name'])) {
            $data['logo_secondary_path'] = FileUploadService::uploadImage($files['logo_secondary'], 'reports', 'style_logo2_');
        }

        // Set creator
        $data['created_by'] = (int) auth()['id'];

        // If marked as default, unset others first
        if (!empty($data['is_default'])) {
            $data['is_default'] = 1;
        } else {
            $data['is_default'] = 0;
        }

        $id = $this->repo->create($data);

        // If this is the new default, ensure exclusivity
        if ((int) $data['is_default'] === 1) {
            $this->repo->setDefault($id);
        }

        return $id;
    }

    public function listAll(): array
    {
        return $this->repo->listAll();
    }

    public function find(int $id): ?array
    {
        return $this->repo->find($id);
    }

    /**
     * Update an existing style preset.
     *
     * @param  int   $id    Preset ID
     * @param  array $data  Form data
     * @param  array $files $_FILES array
     * @return bool
     */
    public function update(int $id, array $data, array $files = []): bool
    {
        $existing = $this->repo->find($id);
        if ($existing === null) {
            return false;
        }

        // Handle logo replacement
        if (!empty($files['logo']['tmp_name'])) {
            // Delete old logo
            if (!empty($existing['logo_path'])) {
                FileUploadService::delete($existing['logo_path'], 'reports');
            }
            $data['logo_path'] = FileUploadService::uploadImage($files['logo'], 'reports', 'style_logo_');
        } elseif (!empty($data['remove_logo'])) {
            if (!empty($existing['logo_path'])) {
                FileUploadService::delete($existing['logo_path'], 'reports');
            }
            $data['logo_path'] = null;
        }
        unset($data['remove_logo']);

        // Handle secondary logo replacement
        if (!empty($files['logo_secondary']['tmp_name'])) {
            if (!empty($existing['logo_secondary_path'])) {
                FileUploadService::delete($existing['logo_secondary_path'], 'reports');
            }
            $data['logo_secondary_path'] = FileUploadService::uploadImage($files['logo_secondary'], 'reports', 'style_logo2_');
        } elseif (!empty($data['remove_logo_secondary'])) {
            if (!empty($existing['logo_secondary_path'])) {
                FileUploadService::delete($existing['logo_secondary_path'], 'reports');
            }
            $data['logo_secondary_path'] = null;
        }
        unset($data['remove_logo_secondary']);

        // Default flag
        if (!empty($data['is_default'])) {
            $data['is_default'] = 1;
        } else {
            $data['is_default'] = 0;
        }

        $result = $this->repo->update($id, $data);

        // If this is the new default, ensure exclusivity
        if ($result && (int) $data['is_default'] === 1) {
            $this->repo->setDefault($id);
        }

        return $result;
    }

    /**
     * Delete a style preset.
     *
     * @param  int $id Preset ID
     * @return bool|string True on success, error message string if in use
     */
    public function delete(int $id): bool|string
    {
        $existing = $this->repo->find($id);
        if ($existing === null) {
            return 'Stile non trovato.';
        }

        // Check usage
        $usageCount = $this->repo->countTemplatesUsing($id);
        if ($usageCount > 0) {
            return "Impossibile eliminare: lo stile è utilizzato da {$usageCount} template.";
        }

        // Delete logo files
        if (!empty($existing['logo_path'])) {
            FileUploadService::delete($existing['logo_path'], 'reports');
        }
        if (!empty($existing['logo_secondary_path'])) {
            FileUploadService::delete($existing['logo_secondary_path'], 'reports');
        }

        return $this->repo->delete($id);
    }

    /**
     * Set a preset as the default.
     */
    public function setDefault(int $id): void
    {
        $this->repo->setDefault($id);
    }
}
