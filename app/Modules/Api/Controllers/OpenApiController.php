<?php

declare(strict_types=1);

namespace App\Modules\Api\Controllers;

use App\Core\Controller;
use App\Traits\ControllerHelpers;

/**
 * GET /api/v1/openapi.json — specifica OpenAPI 3.1 servita staticamente
 * (scritta a mano in docs/api/openapi.json). Endpoint pubblico, fuori dal
 * gruppo autenticato: descrive l'API e non espone dati.
 */
class OpenApiController extends Controller
{
    use ControllerHelpers;

    public function spec(): void
    {
        $path = BASE_PATH . '/docs/api/openapi.json';
        $json = is_file($path) ? file_get_contents($path) : false;

        if ($json === false) {
            $this->json(['error' => ['code' => 'spec_unavailable', 'message' => 'Specifica non disponibile.']], 404);
            return;
        }

        if (defined('FAVILLA_TESTING')) {
            $decoded = json_decode($json, true);
            $this->json(is_array($decoded) ? $decoded : [], 200);
            return;
        }

        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo $json;
        exit;
    }
}
