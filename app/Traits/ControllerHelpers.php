<?php

declare(strict_types=1);

namespace App\Traits;

use App\Modules\Notifications\Services\NotificationService;
use App\Security\Sanitizer;

/**
 * Convenience methods for module controllers.
 *
 * Usage — add to any module controller:
 *   use App\Traits\ControllerHelpers;
 *
 *   class MyController extends \App\Core\Controller {
 *       use ControllerHelpers;
 *       …
 *   }
 */
trait ControllerHelpers
{
    /**
     * Merge new HX-Trigger events with any trigger payload already staged on the response.
     *
     * @param array<string, mixed> $events
     */
    protected function hxTrigger(array $events): void
    {
        $current = [];

        foreach (headers_list() as $header) {
            if (stripos($header, 'HX-Trigger:') !== 0) {
                continue;
            }

            $raw = trim(substr($header, strlen('HX-Trigger:')));
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $current = $decoded;
            }
            break;
        }

        header_remove('HX-Trigger');
        header('HX-Trigger: ' . json_encode(array_merge($current, $events), JSON_UNESCAPED_UNICODE));
    }

    protected function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    protected function isPartialRequest(): bool
    {
        return $this->isHtmxRequest() || $this->isAjaxRequest();
    }

    /**
     * Render a partial view for HTMX requests, or a full view for normal requests.
     *
     * Replaces the boilerplate:
     *   if ($this->isHtmxRequest()) { $this->renderPartial(…); return; }
     *   $this->render(…);
     *
     * @param string $partialPath  Path for HTMX responses    (e.g. 'Items/Views/partials/table').
     * @param string $fullPath     Path for full-page renders (e.g. 'Items/Views/index').
     * @param array  $data         Variables passed to the view.
     */
    protected function htmxOrRender(string $partialPath, string $fullPath, array $data): void
    {
        if ($this->isPartialRequest()) {
            $this->renderPartial($partialPath, $data);
        } else {
            $this->render($fullPath, $data);
        }
    }

    /**
     * Store validation errors and old input in session, then redirect to a named route.
     *
     * Replaces the boilerplate:
     *   $_SESSION['_errors'] = $errors;
     *   $_SESSION['_old']    = $old;
     *   $this->redirect(route('…'));
     *
     * @param array  $errors       Associative array of field → error message.
     * @param array  $old          Previous form values to repopulate the form.
     * @param string $routeName    Named route to redirect to.
     * @param array  $routeParams  Optional route parameters.
     */
    protected function flashErrors(
        array  $errors,
        array  $old,
        string $routeName,
        array  $routeParams = []
    ): void {
        $_SESSION['_errors'] = $errors;
        $_SESSION['_old']    = $old;
        $this->redirect(route($routeName, $routeParams));
    }

    /**
     * Emit an HX-Trigger header to fire a toast notification on the client.
     *
     * Requires the `notify` event listener in app.js (always loaded).
     * Safe to call before any output has been sent.
     *
     * @param string $message  Message text shown in the feedback UI.
     * @param string $type     Bootstrap colour: 'success' | 'danger' | 'warning' | 'info'.
     * @param array  $options  Optional payload fields like title, duration, channel, retryAfter.
     */
    protected function hxToast(string $message, string $type = 'success', array $options = []): void
    {
        $payload = array_merge([
            'message' => $message,
            'type' => $type,
            'channel' => 'toast',
        ], $options);

        $this->hxTrigger([
            'notify' => $payload,
        ]);
    }

    /**
     * Create a persistent in-app notification for the current authenticated user.
     */
    protected function notifyCurrentUser(
        string $title,
        string $body = '',
        string $type = 'info',
        ?string $link = null,
        ?string $icon = null
    ): ?int {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0 || !class_exists(NotificationService::class)) {
            return null;
        }

        try {
            return NotificationService::send($userId, $title, $body, $type, $link, null, $icon);
        } catch (\Throwable $e) {
            app_log('error', '[Feedback] notifyCurrentUser failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Push an updated unread notification count into the current HTMX response.
     */
    protected function hxSyncNotificationBadge(?int $userId = null): void
    {
        $resolvedUserId = $userId ?? (int) ($_SESSION['user_id'] ?? 0);
        if ($resolvedUserId <= 0 || !class_exists(NotificationService::class)) {
            return;
        }

        try {
            $this->hxTrigger([
                'notifCountUpdated' => [
                    'value' => NotificationService::getUnreadCount($resolvedUserId),
                ],
            ]);
        } catch (\Throwable $e) {
            app_log('error', '[Feedback] hxSyncNotificationBadge failed: ' . $e->getMessage());
        }
    }

    /**
     * Read and sanitize POST fields (trim + strip_tags via Sanitizer::clean).
     *
     * @param  array<string> $keys  Field names to extract from $_POST.
     * @return array<string,string> Sanitized values keyed by field name.
     */
    protected function cleanPost(array $keys, int $maxLength = 0): array
    {
        $cleaned = [];
        foreach ($keys as $key) {
            $cleaned[$key] = Sanitizer::clean((string) ($_POST[$key] ?? ''), $maxLength);
        }
        return $cleaned;
    }

    /**
     * Read and sanitize GET fields (trim + strip_tags via Sanitizer::clean).
     *
     * @param array<string> $keys      Field names to extract from $_GET.
     * @param int           $maxLength When > 0, every field is truncated to that many
     *                                 UTF-8 characters. Useful for search filters
     *                                 to prevent DoS via gigantic query strings.
     * @return array<string,string>    Sanitized values keyed by field name.
     */
    protected function cleanGet(array $keys, int $maxLength = 0): array
    {
        $cleaned = [];
        foreach ($keys as $key) {
            $cleaned[$key] = Sanitizer::clean((string) ($_GET[$key] ?? ''), $maxLength);
        }
        return $cleaned;
    }
}
