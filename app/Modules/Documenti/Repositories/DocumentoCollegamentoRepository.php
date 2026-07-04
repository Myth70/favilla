<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Repositories;

use App\Repositories\BaseRepository;

class DocumentoCollegamentoRepository extends BaseRepository
{
    protected string $table     = 'documenti_collegamenti';
    protected bool   $timestamps = false;
    protected bool   $auditable  = true;
    protected string $auditEntity = 'documento.collegamento';

    protected array $fillable = [
        'documento_origine_id', 'documento_destinazione_id', 'tipo', 'note', 'created_by',
    ];

    public function findByDocumento(int $documentoId): array
    {
        // Alias titolo_collegato/protocollo_collegato/stato_collegato: nomi attesi
        // dal partial Views/partials/pannello_collegamenti.php (vedi $c['titolo_collegato']).
        $stmt = $this->pdo->prepare(
            'SELECT c.*, d.titolo AS titolo_collegato, d.protocollo AS protocollo_collegato, d.stato AS stato_collegato
             FROM documenti_collegamenti c
             JOIN documenti d ON d.id = c.documento_destinazione_id AND d.deleted_at IS NULL
             WHERE c.documento_origine_id = ?
             ORDER BY c.tipo, c.created_at DESC'
        );
        $stmt->execute([$documentoId]);
        return $stmt->fetchAll();
    }

    public function exists(int $origineId, int $destinazioneId, string $tipo): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM documenti_collegamenti
             WHERE documento_origine_id = ? AND documento_destinazione_id = ? AND tipo = ?'
        );
        $stmt->execute([$origineId, $destinazioneId, $tipo]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM documenti_collegamenti WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function deleteById(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM documenti_collegamenti WHERE id = ?');
        $stmt->execute([$id]);
    }
}
