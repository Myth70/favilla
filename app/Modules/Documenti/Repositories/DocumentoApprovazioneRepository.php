<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Repositories;

use App\Repositories\BaseRepository;

class DocumentoApprovazioneRepository extends BaseRepository
{
    protected string $table     = 'documenti_approvazioni';
    protected bool   $timestamps = false;

    protected array $fillable = [
        'documento_id', 'versione_id', 'step', 'azione', 'user_id', 'note',
    ];

    public function findByDocumento(int $documentoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM documenti_approvazioni WHERE documento_id = ? ORDER BY created_at ASC'
        );
        $stmt->execute([$documentoId]);
        return $stmt->fetchAll();
    }
}
