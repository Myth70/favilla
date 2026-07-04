<?php

declare(strict_types=1);

namespace App\Modules\Feedback\Controllers;

use App\Core\Controller;
use App\Modules\Feedback\Services\FeedbackReportBuilder;
use App\Modules\Feedback\Services\FeedbackService;
use App\Traits\ControllerHelpers;

class FeedbackController extends Controller
{
    use ControllerHelpers;

    private FeedbackService $service;

    public function __construct()
    {
        $this->service = app(FeedbackService::class);
    }

    // =====================================================================
    //  Invio universale (Auth + CSRF, nessun permesso)
    // =====================================================================

    /**
     * Riceve una segnalazione. Doppia modalità:
     *  - fetch dall'offcanvas (X-Requested-With) → risposta JSON
     *  - form classico dalla pagina di fallback /nuova → flash + redirect
     */
    public function store(): void
    {
        $isAjax = $this->isPartialRequest();

        $input = $this->cleanPost(['tipo', 'severita', 'titolo']);
        $input['descrizione'] = $this->cleanPost(['descrizione'])['descrizione'];
        $input['passi']       = $this->cleanPost(['passi'])['passi'];

        // Il contesto è dato strutturato: si legge raw e si decodifica (NO strip_tags).
        $clientContext = [];
        $raw = (string) ($_POST['contesto'] ?? '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $clientContext = $decoded;
            }
        }

        // Fallback (pagina d'errore / form classico): contesto minimo dai campi POST.
        if ($clientContext === []) {
            $url = (string) ($_POST['pagina_url'] ?? '');
            $clientContext = [
                'url'        => $url,
                'path'       => $url,
                'origine'    => 'pagina-fallback',
                'from_error' => (string) ($_POST['from'] ?? ''),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ];
        }

        $dom = (string) ($_POST['dom'] ?? '');

        try {
            $result = $this->service->create($input, $clientContext, $dom);
        } catch (\InvalidArgumentException $e) {
            if ($isAjax) {
                $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
                return;
            }
            flash_error($e->getMessage());
            $this->redirect(route('feedback.new'));
            return;
        } catch (\Throwable $e) {
            app_log('error', '[Feedback] store failed: ' . $e->getMessage());
            if ($isAjax) {
                $this->json(['ok' => false, 'message' => t('feedback.flash.save_error')], 500);
                return;
            }
            flash_error(t('feedback.flash.save_error'));
            $this->redirect(route('feedback.new'));
            return;
        }

        $message = t('feedback.flash.sent', ['ref' => $result['ref_code']]);

        if ($isAjax) {
            $this->json([
                'ok'        => true,
                'ref_code'  => $result['ref_code'],
                'duplicate' => $result['duplicate'] ?? false,
                'message'   => $message,
            ]);
            return;
        }

        flash_success($message);
        $base = rtrim((string) config('app.url', ''), '/') . rtrim((string) config('app.base_path', ''), '/') . '/';
        $this->redirect($base);
    }

    /**
     * GET /feedback/new — pagina di segnalazione di fallback.
     * Usata dalle pagine d'errore (404/500): è una pagina sana, anche quando
     * quella originale è rotta. Form classico (funziona senza JS).
     */
    public function reportPage(): void
    {
        $ctx = $this->cleanGet(['from', 'url'], 1000);

        $this->render('Feedback/Views/report', [
            'fromCode'    => $ctx['from'] ?? '',
            'pageUrl'     => $ctx['url'] ?? '',
            'pageTitle'   => t('feedback.report_title'),
            'breadcrumbs' => [['label' => t('feedback.report_title')]],
        ]);
    }

    // =====================================================================
    //  Console admin
    // =====================================================================

    public function index(): void
    {
        $filters = $this->cleanGet(['q', 'stato', 'tipo', 'severita', 'modulo', 'sort', 'dir'], 100);
        $page    = max(1, (int) ($_GET['page'] ?? 1));

        $data = $this->service->list($filters, $page);
        $data['filters']     = $filters;
        $data['pageTitle']   = t('feedback.admin_title');
        $data['breadcrumbs'] = [['label' => t('feedback.admin_title'), 'route' => 'feedback.admin.index']];

        $this->htmxOrRender(
            'Feedback/Views/admin/partials/table',
            'Feedback/Views/admin/index',
            $data
        );
    }

    public function show(string $id): void
    {
        $item = $this->service->getDetail((int) $id);
        if ($item === null) {
            flash_error(t('feedback.flash.not_found'));
            $this->redirect(route('feedback.admin.index'));
            return;
        }

        $this->render('Feedback/Views/admin/show', [
            'item'        => $item,
            'markdown'    => app(FeedbackReportBuilder::class)->toMarkdown($item),
            'assignees'   => $this->service->assignableUsers(),
            'pageTitle'   => t('feedback.admin_title') . ' ' . ($item['ref_code'] ?? ('#' . $item['id'])),
            'breadcrumbs' => [
                ['label' => t('feedback.admin_title'), 'route' => 'feedback.admin.index'],
                ['label' => $item['ref_code'] ?? ('#' . $item['id'])],
            ],
        ]);
    }

    public function export(string $id): void
    {
        $item = $this->service->getDetail((int) $id);
        if ($item === null) {
            http_response_code(404);
            echo e(t('feedback.flash.not_found'));
            return;
        }

        $builder = app(FeedbackReportBuilder::class);
        $ref     = $this->safeFilename($item['ref_code'] ?? ('segnalazione-' . $item['id']));
        $format  = ($_GET['format'] ?? 'md') === 'json' ? 'json' : 'md';

        if ($format === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $ref . '.json"');
            echo json_encode($builder->toArray($item), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        header('Content-Type: text/markdown; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $ref . '.md"');
        echo $builder->toMarkdown($item);
        exit;
    }

    /**
     * GET /feedback/admin/{id}/dom — scarica lo snapshot DOM come file.
     * Servito come allegato (mai inline) per evitare esecuzione di HTML/JS
     * catturato nel contesto/origine dell'app.
     */
    public function dom(string $id): void
    {
        $item = $this->service->getDetail((int) $id);
        if ($item === null || empty($item['dom_snapshot'])) {
            http_response_code(404);
            echo e(t('feedback.flash.dom_unavailable'));
            return;
        }

        $ref = $this->safeFilename($item['ref_code'] ?? ('segnalazione-' . $item['id']));
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $ref . '-dom.html"');
        header('X-Content-Type-Options: nosniff');
        echo (string) $item['dom_snapshot'];
        exit;
    }

    public function triage(string $id): void
    {
        $data = $this->cleanPost(['stato', 'severita', 'note_admin']);
        $data['assegnata_a'] = (int) ($_POST['assegnata_a'] ?? 0);

        try {
            $this->service->triage((int) $id, $data);
            flash_success(t('feedback.flash.updated'));
        } catch (\Throwable $e) {
            app_log('error', '[Feedback] triage failed: ' . $e->getMessage());
            flash_error(t('feedback.flash.update_failed'));
        }

        $this->redirect(route('feedback.admin.show', ['id' => (int) $id]));
    }

    public function destroy(string $id): void
    {
        $this->service->delete((int) $id);
        flash_success(t('feedback.flash.deleted'));
        $this->redirect(route('feedback.admin.index'));
    }

    private function safeFilename(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: 'segnalazione';
    }
}
