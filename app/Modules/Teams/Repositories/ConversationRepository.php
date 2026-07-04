<?php

declare(strict_types=1);

namespace App\Modules\Teams\Repositories;

use App\Repositories\BaseRepository;

class ConversationRepository extends BaseRepository
{
    protected string $table = 'teams_conversations';

    /**
     * Lista conversazioni con messaggi non letti per l'utente.
     */
    public function listUnreadForUser(int $userId, int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));

        $stmt = $this->pdo->prepare(" 
            SELECT
                c.id, c.type, c.name, c.description, c.avatar_path, c.archived_at, c.created_at,
                cm.role AS my_role, cm.notifications_muted, cm.last_read_at, cm.hidden_at,
                lm.id AS last_message_id, lm.body AS last_message_body,
                lm.user_id AS last_message_user_id, lm.created_at AS last_message_at,
                lm.type AS last_message_type, lm.deleted_at AS last_message_deleted,
                lm_user.name AS last_message_user_name,
                (
                    SELECT COUNT(*) FROM teams_messages um
                    WHERE um.conversation_id = c.id
                      AND um.created_at > COALESCE(cm.last_read_at, '1970-01-01')
                      AND um.deleted_at IS NULL
                      AND um.user_id != ?
                ) AS unread_count,
                ou.id AS other_user_id, ou.name AS other_user_name,
                ou.avatar_path AS other_user_avatar
            FROM teams_conversations c
            INNER JOIN teams_conversation_members cm
                ON cm.conversation_id = c.id AND cm.user_id = ? AND cm.left_at IS NULL
            LEFT JOIN teams_messages lm
                ON lm.id = (
                    SELECT m2.id FROM teams_messages m2
                    WHERE m2.conversation_id = c.id AND m2.deleted_at IS NULL
                    ORDER BY m2.created_at DESC LIMIT 1
                )
            LEFT JOIN users lm_user ON lm_user.id = lm.user_id
            LEFT JOIN teams_conversation_members ocm
                ON ocm.conversation_id = c.id AND ocm.user_id != ? AND c.type = 'direct' AND ocm.left_at IS NULL
            LEFT JOIN users ou ON ou.id = ocm.user_id
            WHERE c.archived_at IS NULL
              AND cm.hidden_at IS NULL
            HAVING unread_count > 0
            ORDER BY COALESCE(lm.created_at, c.created_at) DESC
            LIMIT ?
        ");

        $stmt->execute([$userId, $userId, $userId, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Lista conversazioni per un utente con ultimo messaggio e conteggio non letti.
     * Ordinate per ultimo messaggio DESC.
     */
    public function listForUser(int $userId, string $search = '', bool $showHidden = false): array
    {
        $params = [$userId, $userId, $userId];
        $searchSql = '';
        $hiddenSql = $showHidden ? '' : 'AND cm.hidden_at IS NULL';

        if ($search !== '') {
            $searchSql = 'AND (c.name LIKE ? OR ou.name LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "
            SELECT
                c.id, c.type, c.name, c.description, c.avatar_path, c.archived_at, c.created_at,
                cm.role AS my_role, cm.notifications_muted, cm.last_read_at, cm.hidden_at,
                lm.id AS last_message_id, lm.body AS last_message_body,
                lm.user_id AS last_message_user_id, lm.created_at AS last_message_at,
                lm.type AS last_message_type, lm.deleted_at AS last_message_deleted,
                lm_user.name AS last_message_user_name,
                (
                    SELECT COUNT(*) FROM teams_messages um
                    WHERE um.conversation_id = c.id
                      AND um.created_at > COALESCE(cm.last_read_at, '1970-01-01')
                      AND um.deleted_at IS NULL
                      AND um.user_id != ?
                ) AS unread_count,
                ou.id AS other_user_id, ou.name AS other_user_name,
                ou.avatar_path AS other_user_avatar
            FROM teams_conversations c
            INNER JOIN teams_conversation_members cm
                ON cm.conversation_id = c.id AND cm.user_id = ? AND cm.left_at IS NULL
            LEFT JOIN teams_messages lm
                ON lm.id = (
                    SELECT m2.id FROM teams_messages m2
                    WHERE m2.conversation_id = c.id AND m2.deleted_at IS NULL
                    ORDER BY m2.created_at DESC LIMIT 1
                )
            LEFT JOIN users lm_user ON lm_user.id = lm.user_id
            LEFT JOIN teams_conversation_members ocm
                ON ocm.conversation_id = c.id AND ocm.user_id != ? AND c.type = 'direct' AND ocm.left_at IS NULL
            LEFT JOIN users ou ON ou.id = ocm.user_id
            WHERE c.archived_at IS NULL
            {$hiddenSql}
            {$searchSql}
            ORDER BY COALESCE(lm.created_at, c.created_at) DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Lista conv dell'utente "dirty" dopo $since? Usato dal polling unificato
     * per decidere se emettere `HX-Trigger: teamsConvRefresh`.
     *
     * Riusa `teams_conversations.updated_at` (touchato da ON UPDATE +
     * `touchConversationUpdatedAt` per gli eventi di membership).
     */
    public function hasUpdatesForUserSince(int $userId, string $since): bool
    {
        if ($since === '') {
            return false;
        }
        $stmt = $this->pdo->prepare('
            SELECT 1
            FROM teams_conversations c
            INNER JOIN teams_conversation_members cm
                ON cm.conversation_id = c.id
               AND cm.user_id = ?
               AND cm.left_at IS NULL
            WHERE c.updated_at > ?
            LIMIT 1
        ');
        $stmt->execute([$userId, $since]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Trova una conversazione con i suoi dati, verificando la membership dell'utente.
     */
    public function findForUser(int $conversationId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*, cm.role AS my_role, cm.notifications_muted, cm.last_read_at,
                   ou.id AS other_user_id, ou.name AS other_user_name,
                   ou.avatar_path AS other_user_avatar
            FROM teams_conversations c
            INNER JOIN teams_conversation_members cm
                ON cm.conversation_id = c.id AND cm.user_id = ? AND cm.left_at IS NULL
            LEFT JOIN teams_conversation_members ocm
                ON ocm.conversation_id = c.id AND ocm.user_id != ? AND c.type = 'direct' AND ocm.left_at IS NULL
            LEFT JOIN users ou ON ou.id = ocm.user_id
            WHERE c.id = ?
        ");
        $stmt->execute([$userId, $userId, $conversationId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Header del gruppo per l'offcanvas "Info gruppo": dati base + nome
     * del creatore + numero membri attivi. Null se l'utente non è membro
     * (filtro autorizzativo identico a findForUser).
     *
     * @return array{
     *   id:int, name:?string, description:?string, avatar_path:?string,
     *   created_at:string, created_by:?int, creator_name:?string,
     *   member_count:int, my_role:string, notifications_muted:int
     * }|null
     */
    public function getGroupHeaderInfo(int $conversationId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.id, c.name, c.description, c.avatar_path,
                   c.created_at, c.created_by,
                   creator.name AS creator_name,
                   cm.role AS my_role, cm.notifications_muted,
                   (
                       SELECT COUNT(*) FROM teams_conversation_members mc
                       WHERE mc.conversation_id = c.id AND mc.left_at IS NULL
                   ) AS member_count
            FROM teams_conversations c
            INNER JOIN teams_conversation_members cm
                ON cm.conversation_id = c.id AND cm.user_id = ? AND cm.left_at IS NULL
            LEFT JOIN users creator ON creator.id = c.created_by
            WHERE c.id = ? AND c.type = 'group'
            LIMIT 1
        ");
        $stmt->execute([$userId, $conversationId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Trova una conversazione direct esistente tra due utenti.
     */
    public function findDirectBetween(int $userId1, int $userId2): ?int
    {
        $stmt = $this->pdo->prepare("
            SELECT c.id
            FROM teams_conversations c
            INNER JOIN teams_conversation_members cm1
                ON cm1.conversation_id = c.id AND cm1.user_id = ? AND cm1.left_at IS NULL
            INNER JOIN teams_conversation_members cm2
                ON cm2.conversation_id = c.id AND cm2.user_id = ? AND cm2.left_at IS NULL
            WHERE c.type = 'direct' AND c.archived_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$userId1, $userId2]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    /**
     * Crea una conversazione con membri. Usa transazione.
     *
     * @return int ID della nuova conversazione
     */
    public function createWithMembers(array $convData, array $memberIds, int $creatorId): int
    {
        return $this->transaction(function () use ($convData, $memberIds, $creatorId) {
            $convId = $this->create($convData);

            // Aggiungi il creatore come admin
            $this->addMember($convId, $creatorId, 'admin');

            // Aggiungi gli altri membri
            foreach ($memberIds as $memberId) {
                $memberId = (int) $memberId;
                if ($memberId !== $creatorId) {
                    $this->addMember($convId, $memberId, 'member');
                }
            }

            return $convId;
        });
    }

    /**
     * Conteggio globale non letti per un utente (tutte le conversazioni).
     */
    public function getGlobalUnreadCount(int $userId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(sub.cnt), 0)
            FROM (
                SELECT COUNT(*) AS cnt
                FROM teams_messages m
                INNER JOIN teams_conversation_members cm
                    ON cm.conversation_id = m.conversation_id
                    AND cm.user_id = ? AND cm.left_at IS NULL
                INNER JOIN teams_conversations c
                    ON c.id = m.conversation_id AND c.archived_at IS NULL
                WHERE m.created_at > COALESCE(cm.last_read_at, '1970-01-01')
                  AND m.deleted_at IS NULL
                  AND m.user_id != ?
            ) sub
        ");
        $stmt->execute([$userId, $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Aggiorna last_read_at per un utente in una conversazione.
     *
     * Effetto secondario per il polling: tocca `teams_messages.state_updated_at`
     * di tutti i messaggi NON dell'utente corrente creati dopo il vecchio
     * `last_read_at`. Così l'autore di quei messaggi al prossimo poll vedrà
     * aggiornata la spunta di lettura (✓ → ✓✓) senza F5.
     */
    public function markAsRead(int $conversationId, int $userId): void
    {
        // 1. Leggi il vecchio last_read_at per filtrare il touch
        $selStmt = $this->pdo->prepare(
            'SELECT last_read_at FROM teams_conversation_members
             WHERE conversation_id = ? AND user_id = ?'
        );
        $selStmt->execute([$conversationId, $userId]);
        $oldLastReadAt = $selStmt->fetchColumn();
        $oldLastReadAt = $oldLastReadAt === false ? null : $oldLastReadAt;

        // 2. Aggiorna last_read_at
        $stmt = $this->pdo->prepare('
            UPDATE teams_conversation_members
            SET last_read_at = NOW()
            WHERE conversation_id = ? AND user_id = ?
        ');
        $stmt->execute([$conversationId, $userId]);

        // 3. Tocca lo state dei messaggi appena letti (non miei, deleted_at IS NULL)
        if ($oldLastReadAt) {
            $touch = $this->pdo->prepare(
                'UPDATE teams_messages
                 SET state_updated_at = CURRENT_TIMESTAMP
                 WHERE conversation_id = ? AND user_id <> ?
                   AND deleted_at IS NULL AND created_at > ?'
            );
            $touch->execute([$conversationId, $userId, $oldLastReadAt]);
        } else {
            // Prima lettura della conversazione: tocca tutti i messaggi non miei
            $touch = $this->pdo->prepare(
                'UPDATE teams_messages
                 SET state_updated_at = CURRENT_TIMESTAMP
                 WHERE conversation_id = ? AND user_id <> ?
                   AND deleted_at IS NULL'
            );
            $touch->execute([$conversationId, $userId]);
        }
    }

    /**
     * Membri attivi di una conversazione con dati utente.
     */
    public function getActiveMembers(int $conversationId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT u.id, u.name, u.email, u.avatar_path, cm.role, cm.joined_at
            FROM teams_conversation_members cm
            INNER JOIN users u ON u.id = cm.user_id
            WHERE cm.conversation_id = ? AND cm.left_at IS NULL
            ORDER BY cm.role ASC, u.name ASC
        ');
        $stmt->execute([$conversationId]);
        return $stmt->fetchAll();
    }

    /**
     * IDs dei membri attivi (per notifiche).
     */
    public function getActiveMemberIds(int $conversationId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT user_id FROM teams_conversation_members
            WHERE conversation_id = ? AND left_at IS NULL
        ');
        $stmt->execute([$conversationId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Membri attivi con stato mute (per notifiche batch, evita N+1).
     */
    public function getActiveMembersWithMuteStatus(int $conversationId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT user_id, notifications_muted
            FROM teams_conversation_members
            WHERE conversation_id = ? AND left_at IS NULL
        ');
        $stmt->execute([$conversationId]);
        return $stmt->fetchAll();
    }

    /**
     * Record di un singolo membro in una conversazione.
     */
    public function getMemberRecord(int $conversationId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM teams_conversation_members
            WHERE conversation_id = ? AND user_id = ?
        ');
        $stmt->execute([$conversationId, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Aggiunge un membro a una conversazione.
     */
    public function addMember(int $conversationId, int $userId, string $role = 'member'): void
    {
        if (!in_array($role, ['admin', 'member'], true)) {
            throw new \InvalidArgumentException("Ruolo non valido: {$role}");
        }

        // Controlla se esiste gia' (potrebbe aver lasciato e rientrare)
        $existing = $this->getMemberRecord($conversationId, $userId);
        if ($existing) {
            // Reinserisce: azzera left_at
            $stmt = $this->pdo->prepare('
                UPDATE teams_conversation_members
                SET left_at = NULL, role = ?, joined_at = NOW()
                WHERE conversation_id = ? AND user_id = ?
            ');
            $stmt->execute([$role, $conversationId, $userId]);
        } else {
            $stmt = $this->pdo->prepare('
                INSERT INTO teams_conversation_members (conversation_id, user_id, role)
                VALUES (?, ?, ?)
            ');
            $stmt->execute([$conversationId, $userId, $role]);
        }
        $this->touchConversationUpdatedAt($conversationId);
    }

    /**
     * Rimuove un membro (soft: imposta left_at).
     */
    public function removeMember(int $conversationId, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE teams_conversation_members
            SET left_at = NOW()
            WHERE conversation_id = ? AND user_id = ?
        ');
        $stmt->execute([$conversationId, $userId]);
        $this->touchConversationUpdatedAt($conversationId);
    }

    /**
     * Archivia una conversazione.
     */
    public function archive(int $conversationId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE teams_conversations SET archived_at = NOW() WHERE id = ?
        ');
        $stmt->execute([$conversationId]);
    }

    /**
     * Toggle mute per un utente in una conversazione.
     * Restituisce il nuovo stato.
     */
    public function toggleMute(int $conversationId, int $userId): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE teams_conversation_members
            SET notifications_muted = NOT notifications_muted
            WHERE conversation_id = ? AND user_id = ?
        ');
        $stmt->execute([$conversationId, $userId]);

        $stmt2 = $this->pdo->prepare('
            SELECT notifications_muted FROM teams_conversation_members
            WHERE conversation_id = ? AND user_id = ?
        ');
        $stmt2->execute([$conversationId, $userId]);
        $this->touchConversationUpdatedAt($conversationId);
        return (bool) $stmt2->fetchColumn();
    }

    /**
     * Conta i membri admin attivi di una conversazione.
     */
    public function countAdmins(int $conversationId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM teams_conversation_members
            WHERE conversation_id = ? AND role = 'admin' AND left_at IS NULL
        ");
        $stmt->execute([$conversationId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Promuovi il membro piu' anziano ad admin.
     * Restituisce l'ID dell'utente promosso, o null se nessun membro disponibile.
     */
    public function promoteOldestMember(int $conversationId): ?int
    {
        $stmt = $this->pdo->prepare("
            SELECT user_id FROM teams_conversation_members
            WHERE conversation_id = ? AND left_at IS NULL AND role = 'member'
            ORDER BY joined_at ASC
            LIMIT 1
        ");
        $stmt->execute([$conversationId]);
        $userId = $stmt->fetchColumn();

        if (!$userId) {
            return null;
        }

        $update = $this->pdo->prepare("
            UPDATE teams_conversation_members
            SET role = 'admin'
            WHERE conversation_id = ? AND user_id = ?
        ");
        $update->execute([$conversationId, $userId]);

        return (int) $userId;
    }

    /**
     * Nascondi una conversazione per un utente.
     */
    public function hideConversation(int $conversationId, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE teams_conversation_members
            SET hidden_at = NOW()
            WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL
        ');
        $stmt->execute([$conversationId, $userId]);
        $this->touchConversationUpdatedAt($conversationId);
    }

    /**
     * Mostra una conversazione nascosta per un utente.
     */
    public function unhideConversation(int $conversationId, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE teams_conversation_members
            SET hidden_at = NULL
            WHERE conversation_id = ? AND user_id = ?
        ');
        $stmt->execute([$conversationId, $userId]);
        $this->touchConversationUpdatedAt($conversationId);
    }

    /**
     * Rimuovi lo stato nascosto per tutti i membri (quando arriva un nuovo messaggio).
     */
    public function unhideForAllMembers(int $conversationId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE teams_conversation_members
            SET hidden_at = NULL
            WHERE conversation_id = ? AND hidden_at IS NOT NULL
        ');
        $stmt->execute([$conversationId]);
    }

    /**
     * MAX last_read_at degli altri membri (per determinare se un messaggio e' stato letto).
     */
    public function getOthersMaxReadAt(int $conversationId, int $excludeUserId): ?string
    {
        $stmt = $this->pdo->prepare('
            SELECT MAX(last_read_at)
            FROM teams_conversation_members
            WHERE conversation_id = ? AND user_id != ? AND left_at IS NULL
        ');
        $stmt->execute([$conversationId, $excludeUserId]);
        $result = $stmt->fetchColumn();
        return $result ?: null;
    }

    /**
     * Controlla se qualche altro membro ha letto un messaggio (last_read_at >= msg created_at).
     */
    public function isMessageReadByOthers(int $conversationId, int $senderUserId, string $messageCreatedAt): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM teams_conversation_members
            WHERE conversation_id = ? AND user_id != ? AND left_at IS NULL
              AND last_read_at >= ?
        ');
        $stmt->execute([$conversationId, $senderUserId, $messageCreatedAt]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Conta quanti altri membri hanno letto un messaggio specifico.
     */
    public function countReadBy(int $conversationId, int $senderUserId, string $messageCreatedAt): int
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM teams_conversation_members
            WHERE conversation_id = ? AND user_id != ? AND left_at IS NULL
              AND last_read_at >= ?
        ');
        $stmt->execute([$conversationId, $senderUserId, $messageCreatedAt]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Batch: per ogni timestamp in $messageCreatedAts, restituisce quanti altri
     * membri della conversazione hanno `last_read_at >= timestamp`.
     *
     * Una sola query carica i `last_read_at` dei membri non-mittente, poi il
     * conteggio per ciascun timestamp è O(M*N) in memoria (M messaggi × N
     * membri, tipicamente < 100×30). Sostituisce le N chiamate a `countReadBy`
     * in loop nelle view e nel polling.
     *
     * @param string[] $messageCreatedAts
     * @return array<string, int> mappa created_at → count
     */
    public function countReadByForMessages(int $conversationId, int $senderUserId, array $messageCreatedAts): array
    {
        if (empty($messageCreatedAts)) {
            return [];
        }
        $stmt = $this->pdo->prepare('
            SELECT last_read_at
            FROM teams_conversation_members
            WHERE conversation_id = ? AND user_id != ? AND left_at IS NULL
              AND last_read_at IS NOT NULL
        ');
        $stmt->execute([$conversationId, $senderUserId]);
        $reads = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $result = [];
        foreach ($messageCreatedAts as $ts) {
            $tsKey = (string) $ts;
            $count = 0;
            foreach ($reads as $r) {
                if ($r >= $tsKey) {
                    $count++;
                }
            }
            $result[$tsKey] = $count;
        }
        return $result;
    }

    /**
     * Restituisce gli utenti (diversi dal mittente) che hanno letto un messaggio,
     * con avatar e timestamp di lettura. Letto = last_read_at >= messageCreatedAt.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getReadersForMessage(int $conversationId, int $senderUserId, string $messageCreatedAt): array
    {
        $stmt = $this->pdo->prepare('
            SELECT u.id AS user_id, u.name, u.avatar_path, m.last_read_at
            FROM teams_conversation_members m
            JOIN users u ON u.id = m.user_id
            WHERE m.conversation_id = ?
              AND m.user_id != ?
              AND m.left_at IS NULL
              AND m.last_read_at IS NOT NULL
              AND m.last_read_at >= ?
            ORDER BY m.last_read_at ASC
        ');
        $stmt->execute([$conversationId, $senderUserId, $messageCreatedAt]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    // ── Metodi Admin ──────────────────────────────────────────────

    /**
     * Statistiche aggregate per il pannello admin.
     */
    public function adminStats(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN archived_at IS NULL THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN archived_at IS NOT NULL THEN 1 ELSE 0 END) AS archived,
                SUM(CASE WHEN type = 'direct' THEN 1 ELSE 0 END) AS direct,
                SUM(CASE WHEN type = 'group' THEN 1 ELSE 0 END) AS `group`
            FROM teams_conversations
        ");
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $row = $row ?: ['total' => 0, 'active' => 0, 'archived' => 0, 'direct' => 0, 'group' => 0];

        $msgStmt = $this->pdo->prepare('SELECT COUNT(*) FROM teams_messages WHERE deleted_at IS NULL');
        $msgStmt->execute();
        $row['total_messages'] = (int) $msgStmt->fetchColumn();

        // Soglia calcolata lato PHP (non `DATE_SUB(NOW(), INTERVAL ...)`,
        // sintassi MySQL-only non portabile su SQLite).
        $onlineStmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM teams_user_presence
            WHERE last_seen_at > ?
        ');
        $onlineStmt->execute([date('Y-m-d H:i:s', time() - 30)]);
        $row['online_now'] = (int) $onlineStmt->fetchColumn();

        return $row;
    }

    /**
     * Lista paginata conversazioni per l'admin (NESSUN body messaggi).
     */
    public function adminList(string $search, string $filter, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [];
        $whereClauses = [];

        if ($filter === 'active') {
            $whereClauses[] = 'c.archived_at IS NULL';
        } elseif ($filter === 'archived') {
            $whereClauses[] = 'c.archived_at IS NOT NULL';
        } elseif ($filter === 'direct') {
            $whereClauses[] = "c.type = 'direct'";
        } elseif ($filter === 'group') {
            $whereClauses[] = "c.type = 'group'";
        }

        if ($search !== '') {
            $whereClauses[] = '(c.name LIKE ? OR member_names_sub.names LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
        $params[] = $perPage;
        $params[] = $offset;

        $stmt = $this->pdo->prepare("
            SELECT
                c.id, c.type, c.name, c.archived_at, c.created_at, c.updated_at,
                (
                    SELECT COUNT(*)
                    FROM teams_conversation_members cm
                    WHERE cm.conversation_id = c.id AND cm.left_at IS NULL
                ) AS member_count,
                (
                    SELECT COUNT(*)
                    FROM teams_messages m
                    WHERE m.conversation_id = c.id AND m.deleted_at IS NULL
                ) AS message_count,
                member_names_sub.names AS member_names
            FROM teams_conversations c
            LEFT JOIN (
                SELECT cm2.conversation_id,
                       GROUP_CONCAT(u.name ORDER BY cm2.joined_at ASC SEPARATOR ', ') AS names
                FROM teams_conversation_members cm2
                INNER JOIN users u ON u.id = cm2.user_id
                WHERE cm2.left_at IS NULL
                GROUP BY cm2.conversation_id
            ) AS member_names_sub ON member_names_sub.conversation_id = c.id
            {$whereSQL}
            ORDER BY c.updated_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Conta conversazioni per l'admin (per paginazione).
     */
    public function adminCount(string $search, string $filter): int
    {
        $params = [];
        $whereClauses = [];

        if ($filter === 'active') {
            $whereClauses[] = 'c.archived_at IS NULL';
        } elseif ($filter === 'archived') {
            $whereClauses[] = 'c.archived_at IS NOT NULL';
        } elseif ($filter === 'direct') {
            $whereClauses[] = "c.type = 'direct'";
        } elseif ($filter === 'group') {
            $whereClauses[] = "c.type = 'group'";
        }

        if ($search !== '') {
            $whereClauses[] = '(c.name LIKE ? OR member_names_sub.names LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM teams_conversations c
            LEFT JOIN (
                SELECT cm2.conversation_id,
                       GROUP_CONCAT(u.name ORDER BY cm2.joined_at ASC SEPARATOR ', ') AS names
                FROM teams_conversation_members cm2
                INNER JOIN users u ON u.id = cm2.user_id
                WHERE cm2.left_at IS NULL
                GROUP BY cm2.conversation_id
            ) AS member_names_sub ON member_names_sub.conversation_id = c.id
            {$whereSQL}
        ");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Cerca utenti non ancora membri di una conversazione.
     */
    public function searchUsersNotInConversation(string $like, int $excludeUserId, int $excludeConvId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT u.id, u.name, u.email, u.avatar_path
            FROM users u
            WHERE (u.name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)
              AND u.is_active = 1 AND u.deleted_at IS NULL
              AND u.id != ?
              AND u.id NOT IN (
                  SELECT cm.user_id FROM teams_conversation_members cm
                  WHERE cm.conversation_id = ? AND cm.left_at IS NULL
              )
            ORDER BY u.name ASC
            LIMIT 20
        ');
        $stmt->execute([$like, $like, $like, $excludeUserId, $excludeConvId]);
        return $stmt->fetchAll();
    }

    /**
     * Cerca utenti attivi per nuova conversazione.
     */
    public function searchUsers(string $like, int $excludeUserId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT u.id, u.name, u.email, u.avatar_path
            FROM users u
            WHERE (u.name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)
              AND u.is_active = 1 AND u.deleted_at IS NULL
              AND u.id != ?
            ORDER BY u.name ASC
            LIMIT 20
        ');
        $stmt->execute([$like, $like, $like, $excludeUserId]);
        return $stmt->fetchAll();
    }

    /**
     * Membri attivi della conversazione che matchano $like, per autocomplete @mention.
     * Esclude l'utente corrente e i membri che hanno lasciato la conversazione.
     *
     * @return array<int, array{id:int, name:string, username:?string, avatar_path:?string}>
     */
    public function getMembersForAutocomplete(int $conversationId, string $like, int $excludeUserId, int $limit = 8): array
    {
        $likeParam = '%' . $like . '%';
        $stmt = $this->pdo->prepare('
            SELECT u.id, u.name, u.username, u.avatar_path
            FROM teams_conversation_members m
            JOIN users u ON u.id = m.user_id
            WHERE m.conversation_id = ?
              AND m.left_at IS NULL
              AND u.is_active = 1 AND u.deleted_at IS NULL
              AND u.id != ?
              AND (u.name LIKE ? OR u.username LIKE ?)
            ORDER BY u.name ASC
            LIMIT ?
        ');
        $stmt->bindValue(1, $conversationId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $excludeUserId, \PDO::PARAM_INT);
        $stmt->bindValue(3, $likeParam, \PDO::PARAM_STR);
        $stmt->bindValue(4, $likeParam, \PDO::PARAM_STR);
        $stmt->bindValue(5, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Trova tra i membri attivi della conversazione gli utenti il cui name o
     * username matchano (case-insensitive) uno qualunque dei $candidates.
     *
     * @param string[] $candidates  Nomi/username da matchare (senza la `@`).
     * @return array<int, array{id:int, name:string, username:?string}>
     */
    public function findMembersByMentionCandidates(int $conversationId, array $candidates): array
    {
        $candidates = array_values(array_unique(array_filter(
            array_map('strval', $candidates),
            static fn (string $c): bool => $c !== ''
        )));
        if (empty($candidates)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($candidates), '?'));
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT u.id, u.name, u.username
            FROM teams_conversation_members m
            JOIN users u ON u.id = m.user_id
            WHERE m.conversation_id = ?
              AND m.left_at IS NULL
              AND u.is_active = 1 AND u.deleted_at IS NULL
              AND (
                  LOWER(u.username) IN ($placeholders)
                  OR LOWER(REPLACE(u.name, ' ', '')) IN ($placeholders)
              )
        ");
        $lower = array_map(static fn (string $c): string => mb_strtolower($c), $candidates);
        $params = array_merge([$conversationId], $lower, $lower);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Tocca `teams_conversations.updated_at` per segnalare al polling unificato
     * che la lista conversazioni va rinfrescata (eventi che cambiano solo
     * `teams_conversation_members`: add/remove/hide/unhide/mute).
     *
     * Per messaggi e rinomine/avatar/archive la colonna è già toccata
     * (ON UPDATE CURRENT_TIMESTAMP + NOW() esplicito in MessageRepository).
     */
    private function touchConversationUpdatedAt(int $conversationId): void
    {
        $this->pdo
            ->prepare('UPDATE teams_conversations SET updated_at = NOW() WHERE id = ?')
            ->execute([$conversationId]);
    }

    /**
     * Hard-delete di una conversazione (FK CASCADE elimina members e messaggi).
     */
    public function hardDelete(int $conversationId): void
    {
        // Pulizia riferimenti non coperti da CASCADE
        $this->pdo->prepare('DELETE FROM teams_typing WHERE conversation_id = ?')
            ->execute([$conversationId]);
        $this->pdo->prepare('UPDATE teams_user_presence SET active_conversation_id = NULL WHERE active_conversation_id = ?')
            ->execute([$conversationId]);
        $this->pdo->prepare('DELETE FROM teams_conversations WHERE id = ?')
            ->execute([$conversationId]);
    }
}
