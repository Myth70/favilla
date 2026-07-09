<?php

declare(strict_types=1);

namespace App\Modules\Api\Http;

use App\Core\Controller;
use App\Modules\Api\Support\ApiRequestContext;

/**
 * Base controller dell'API v1: envelope JSON coerente, paginazione, gate sugli
 * scope e parsing del body JSON. I controller di dominio (Tasks, Contacts, …)
 * la estendono e riusano i Service esistenti.
 *
 * Envelope:
 *   success → { "data": …, "meta"?: { page, per_page, total } }
 *   error   → { "error": { "code", "message", "details"? } }
 */
abstract class ApiController extends Controller
{
    protected function context(): ApiRequestContext
    {
        return app(ApiRequestContext::class);
    }

    /**
     * Utente autenticato via token.
     */
    protected function userId(): int
    {
        return $this->context()->userId();
    }

    /**
     * Richiede che il token+utente possano esercitare il permesso; altrimenti
     * termina con 403 JSON. Gate effettivo = min(permessi utente, scope token).
     */
    protected function requireScope(string $permission): void
    {
        if (!$this->context()->can($permission)) {
            $this->fail('forbidden', 'Permesso o scope insufficiente: ' . $permission, 403);
        }
    }

    /**
     * Risposta di successo con envelope.
     *
     * @param mixed                     $data
     * @param array<string, mixed>|null $meta
     */
    protected function ok(mixed $data, ?array $meta = null, int $status = 200): void
    {
        $payload = ['data' => $data];
        if ($meta !== null) {
            $payload['meta'] = $meta;
        }
        $this->json($payload, $status);
    }

    /**
     * Risposta paginata: calcola il meta a partire dal totale.
     *
     * @param array<int, mixed> $items
     */
    protected function paginated(array $items, int $page, int $perPage, int $total): void
    {
        $this->ok($items, [
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
        ]);
    }

    /**
     * Risposta di errore con envelope.
     *
     * @param array<string, mixed>|null $details
     */
    protected function fail(string $code, string $message, int $status = 400, ?array $details = null): void
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== null) {
            $error['details'] = $details;
        }
        $this->json(['error' => $error], $status);
    }

    /**
     * Body della richiesta come array associativo: JSON (application/json) o,
     * in fallback, i campi $_POST. Il body JSON viene parsato una sola volta.
     *
     * @return array<string, mixed>
     */
    protected function input(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = $raw !== false && $raw !== '' ? json_decode($raw, true) : null;
            $cache = is_array($decoded) ? $decoded : [];
        } else {
            $cache = $_POST;
        }

        return $cache;
    }

    /**
     * Legge un intero di query (page/per_page) con clamp.
     */
    protected function queryInt(string $key, int $default, int $min, int $max): int
    {
        $value = isset($_GET[$key]) ? (int) $_GET[$key] : $default;
        return max($min, min($max, $value));
    }
}
