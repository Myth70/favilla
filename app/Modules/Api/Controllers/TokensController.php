<?php

declare(strict_types=1);

namespace App\Modules\Api\Controllers;

use App\Core\Controller;
use App\Modules\Api\Services\ApiTokenService;
use App\Traits\ControllerHelpers;

/**
 * UI self-service dei Personal Access Token (pagina profilo). Solo auth: gli
 * scope selezionabili sono comunque limitati ai permessi dell'utente, quindi
 * non serve un permesso dedicato.
 */
class TokensController extends Controller
{
    use ControllerHelpers;

    private ApiTokenService $tokenService;

    public function __construct()
    {
        $this->tokenService = app(ApiTokenService::class);
    }

    public function index(): void
    {
        $userId = (int) (auth()['id'] ?? 0);

        // Token in chiaro appena creato: mostrato una sola volta poi rimosso.
        $newToken = $_SESSION['_new_api_token'] ?? null;
        unset($_SESSION['_new_api_token']);

        $this->render('Api/Views/tokens/index', [
            'pageTitle'       => t('api.tokens.title'),
            'tokens'          => $this->tokenService->listForUser($userId),
            'availableScopes' => $this->tokenService->availableScopesForUser($userId),
            'newPlainToken'   => is_string($newToken) ? $newToken : null,
            'breadcrumbs'     => [
                ['label' => t('common.user.profile'), 'route' => 'profile'],
                ['label' => t('api.tokens.title')],
            ],
        ]);
    }

    public function store(): void
    {
        $userId = (int) (auth()['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $scopes = $_POST['scopes'] ?? [];
        $scopes = is_array($scopes) ? array_values(array_filter(array_map('strval', $scopes))) : [];
        $expiresAt = $this->resolveExpiry((string) ($_POST['expires'] ?? ''));

        try {
            // Gli scope sono obbligatori: passiamo la selezione così com'è, il
            // service rifiuta una lista vuota (niente token con permessi pieni).
            $result = $this->tokenService->create($userId, $name, $scopes, $expiresAt);
            $_SESSION['_new_api_token'] = $result['plain_token'];
            flash_success(t('api.tokens.flash_created'));
        } catch (\RuntimeException $e) {
            flash_error($e->getMessage());
        }

        $this->redirect(route('api.tokens.index'));
    }

    public function revoke(string $id): void
    {
        $userId = (int) (auth()['id'] ?? 0);

        if ($this->tokenService->revoke((int) $id, $userId)) {
            flash_success(t('api.tokens.flash_revoked'));
        } else {
            flash_error(t('api.tokens.flash_not_found'));
        }

        $this->redirect(route('api.tokens.index'));
    }

    /**
     * Converte la scelta di scadenza (giorni o "never") in un timestamp o null.
     */
    private function resolveExpiry(string $choice): ?string
    {
        $allowed = ['30', '90', '365'];
        if (!in_array($choice, $allowed, true)) {
            return null; // "never" o valore non previsto
        }
        return date('Y-m-d H:i:s', strtotime('+' . (int) $choice . ' days'));
    }
}
