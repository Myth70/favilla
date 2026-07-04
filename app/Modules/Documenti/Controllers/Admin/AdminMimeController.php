<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Controllers\Admin;

use App\Core\Controller;
use App\Modules\Documenti\Services\DocumentiMimeRegistry;
use App\Traits\ControllerHelpers;

class AdminMimeController extends Controller
{
    use ControllerHelpers;

    public function index(): void
    {
        $user = auth();

        $this->render('Documenti/Views/admin/mime', [
            'title'     => t('documenti.admin.mime_title'),
            'mimeTypes' => $this->buildMimeTypes(),
            'user'      => $user,
        ]);
    }

    public function toggle(string $mime): void
    {
        // Decode slashes in route parameter (e.g. application/pdf → application%2Fpdf)
        $mime = urldecode($mime);

        try {
            $nowEnabled = DocumentiMimeRegistry::toggleMime($mime);
            $msg = $nowEnabled
                ? t('documenti.admin.mime_abilitato', ['mime' => $mime])
                : t('documenti.admin.mime_disabilitato', ['mime' => $mime]);

            if ($this->isHtmxRequest()) {
                $this->hxToast($msg, 'success');
                $this->renderPartial('Documenti/Views/admin/partials/mime_table', [
                    'mimeTypes' => $this->buildMimeTypes(),
                ]);
                return;
            }
            flash_success($msg);
        } catch (\Throwable $e) {
            if ($this->isHtmxRequest()) {
                http_response_code(422);
                $this->hxToast($e->getMessage(), 'danger');
                return;
            }
            flash_error($e->getMessage());
        }

        $this->redirect(route('documenti.admin.mime'));
    }

    /**
     * Righe per la tabella MIME: stato attivo + estensione come label.
     *
     * @return array<int, array{mime:string,abilitato:bool,label:string}>
     */
    private function buildMimeTypes(): array
    {
        $disabled = DocumentiMimeRegistry::disabledMimes();
        $rows = [];
        foreach (DocumentiMimeRegistry::MIMES as $mime => $ext) {
            $rows[] = [
                'mime'      => $mime,
                'abilitato' => !in_array($mime, $disabled, true),
                'label'     => $ext,
            ];
        }
        return $rows;
    }
}
