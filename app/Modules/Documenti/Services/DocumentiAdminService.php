<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Services;

use App\Modules\Documenti\Repositories\DocumentoRepository;
use App\Modules\Notifications\Services\NotificationService;
use App\Repositories\UserRepository;

/**
 * Operazioni amministrative sui documenti (vista admin):
 * KPI, elenco globale, riassegnazione owner, scadenza pubblicati, cestino.
 */
class DocumentiAdminService
{
    private DocumentoRepository      $docRepo;
    private CategoryTreeService      $catTree;
    private UserRepository           $userRepo;
    private DocumentiStorageService  $storage;
    private \PDO $pdo;

    public function __construct()
    {
        $this->docRepo  = app(DocumentoRepository::class);
        $this->catTree  = app(CategoryTreeService::class);
        $this->userRepo = app(UserRepository::class);
        $this->storage  = app(DocumentiStorageService::class);
        $this->pdo      = app(\PDO::class);
    }

    /**
     * KPI documenti per stato, già indicizzati `stato => totale` per la dashboard.
     *
     * @return array<string,int>
     */
    public function kpiPerStato(): array
    {
        $rows = $this->docRepo->kpiByStato();
        $out  = [];
        foreach ($rows as $row) {
            $out[(string) $row['stato']] = (int) $row['totale'];
        }
        return $out;
    }

    /**
     * Elenco amministrativo paginato + dati di contorno (categorie, utenti assegnabili).
     *
     * @return array{result:array,categorie:array,users:array}
     */
    public function elencoAdmin(array $filters): array
    {
        $result = $this->docRepo->listPaginated($filters, true);
        $result['items'] = $result['data'] ?? [];

        return [
            'result'    => $result,
            'categorie' => $this->catTree->alberoOrdinato(),
            'users'     => $this->utentiAssegnabili(),
        ];
    }

    /**
     * Utenti selezionabili come owner (id + name).
     *
     * @return array<int,array<string,mixed>>
     */
    public function utentiAssegnabili(): array
    {
        return $this->userRepo->all();
    }

    /**
     * Riassegna l'owner di un documento.
     *
     * @throws \RuntimeException
     */
    public function riassegnaOwner(int $id, int $nuovoOwnerId, int $actorId): void
    {
        if ($nuovoOwnerId <= 0) {
            throw new \RuntimeException(t('documenti.exception.utente_non_valido'));
        }
        if (!$this->userRepo->find($nuovoOwnerId)) {
            throw new \RuntimeException(t('documenti.exception.utente_destinatario_inesistente'));
        }
        if (!$this->docRepo->find($id)) {
            throw new \RuntimeException(t('documenti.exception.documento_non_trovato'));
        }

        // Update via repository per audit log + updated_by.
        $this->docRepo->update($id, [
            'owner_user_id' => $nuovoOwnerId,
            'updated_by'    => $actorId,
        ]);
    }

    /**
     * Porta in stato 'scaduto' i documenti pubblicati con scadenza passata,
     * notificando l'owner. Ritorna il numero di documenti aggiornati.
     * Sorgente unica condivisa da pannello admin e CLI `documenti:expire-published`.
     *
     * @param int $actorId  Utente che esegue (0 = contesto di sistema/CLI)
     */
    public function scadiPubblicati(int $actorId = 0): int
    {
        $expired = $this->docRepo->findPublishedExpired();
        $count   = 0;

        foreach ($expired as $doc) {
            // Update via repository per audit log + updated_by.
            $this->docRepo->update((int) $doc['id'], [
                'stato'      => 'scaduto',
                'updated_by' => $actorId,
            ]);

            try {
                NotificationService::dispatchEventToUser(
                    'documenti.scaduto',
                    'Documenti',
                    (int) $doc['owner_user_id'],
                    [
                        'documento_id'     => (int) $doc['id'],
                        'documento_titolo' => $doc['titolo'] ?? '',
                    ],
                    route('documenti.show', ['id' => $doc['id']])
                );
            } catch (\Throwable) {
                // notifiche non bloccanti
            }
            $count++;
        }

        return $count;
    }

    /**
     * Documenti nel cestino (soft-deleted), paginati, con owner_name risolto.
     *
     * @return array{items:array,total:int,page:int,perPage:int}
     */
    public function cestino(string $q, int $page): array
    {
        $like    = '%' . $q . '%';
        $perPage = 20;
        $page    = max(1, $page);
        $offset  = ($page - 1) * $perPage;

        $countStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM documenti WHERE deleted_at IS NOT NULL AND titolo LIKE ?'
        );
        $countStmt->execute([$like]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            'SELECT * FROM documenti
             WHERE deleted_at IS NOT NULL AND titolo LIKE ?
             ORDER BY deleted_at DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->bindValue(1, $like, \PDO::PARAM_STR);
        $stmt->bindValue(2, $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $ownerIds = array_values(array_unique(array_filter(
            array_map(static fn ($it) => (int) ($it['owner_user_id'] ?? 0), $items)
        )));
        $ownerMap = $this->userRepo->findManyByIds($ownerIds);
        foreach ($items as &$it) {
            $row = $ownerMap[(int) $it['owner_user_id']] ?? null;
            $it['owner_name'] = $row['name'] ?? null;
        }
        unset($it);

        return ['items' => $items, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }

    /**
     * Ripristina un documento dal cestino.
     *
     * @throws \RuntimeException
     */
    public function ripristinaDalCestino(int $id, int $actorId): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM documenti WHERE id = ? AND deleted_at IS NOT NULL');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            throw new \RuntimeException(t('documenti.exception.documento_non_nel_cestino'));
        }

        // deleted_at non è in $fillable (whitelist di update()): restore() lo
        // gestisce direttamente, bypassando il filtro.
        $this->docRepo->restore($id);
        $this->docRepo->update($id, ['updated_by' => $actorId]);
    }

    /**
     * Cancella definitivamente un documento dal cestino: record (versioni in cascade)
     * e file fisici associati.
     *
     * @throws \RuntimeException
     */
    public function purgaDefinitivo(int $id): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM documenti WHERE id = ? AND deleted_at IS NOT NULL');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            throw new \RuntimeException(t('documenti.exception.documento_non_nel_cestino'));
        }

        // Raccoglie i file_id PRIMA della cancellazione (le versioni vanno via in cascade).
        $stmt = $this->pdo->prepare('SELECT DISTINCT file_id FROM documenti_versioni WHERE documento_id = ?');
        $stmt->execute([$id]);
        $fileIds = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));

        $this->docRepo->forceDelete($id);

        foreach ($fileIds as $fid) {
            try {
                $this->storage->cleanup($fid);
            } catch (\Throwable) {
                // best-effort; il job documenti:cleanup-orphans recupera il resto.
            }
        }
    }
}
