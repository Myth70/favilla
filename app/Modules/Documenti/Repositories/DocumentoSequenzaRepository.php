<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Repositories;

use App\Repositories\BaseRepository;

class DocumentoSequenzaRepository extends BaseRepository
{
    protected string $table     = 'documenti_protocollo_sequenze';
    protected bool   $timestamps = false;

    /**
     * Incrementa il contatore e restituisce il nuovo numero.
     * Deve essere chiamato dentro una transaction (letto-modifica-scrivo,
     * non atomico da solo): read-then-write invece dell'upsert MySQL
     * `ON DUPLICATE KEY UPDATE`, che non ha equivalente su SQLite.
     *
     * La SELECT usa `FOR UPDATE` (lock pessimistico di riga) per impedire che
     * due richieste concorrenti leggano lo stesso `ultimo_numero` e generino
     * protocolli duplicati — critico per un numero di protocollo legale.
     * `FOR UPDATE` non è supportato da SQLite: omesso sotto test, dove le
     * scritture sono già serializzate a livello di connessione (vedi
     * DocumentoRepository::findForUpdate() per lo stesso pattern).
     */
    public function incrementAndGet(int $categoriaId, int $anno): int
    {
        $forUpdate = $this->isSqlite() ? '' : ' FOR UPDATE';
        $stmt = $this->pdo->prepare(
            "SELECT ultimo_numero FROM documenti_protocollo_sequenze WHERE categoria_id = ? AND anno = ?{$forUpdate}"
        );
        $stmt->execute([$categoriaId, $anno]);
        $current = $stmt->fetchColumn();

        if ($current === false) {
            $this->pdo->prepare(
                'INSERT INTO documenti_protocollo_sequenze (categoria_id, anno, ultimo_numero) VALUES (?, ?, 1)'
            )->execute([$categoriaId, $anno]);
            return 1;
        }

        $nuovoNumero = (int) $current + 1;
        $this->pdo->prepare(
            'UPDATE documenti_protocollo_sequenze SET ultimo_numero = ? WHERE categoria_id = ? AND anno = ?'
        )->execute([$nuovoNumero, $categoriaId, $anno]);
        return $nuovoNumero;
    }

    private function isSqlite(): bool
    {
        return $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    public function allSequenze(): array
    {
        $stmt = $this->pdo->query(
            'SELECT s.*, c.nome AS categoria_nome, c.codice AS categoria_codice
             FROM documenti_protocollo_sequenze s
             JOIN documenti_categorie c ON c.id = s.categoria_id
             ORDER BY s.anno DESC, c.nome'
        );
        return $stmt->fetchAll();
    }

    public function resetSequenza(int $categoriaId, int $anno): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM documenti_protocollo_sequenze WHERE categoria_id = ? AND anno = ?'
        );
        $stmt->execute([$categoriaId, $anno]);
    }
}
