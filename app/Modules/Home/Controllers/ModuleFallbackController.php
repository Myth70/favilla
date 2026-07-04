<?php

declare(strict_types=1);

namespace App\Modules\Home\Controllers;

use App\Core\Controller;

class ModuleFallbackController extends Controller
{
    private const LABEL_KEYS = [
        '/files'    => 'home.fallback.files',
        '/contacts' => 'home.fallback.contacts',
        '/tasks'    => 'home.fallback.tasks',
    ];

    public function redirectToHome(): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $label = t('home.fallback.requested');

        foreach (self::LABEL_KEYS as $prefix => $labelKey) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                $label = t($labelKey);
                break;
            }
        }

        flash_error(t('home.fallback.unavailable', ['label' => $label]));
        $this->redirect(route('home.index'));
    }
}
