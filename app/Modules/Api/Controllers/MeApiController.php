<?php

declare(strict_types=1);

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Http\ApiController;

/**
 * GET /api/v1/me — identità del token corrente. Endpoint di verifica veloce per
 * i client (conferma che il Bearer token è valido e con quali scope).
 */
class MeApiController extends ApiController
{
    public function show(): void
    {
        $context = $this->context();
        $user = $context->user();

        $this->ok([
            'id'     => (int) ($user['id'] ?? 0),
            'name'   => $user['name'] ?? '',
            'email'  => $user['email'] ?? '',
            'roles'  => $context->roles(),
            'scopes' => $context->scopes(), // null = permessi pieni dell'utente
        ]);
    }
}
