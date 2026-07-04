<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Controllers\Admin;

use App\Core\Controller;
use App\Modules\Documenti\Services\ProtocolGeneratorService;
use App\Traits\ControllerHelpers;

class AdminSequenzeController extends Controller
{
    use ControllerHelpers;

    private ProtocolGeneratorService $protocolSvc;

    public function __construct()
    {
        $this->protocolSvc = app(ProtocolGeneratorService::class);
    }

    public function index(): void
    {
        $user     = auth();
        $sequenze = $this->protocolSvc->tutteLeSequenze();

        $this->render('Documenti/Views/admin/sequenze', [
            'title'    => t('documenti.admin.sequenze_title'),
            'sequenze' => $sequenze,
            'user'     => $user,
        ]);
    }

    public function reset(string $categoriaId): void
    {
        $categoriaId = (int) $categoriaId;
        $anno = (int) ($_POST['anno'] ?? date('Y'));
        if ($anno < 2000 || $anno > 2100) {
            flash_error(t('documenti.admin.anno_non_valido'));
            $this->redirect(route('documenti.admin.sequenze'));
            return;
        }

        try {
            $this->protocolSvc->azzeraSequenza($categoriaId, $anno);
            if ($this->isHtmxRequest()) {
                $this->hxToast(t('documenti.admin.sequenza_azzerata', ['anno' => $anno]), 'success');
                $sequenze = $this->protocolSvc->tutteLeSequenze();
                $this->renderPartial('Documenti/Views/admin/partials/sequenze_table', [
                    'sequenze' => $sequenze,
                ]);
                return;
            }
            flash_success(t('documenti.admin.sequenza_azzerata', ['anno' => $anno]));
        } catch (\Throwable $e) {
            if ($this->isHtmxRequest()) {
                http_response_code(422);
                $this->hxToast($e->getMessage(), 'danger');
                return;
            }
            flash_error($e->getMessage());
        }

        $this->redirect(route('documenti.admin.sequenze'));
    }
}
