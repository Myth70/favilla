<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\Controller;
use App\Modules\Admin\Services\AdminIndexService;

class PaletteApiController extends Controller
{
    public function index(): void
    {
        if (!empty($_SESSION['_palette_catalog'])) {
            $this->json($_SESSION['_palette_catalog']);
            return;
        }

        $service = app(AdminIndexService::class);
        $user = auth() ?? [];
        $flat = $service->getFlatCatalog(
            (array) ($user['permissions'] ?? []),
            (array) ($user['roles'] ?? []),
        );

        $_SESSION['_palette_catalog'] = $flat;

        $this->json($flat);
    }
}
