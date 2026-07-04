<?php

declare(strict_types=1);

namespace App\Modules\Home\Controllers;

use App\Core\Controller;
use App\Modules\Home\Services\PreferencesService;
use App\Traits\ControllerHelpers;

class PreferencesController extends Controller
{
    use ControllerHelpers;

    private PreferencesService $service;

    public function __construct()
    {
        $this->service = app(PreferencesService::class);
    }

    /**
     * Handle preference update (theme).
     */
    public function updateTheme(): void
    {
        $data   = $this->cleanPost(['theme']);
        $userId = auth()['id'] ?? null;

        if (!$userId) {
            http_response_code(401);
            return;
        }

        try {
            $theme = $this->service->updateTheme($userId, $data['theme'] ?? 'light');
            $_SESSION['user_preferences']['theme'] = $theme;
            http_response_code(204);
        } catch (\Throwable $e) {
            app_log('error', 'PreferencesController::updateTheme error: ' . $e->getMessage());
            http_response_code(500);
        }
    }

    /**
     * Handle preference update (accent color).
     */
    public function updateColor(): void
    {
        $data   = $this->cleanPost(['color']);
        $userId = auth()['id'] ?? null;

        if (!$userId) {
            http_response_code(401);
            return;
        }

        try {
            $color = $this->service->updateColor($userId, $data['color'] ?? PreferencesService::DEFAULT_COLOR);
            $_SESSION['user_preferences']['primary_color'] = $color;
            http_response_code(204);
        } catch (\Throwable $e) {
            app_log('error', 'PreferencesController::updateColor error: ' . $e->getMessage());
            http_response_code(500);
        }
    }

    /**
     * Handle preference update (sidebar collapsed).
     */
    public function updateSidebar(): void
    {
        $data      = $this->cleanPost(['sidebar_collapsed']);
        $userId    = auth()['id'] ?? null;
        $collapsed = ($data['sidebar_collapsed'] ?? '0') === '1' ? 1 : 0;

        if (!$userId) {
            http_response_code(401);
            return;
        }

        try {
            $value = $this->service->updateSidebar($userId, $collapsed);
            $_SESSION['user_preferences']['sidebar_collapsed'] = $value;
            http_response_code(204);
        } catch (\Throwable $e) {
            app_log('error', 'PreferencesController::updateSidebar error: ' . $e->getMessage());
            http_response_code(500);
        }
    }

    /**
     * Handle preference update (sidebar skin: default|light|accent).
     */
    public function updateSidebarStyle(): void
    {
        $data   = $this->cleanPost(['style']);
        $userId = auth()['id'] ?? null;

        if (!$userId) {
            http_response_code(401);
            return;
        }

        try {
            $style = $this->service->updateSidebarStyle($userId, $data['style'] ?? 'default');
            $_SESSION['user_preferences']['sidebar_style'] = $style;
            http_response_code(204);
        } catch (\Throwable $e) {
            app_log('error', 'PreferencesController::updateSidebarStyle error: ' . $e->getMessage());
            http_response_code(500);
        }
    }

    /**
     * Handle preference update (visual theme skin).
     */
    public function updateSkin(): void
    {
        $data   = $this->cleanPost(['skin']);
        $userId = auth()['id'] ?? null;

        if (!$userId) {
            http_response_code(401);
            return;
        }

        try {
            $skin = $this->service->updateSkin($userId, $data['skin'] ?? 'default');
            $_SESSION['user_preferences']['theme_skin'] = $skin;
            http_response_code(204);
        } catch (\Throwable $e) {
            app_log('error', 'PreferencesController::updateSkin error: ' . $e->getMessage());
            http_response_code(500);
        }
    }

    /**
     * Handle preference update (hero background pattern).
     */
    public function updatePattern(): void
    {
        $data   = $this->cleanPost(['pattern']);
        $userId = auth()['id'] ?? null;

        if (!$userId) {
            http_response_code(401);
            return;
        }

        try {
            $pattern = $this->service->updatePattern($userId, $data['pattern'] ?? 'circles');
            $_SESSION['user_preferences']['background_pattern'] = $pattern;
            http_response_code(204);
        } catch (\Throwable $e) {
            app_log('error', 'PreferencesController::updatePattern error: ' . $e->getMessage());
            http_response_code(500);
        }
    }

    /**
     * Handle preference update (font family).
     */
    public function updateFont(): void
    {
        $data   = $this->cleanPost(['font']);
        $userId = auth()['id'] ?? null;

        if (!$userId) {
            http_response_code(401);
            return;
        }

        try {
            $font = $this->service->updateFont($userId, $data['font'] ?? 'system');
            $_SESSION['user_preferences']['font_family'] = $font;
            http_response_code(204);
        } catch (\Throwable $e) {
            app_log('error', 'PreferencesController::updateFont error: ' . $e->getMessage());
            http_response_code(500);
        }
    }
}
