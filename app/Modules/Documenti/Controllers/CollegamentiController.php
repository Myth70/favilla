<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Controllers;

use App\Core\Controller;
use App\Core\Validator;
use App\Modules\Documenti\Services\CollegamentoService;
use App\Traits\ControllerHelpers;

class CollegamentiController extends Controller
{
    use ControllerHelpers;

    private CollegamentoService $service;

    public function __construct()
    {
        $this->service = app(CollegamentoService::class);
    }

    /**
     * Crea un collegamento bidirezionale tra due documenti.
     */
    public function store(string $docId): void
    {
        $docId  = (int) $docId;
        $user   = auth();
        $userId = (int) $user['id'];

        $clean = $this->cleanPost(['destinazione_id', 'tipo', 'note']);

        $v  = new Validator();
        $ok = $v->validate($clean, [
            'destinazione_id' => 'required',
            'tipo'            => 'required',
        ]);

        if (!$ok) {
            if ($this->isHtmxRequest()) {
                http_response_code(422);
                $this->hxToast(implode(' ', array_merge(...array_values($v->errors()))), 'danger');
                return;
            }
            $this->flashErrors($v->errors(), $clean, 'documenti.show', ['id' => $docId]);
            return;
        }

        try {
            $destinazioneId = (int) $clean['destinazione_id'];
            $this->service->crea($docId, $destinazioneId, $clean['tipo'], $clean['note'] ?: null, $userId);

            if ($this->isHtmxRequest()) {
                $collegamenti = $this->service->elencoPerDocumento($docId);
                $this->hxToast(t('documenti.flash.collegamento_creato'), 'success');
                $this->renderPartial('Documenti/Views/partials/pannello_collegamenti', [
                    'collegamenti' => $collegamenti,
                    'docId'        => $docId,
                ]);
                return;
            }
            flash_success(t('documenti.flash.collegamento_creato'));
        } catch (\Throwable $e) {
            if ($this->isHtmxRequest()) {
                http_response_code(422);
                $this->hxToast($e->getMessage(), 'danger');
                return;
            }
            flash_error($e->getMessage());
        }

        $this->redirect(route('documenti.show', ['id' => $docId]));
    }

    /**
     * Elimina un collegamento (e il suo inverso).
     */
    public function destroy(string $docId, string $collegamentoId): void
    {
        $docId          = (int) $docId;
        $collegamentoId = (int) $collegamentoId;
        try {
            $this->service->rimuovi($collegamentoId, $docId);

            if ($this->isHtmxRequest()) {
                $collegamenti = $this->service->elencoPerDocumento($docId);
                $this->hxToast(t('documenti.flash.collegamento_rimosso'), 'success');
                $this->renderPartial('Documenti/Views/partials/pannello_collegamenti', [
                    'collegamenti' => $collegamenti,
                    'docId'        => $docId,
                ]);
                return;
            }
            flash_success(t('documenti.flash.collegamento_rimosso'));
        } catch (\Throwable $e) {
            if ($this->isHtmxRequest()) {
                http_response_code(422);
                $this->hxToast($e->getMessage(), 'danger');
                return;
            }
            flash_error($e->getMessage());
        }

        $this->redirect(route('documenti.show', ['id' => $docId]));
    }
}
