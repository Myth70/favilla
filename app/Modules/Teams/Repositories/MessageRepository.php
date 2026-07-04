<?php

declare(strict_types=1);

namespace App\Modules\Teams\Repositories;

use App\Repositories\BaseRepository;

class MessageRepository extends BaseRepository
{
    protected string $table = 'teams_messages';

    /**
     * Frammento SQL comune per il JOIN sul messaggio citato (reply parent).
     * Aggiunge i campi `reply_parent_body`, `reply_parent_user_name`,
     * `reply_parent_deleted` (1 se il padre è stato soft-deleted).
     */
    private const REPLY_PARENT_SELECT =
        '
            ,
            rm.body                       AS reply_parent_body,
            ru.name                       AS reply_parent_user_name,
            CASE WHEN rm.deleted_at IS NOT NULL THEN 1 ELSE 0 END AS reply_parent_deleted
        ';

    private const REPLY_PARENT_JOIN =
        '
            LEFT JOIN teams_messages rm ON rm.id = m.reply_to_id
            LEFT JOIN users          ru ON ru.id = rm.user_id
        ';

    /**
     * Ultimi N messaggi di una conversazione (ordine ASC per visualizzazione).
     * Ogni messaggio include un array `attachments` (può essere vuoto) e
     * i campi `reply_parent_*` se è una risposta.
     */
    public function getLatest(int $conversationId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('
            SELECT m.*, u.name AS user_name, u.avatar_path
            ' . self::REPLY_PARENT_SELECT . '
            FROM teams_messages m
            LEFT JOIN users u ON u.id = m.user_id
            ' . self::REPLY_PARENT_JOIN . '
            WHERE m.conversation_id = ?
              AND m.deleted_at IS NULL
            ORDER BY m.created_at DESC
            LIMIT ?
        ');
        $stmt->execute([$conversationId, $limit]);
        $rows = $stmt->fetchAll();
        return $this->attachAttachments(array_reverse($rows));
    }

    /**
     * Messaggi piu' vecchi di un dato ID (infinite scroll up).
     */
    public function getOlderThan(int $conversationId, int $beforeId, int $limit = 30): array
    {
        $stmt = $this->pdo->prepare('
            SELECT m.*, u.name AS user_name, u.avatar_path
            ' . self::REPLY_PARENT_SELECT . '
            FROM teams_messages m
            LEFT JOIN users u ON u.id = m.user_id
            ' . self::REPLY_PARENT_JOIN . '
            WHERE m.conversation_id = ? AND m.id < ?
              AND m.deleted_at IS NULL
            ORDER BY m.created_at DESC, m.id DESC
            LIMIT ?
        ');
        $stmt->execute([$conversationId, $beforeId, $limit]);
        $rows = $stmt->fetchAll();
        return $this->attachAttachments(array_reverse($rows));
    }

    /**
     * Messaggi nuovi dopo un dato timestamp (per polling).
     */
    public function getNewerThan(int $conversationId, string $sinceTimestamp): array
    {
        $stmt = $this->pdo->prepare('
            SELECT m.*, u.name AS user_name, u.avatar_path
            ' . self::REPLY_PARENT_SELECT . '
            FROM teams_messages m
            LEFT JOIN users u ON u.id = m.user_id
            ' . self::REPLY_PARENT_JOIN . '
            WHERE m.conversation_id = ? AND m.created_at > ?
            ORDER BY m.created_at ASC
        ');
        $stmt->execute([$conversationId, $sinceTimestamp]);
        return $this->attachAttachments($stmt->fetchAll());
    }

    /**
     * Messaggi della conversazione il cui stato visibile è cambiato dopo
     * $sinceTimestamp (reaction toggle / edit / soft-delete / read receipt
     * di altri sul tuo messaggio). Usato dal polling per inviare via HTMX OOB
     * solo i wrapper effettivamente cambiati.
     */
    public function getStateChangedSince(int $conversationId, string $sinceTimestamp): array
    {
        $stmt = $this->pdo->prepare('
            SELECT m.*, u.name AS user_name, u.avatar_path
            ' . self::REPLY_PARENT_SELECT . '
            FROM teams_messages m
            LEFT JOIN users u ON u.id = m.user_id
            ' . self::REPLY_PARENT_JOIN . '
            WHERE m.conversation_id = ? AND m.state_updated_at > ?
            ORDER BY m.created_at ASC
        ');
        $stmt->execute([$conversationId, $sinceTimestamp]);
        return $this->attachAttachments($stmt->fetchAll());
    }

    /**
     * Aggiorna il timestamp `state_updated_at` di un singolo messaggio.
     * Chiamato dai service quando cambia lo stato visibile (reaction, edit,
     * delete). Per le read receipts su massa, vedi `touchStateForOthersMessages`.
     */
    public function touchStateUpdatedAt(int $messageId): void
    {
        $this->pdo->prepare(
            'UPDATE teams_messages SET state_updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        )->execute([$messageId]);
    }

    /**
     * Tocca `state_updated_at` di tutti i messaggi (non eliminati) della
     * conversazione il cui autore NON è $excludeUserId, creati dopo
     * $afterCreatedAt. Usato in `markAsRead` per propagare ai mittenti il
     * cambio "spunte di lettura" senza causare swap su messaggi già letti.
     */
    public function touchStateForOthersMessages(int $conversationId, int $excludeUserId, ?string $afterCreatedAt): void
    {
        if ($afterCreatedAt === null || $afterCreatedAt === '') {
            $stmt = $this->pdo->prepare(
                'UPDATE teams_messages
                 SET state_updated_at = CURRENT_TIMESTAMP
                 WHERE conversation_id = ? AND user_id <> ? AND deleted_at IS NULL'
            );
            $stmt->execute([$conversationId, $excludeUserId]);
            return;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE teams_messages
             SET state_updated_at = CURRENT_TIMESTAMP
             WHERE conversation_id = ? AND user_id <> ? AND deleted_at IS NULL AND created_at > ?'
        );
        $stmt->execute([$conversationId, $excludeUserId, $afterCreatedAt]);
    }

    /**
     * Arricchisce un set di messaggi con i loro allegati in un'unica query
     * batch (no N+1). Aggiunge la chiave `attachments` ad ogni messaggio.
     *
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    private function attachAttachments(array $messages): array
    {
        if (empty($messages)) {
            return $messages;
        }
        $ids = array_column($messages, 'id');
        $map = $this->getAttachmentsForMessages($ids);
        foreach ($messages as &$msg) {
            $msg['attachments'] = $map[(int) $msg['id']] ?? [];
        }
        unset($msg);
        return $messages;
    }

    /**
     * Restituisce gli allegati raggruppati per message_id.
     *
     * @param int[] $messageIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function getAttachmentsForMessages(array $messageIds): array
    {
        if (empty($messageIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT id, message_id, original_name, stored_name, mime_type, size_bytes, extension, created_at
            FROM teams_message_attachments
            WHERE message_id IN ($placeholders)
            ORDER BY message_id, id
        ");
        $stmt->execute($messageIds);
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['message_id']][] = $r;
        }
        return $map;
    }

    /**
     * Trova un allegato con la conversazione di appartenenza (per il check
     * membership a monte dello streaming). Esclude i messaggi eliminati.
     */
    public function findAttachment(int $attachmentId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT a.id, a.message_id, a.original_name, a.stored_name,
                   a.mime_type, a.size_bytes, a.extension, m.conversation_id
            FROM teams_message_attachments a
            JOIN teams_messages m ON m.id = a.message_id
            WHERE a.id = ? AND m.deleted_at IS NULL
        ');
        $stmt->execute([$attachmentId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Crea un messaggio e restituisce il record completo con dati utente.
     */
    public function createMessage(int $conversationId, int $userId, string $body, string $type = 'text'): array
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO teams_messages (conversation_id, user_id, body, type)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$conversationId, $userId, $body, $type]);
        $id = (int) $this->pdo->lastInsertId();

        // Aggiorna updated_at sulla conversazione
        $this->pdo->prepare('UPDATE teams_conversations SET updated_at = NOW() WHERE id = ?')
            ->execute([$conversationId]);

        return $this->findWithUser($id);
    }

    /**
     * Associa un allegato a un messaggio. Supporta N allegati per messaggio.
     */
    public function attachFileToMessage(int $messageId, array $fileMeta): void
    {
        $this->pdo->prepare(
            'INSERT INTO teams_message_attachments
                (message_id, original_name, stored_name, mime_type, size_bytes, extension)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $messageId,
            (string) ($fileMeta['original_name'] ?? ''),
            (string) ($fileMeta['filename'] ?? ''),
            (string) ($fileMeta['mime'] ?? ''),
            (int) ($fileMeta['size'] ?? 0),
            (string) ($fileMeta['extension'] ?? ''),
        ]);
    }

    /**
     * Associa N allegati a un messaggio (insert batch).
     *
     * @param array<int, array<string, mixed>> $filesMeta
     */
    public function attachFilesToMessage(int $messageId, array $filesMeta): void
    {
        foreach ($filesMeta as $fileMeta) {
            $this->attachFileToMessage($messageId, $fileMeta);
        }
    }

    /**
     * Persiste l'elenco di utenti menzionati in un messaggio.
     *
     * Niente `INSERT IGNORE` (sintassi MySQL-only, errore di sintassi su
     * SQLite): gli $userIds sono deduplicati lato PHP prima dell'insert e
     * `(message_id, mentioned_user_id)` è la chiave primaria composita, quindi
     * un semplice INSERT è sufficiente — non può verificarsi una violazione
     * di unicità all'interno dello stesso batch.
     *
     * @param int[] $userIds
     */
    public function insertMentions(int $messageId, array $userIds): void
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (empty($userIds)) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO teams_message_mentions (message_id, mentioned_user_id) VALUES (?, ?)'
        );
        foreach ($userIds as $userId) {
            $stmt->execute([$messageId, $userId]);
        }
    }

    /**
     * Crea un messaggio di sistema.
     */
    public function createSystemMessage(int $conversationId, string $body): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO teams_messages (conversation_id, user_id, body, type)
            VALUES (?, NULL, ?, 'system')
        ");
        $stmt->execute([$conversationId, $body]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Modifica un messaggio (aggiorna body e imposta edited_at).
     * Salva la versione precedente nella tabella di storico.
     */
    public function editMessage(int $messageId, string $newBody, int $editedBy): ?array
    {
        $current = $this->findWithUser($messageId);
        if (!$current || !empty($current['deleted_at'])) {
            return null;
        }

        // Salva storico modifica
        $this->pdo->prepare('INSERT INTO teams_message_edits (message_id, old_body, edited_by) VALUES (?, ?, ?)')
            ->execute([$messageId, $current['body'], $editedBy]);

        // Aggiorna messaggio (anche state_updated_at per polling delta)
        $this->pdo->prepare('UPDATE teams_messages SET body = ?, edited_at = NOW(), state_updated_at = NOW() WHERE id = ? AND deleted_at IS NULL')
            ->execute([$newBody, $messageId]);

        return $this->findWithUser($messageId);
    }

    /**
     * Cronologia modifiche di un messaggio.
     */
    public function getEditHistory(int $messageId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT e.*, u.name AS editor_name
            FROM teams_message_edits e
            LEFT JOIN users u ON u.id = e.edited_by
            WHERE e.message_id = ?
            ORDER BY e.edited_at DESC
        ');
        $stmt->execute([$messageId]);
        return $stmt->fetchAll();
    }

    /**
     * Soft-delete di un messaggio.
     */
    public function softDelete(int $messageId): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE teams_messages SET deleted_at = NOW(), state_updated_at = NOW() WHERE id = ?
        ');
        return $stmt->execute([$messageId]);
    }

    /**
     * Imposta un messaggio come "pinned" in una conversazione.
     */
    public function pinMessage(int $messageId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE teams_messages SET pinned_at = NOW(), pinned_by = ?
             WHERE id = ? AND deleted_at IS NULL'
        );
        return $stmt->execute([$userId, $messageId]);
    }

    /**
     * Rimuove il pin da un messaggio.
     */
    public function unpinMessage(int $messageId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE teams_messages SET pinned_at = NULL, pinned_by = NULL WHERE id = ?'
        );
        return $stmt->execute([$messageId]);
    }

    /**
     * Restituisce i messaggi pinned di una conversazione (con utente).
     * Esclude i messaggi soft-deleted.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPinnedMessages(int $conversationId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare('
            SELECT m.*, u.name AS user_name, u.avatar_path
            ' . self::REPLY_PARENT_SELECT . '
            FROM teams_messages m
            LEFT JOIN users u ON u.id = m.user_id
            ' . self::REPLY_PARENT_JOIN . '
            WHERE m.conversation_id = ?
              AND m.pinned_at IS NOT NULL
              AND m.deleted_at IS NULL
            ORDER BY m.pinned_at DESC
            LIMIT ?
        ');
        $stmt->bindValue(1, $conversationId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $this->attachAttachments($stmt->fetchAll());
    }

    /**
     * Conta i messaggi pinned attivi (non soft-deleted) di una conversazione.
     */
    public function countPinnedMessages(int $conversationId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM teams_messages
             WHERE conversation_id = ? AND pinned_at IS NOT NULL AND deleted_at IS NULL'
        );
        $stmt->execute([$conversationId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Converte una query utente in sintassi MATCH AGAINST BOOLEAN MODE,
     * con prefix wildcard `*` su ogni token >= 3 char. Rimuove operatori
     * BOOLEAN MODE non desiderati per evitare sintassi malformata.
     * Restituisce stringa vuota se nessun token utile rimane.
     */
    private static function toBooleanModeQuery(string $query): string
    {
        $cleaned = preg_replace('/[+\-<>()~*"@]/', ' ', $query) ?? '';
        $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned) ?? '');
        if ($cleaned === '') {
            return '';
        }
        $out = [];
        foreach (explode(' ', $cleaned) as $tok) {
            if (mb_strlen($tok) >= 3) {
                $out[] = '+' . $tok . '*';
            }
        }
        return implode(' ', $out);
    }

    /**
     * Cerca messaggi tra le conversazioni di un utente.
     *
     * Strategia: FULLTEXT `MATCH ... AGAINST (... IN BOOLEAN MODE)` per query
     * con almeno un token >= 3 caratteri (sotto `innodb_ft_min_token_size` i
     * token vengono ignorati e otterremmo zero risultati). Per query corte
     * usa fallback `LIKE '%q%'` (scan, ma su pochi caratteri raro).
     */
    public function searchForUser(int $userId, string $query, int $limit = 50): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $boolQ = self::toBooleanModeQuery($query);
        $useFullText = $boolQ !== '';

        $matchSql  = $useFullText ? 'MATCH(m.body) AGAINST (? IN BOOLEAN MODE)' : 'm.body LIKE ?';
        $bodyParam = $useFullText ? $boolQ : ('%' . $query . '%');

        $stmt = $this->pdo->prepare("
            SELECT m.*, u.name AS user_name, u.avatar_path,
                   c.name AS conversation_name, c.type AS conversation_type,
                   ou.name AS other_user_name
            FROM teams_messages m
            INNER JOIN teams_conversation_members cm
                ON cm.conversation_id = m.conversation_id
                AND cm.user_id = ? AND cm.left_at IS NULL
            INNER JOIN teams_conversations c ON c.id = m.conversation_id
            LEFT JOIN users u ON u.id = m.user_id
            LEFT JOIN teams_conversation_members ocm
                ON ocm.conversation_id = c.id AND ocm.user_id != ? AND c.type = 'direct' AND ocm.left_at IS NULL
            LEFT JOIN users ou ON ou.id = ocm.user_id
            WHERE {$matchSql} AND m.deleted_at IS NULL AND m.type = 'text'
              AND c.archived_at IS NULL
            ORDER BY m.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $userId, $bodyParam, $limit]);
        return $this->attachAttachments($stmt->fetchAll());
    }

    /**
     * Cerca messaggi all'interno di una specifica conversazione. Stessa
     * strategia FULLTEXT + fallback LIKE di `searchForUser`.
     */
    public function searchInConversationForUser(int $conversationId, int $userId, string $query, int $limit = 50): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $boolQ = self::toBooleanModeQuery($query);
        $useFullText = $boolQ !== '';

        $matchSql  = $useFullText ? 'MATCH(m.body) AGAINST (? IN BOOLEAN MODE)' : 'm.body LIKE ?';
        $bodyParam = $useFullText ? $boolQ : ('%' . $query . '%');

        $stmt = $this->pdo->prepare(
            "SELECT m.*, u.name AS user_name, u.avatar_path
             FROM teams_messages m
             INNER JOIN teams_conversation_members cm
                ON cm.conversation_id = m.conversation_id
                AND cm.user_id = ? AND cm.left_at IS NULL
             LEFT JOIN users u ON u.id = m.user_id
             WHERE m.conversation_id = ?
               AND {$matchSql}
               AND m.deleted_at IS NULL
               AND m.type = 'text'
             ORDER BY m.created_at DESC
             LIMIT ?"
        );
        $stmt->execute([$userId, $conversationId, $bodyParam, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Trova un messaggio con dati utente, allegati e info reply parent.
     */
    public function findWithUser(int $messageId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT m.*, u.name AS user_name, u.avatar_path
            ' . self::REPLY_PARENT_SELECT . '
            FROM teams_messages m
            LEFT JOIN users u ON u.id = m.user_id
            ' . self::REPLY_PARENT_JOIN . '
            WHERE m.id = ?
        ');
        $stmt->execute([$messageId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['attachments'] = $this->getAttachmentsForMessages([(int) $row['id']])[(int) $row['id']] ?? [];
        return $row;
    }

    /**
     * Crea un messaggio con possibile reply_to_id (validato a monte).
     */
    public function createMessageWithReply(int $conversationId, int $userId, string $body, ?int $replyToId, string $type = 'text'): array
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO teams_messages (conversation_id, user_id, body, type, reply_to_id)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$conversationId, $userId, $body, $type, $replyToId]);
        $id = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare('UPDATE teams_conversations SET updated_at = NOW() WHERE id = ?')
            ->execute([$conversationId]);

        return $this->findWithUser($id);
    }

    /**
     * Verifica che $replyToId sia un messaggio valido nella stessa
     * conversazione e non soft-deleted. Restituisce true se utilizzabile.
     */
    public function isValidReplyTarget(int $conversationId, int $replyToId): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM teams_messages
            WHERE id = ? AND conversation_id = ? AND deleted_at IS NULL
        ');
        $stmt->execute([$replyToId, $conversationId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Controlla se ci sono messaggi piu' vecchi del primo ID dato.
     */
    public function hasOlderMessages(int $conversationId, int $firstMessageId): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM teams_messages
            WHERE conversation_id = ? AND id < ?
              AND deleted_at IS NULL
        ');
        $stmt->execute([$conversationId, $firstMessageId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Soft-delete messaggi piu' vecchi di N mesi.
     *
     * Soglia calcolata lato PHP (non `DATE_SUB(NOW(), INTERVAL ...)`, sintassi
     * MySQL-only non portabile su SQLite) e passata come parametro bind.
     */
    public function cleanupOldMessages(int $months = 6): int
    {
        $stmt = $this->pdo->prepare('
            UPDATE teams_messages
            SET deleted_at = NOW()
            WHERE deleted_at IS NULL
              AND created_at < ?
        ');
        $stmt->execute([$this->monthsAgo($months)]);
        return $stmt->rowCount();
    }

    /**
     * Conta messaggi non ancora soft-deleted e piu' vecchi di N mesi.
     */
    public function countOlderThan(int $months): int
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM teams_messages
            WHERE deleted_at IS NULL
              AND created_at < ?
        ');
        $stmt->execute([$this->monthsAgo($months)]);
        return (int) $stmt->fetchColumn();
    }

    private function monthsAgo(int $months): string
    {
        return date('Y-m-d H:i:s', strtotime("-{$months} months"));
    }

    // ── Reazioni emoji ────────────────────────────────────────────────────

    /**
     * Aggiungi/rimuovi reazione (toggle).
     * Restituisce true se aggiunta, false se rimossa.
     */
    public function toggleReaction(int $messageId, int $userId, string $emoji): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM teams_message_reactions
            WHERE message_id = ? AND user_id = ? AND emoji = ?
        ');
        $stmt->execute([$messageId, $userId, $emoji]);
        $exists = (int) $stmt->fetchColumn() > 0;

        if ($exists) {
            $this->pdo->prepare('
                DELETE FROM teams_message_reactions
                WHERE message_id = ? AND user_id = ? AND emoji = ?
            ')->execute([$messageId, $userId, $emoji]);
            $this->touchStateUpdatedAt($messageId);
            return false;
        }

        $this->pdo->prepare('
            INSERT INTO teams_message_reactions (message_id, user_id, emoji)
            VALUES (?, ?, ?)
        ')->execute([$messageId, $userId, $emoji]);
        $this->touchStateUpdatedAt($messageId);
        return true;
    }

    /**
     * Reazioni aggregate per un messaggio.
     * Restituisce array di ['emoji', 'count', 'users' => [userId, ...]]
     */
    public function getReactions(int $messageId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT emoji, user_id, created_at
            FROM teams_message_reactions
            WHERE message_id = ?
            ORDER BY created_at ASC
        ');
        $stmt->execute([$messageId]);
        $rows = $stmt->fetchAll();

        $grouped = [];
        foreach ($rows as $r) {
            $emoji = $r['emoji'];
            if (!isset($grouped[$emoji])) {
                $grouped[$emoji] = ['emoji' => $emoji, 'count' => 0, 'user_ids' => [], 'first_at' => $r['created_at']];
            }
            $grouped[$emoji]['count']++;
            $grouped[$emoji]['user_ids'][] = (int) $r['user_id'];
        }

        $result = array_values($grouped);
        usort($result, function (array $a, array $b): int {
            return $b['count'] <=> $a['count'] ?: $a['first_at'] <=> $b['first_at'];
        });

        return array_map(function (array $r): array {
            return [
                'emoji'    => $r['emoji'],
                'count'    => $r['count'],
                'user_ids' => $r['user_ids'],
            ];
        }, $result);
    }

    // ── Group panel: media / files / links ────────────────────────────────

    /**
     * Conta i messaggi non eliminati di una conversazione.
     * Esclude i messaggi di sistema dal totale visibile all'utente.
     */
    public function countMessagesInConversation(int $conversationId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM teams_messages
             WHERE conversation_id = ? AND deleted_at IS NULL AND type = \'text\''
        );
        $stmt->execute([$conversationId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Allegati image/* o video/* della conversazione, ordinati dal più recente.
     * Cursor-based su `teams_message_attachments.id`. Restituisce un riga per
     * allegato, con metadati del messaggio padre (autore, data).
     *
     * @return array<int, array{
     *   id:int, message_id:int, original_name:string, stored_name:string,
     *   mime_type:string, size_bytes:int, extension:?string, created_at:string,
     *   user_id:?int, user_name:?string, msg_created_at:string
     * }>
     */
    public function getMediaAttachments(int $conversationId, ?int $beforeAttId, int $limit): array
    {
        $limit = max(1, min(100, $limit));

        $where = 'm.conversation_id = ?
                  AND m.deleted_at IS NULL
                  AND (a.mime_type LIKE \'image/%\' OR a.mime_type LIKE \'video/%\')';
        $params = [$conversationId];
        if ($beforeAttId !== null && $beforeAttId > 0) {
            $where .= ' AND a.id < ?';
            $params[] = $beforeAttId;
        }

        $sql = "SELECT a.id, a.message_id, a.original_name, a.stored_name,
                       a.mime_type, a.size_bytes, a.extension, a.created_at,
                       m.user_id, m.created_at AS msg_created_at,
                       u.name AS user_name
                FROM teams_message_attachments a
                INNER JOIN teams_messages m ON m.id = a.message_id
                LEFT JOIN users u ON u.id = m.user_id
                WHERE {$where}
                ORDER BY a.id DESC
                LIMIT {$limit}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Allegati non-immagine/non-video della conversazione.
     * Filtro $kind: docs|archives|audio|all. Vedi TeamsFileIcon::kindOf
     * per la mappa MIME/estensione → categoria, replicata qui in SQL.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFileAttachments(int $conversationId, ?int $beforeAttId, int $limit, string $kind = 'all'): array
    {
        $limit = max(1, min(100, $limit));

        $where  = 'm.conversation_id = ?
                   AND m.deleted_at IS NULL
                   AND a.mime_type NOT LIKE \'image/%\'
                   AND a.mime_type NOT LIKE \'video/%\'';
        $params = [$conversationId];

        $kindSql = self::buildFileKindClause($kind);
        if ($kindSql !== '') {
            $where .= ' AND (' . $kindSql . ')';
        }

        if ($beforeAttId !== null && $beforeAttId > 0) {
            $where .= ' AND a.id < ?';
            $params[] = $beforeAttId;
        }

        $sql = "SELECT a.id, a.message_id, a.original_name, a.stored_name,
                       a.mime_type, a.size_bytes, a.extension, a.created_at,
                       m.user_id, m.created_at AS msg_created_at,
                       u.name AS user_name
                FROM teams_message_attachments a
                INNER JOIN teams_messages m ON m.id = a.message_id
                LEFT JOIN users u ON u.id = m.user_id
                WHERE {$where}
                ORDER BY a.id DESC
                LIMIT {$limit}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Conta solo gli allegati visibili (non eliminato il messaggio padre).
     * Usato per il counter "234 elementi multimediali" nel tab Media.
     */
    public function countMediaAttachments(int $conversationId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM teams_message_attachments a
             INNER JOIN teams_messages m ON m.id = a.message_id
             WHERE m.conversation_id = ?
               AND m.deleted_at IS NULL
               AND (a.mime_type LIKE \'image/%\' OR a.mime_type LIKE \'video/%\')'
        );
        $stmt->execute([$conversationId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Messaggi della conversazione il cui body contiene almeno un URL
     * (pre-filtrato con LIKE per stare leggeri, validato poi via regex nel
     * service). Cursor su `teams_messages.id`. Esclude system e deleted.
     *
     * @return array<int, array{
     *   id:int, body:string, user_id:?int, user_name:?string, created_at:string
     * }>
     */
    public function getMessagesWithLinks(int $conversationId, ?int $beforeMessageId, int $limit): array
    {
        $limit = max(1, min(100, $limit));

        $where = "m.conversation_id = ?
                  AND m.deleted_at IS NULL
                  AND m.type = 'text'
                  AND (m.body LIKE '%http://%' OR m.body LIKE '%https://%' OR m.body LIKE '%www.%')";
        $params = [$conversationId];
        if ($beforeMessageId !== null && $beforeMessageId > 0) {
            $where .= ' AND m.id < ?';
            $params[] = $beforeMessageId;
        }

        $sql = "SELECT m.id, m.body, m.user_id, m.created_at,
                       u.name AS user_name
                FROM teams_messages m
                LEFT JOIN users u ON u.id = m.user_id
                WHERE {$where}
                ORDER BY m.id DESC
                LIMIT {$limit}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Frammento SQL per filtrare per categoria file. Logica allineata a
     * TeamsFileIcon::kindOf: mappa estensioni primarie + MIME prefix.
     * Stringhe SQL letterali (no concat con input utente).
     */
    private static function buildFileKindClause(string $kind): string
    {
        switch ($kind) {
            case 'docs':
                return "LOWER(a.extension) IN ('pdf','doc','docx','odt','xls','xlsx','ods','csv','ppt','pptx','odp','txt')
                        OR a.mime_type IN (
                            'application/pdf','application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-powerpoint',
                            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                            'application/vnd.oasis.opendocument.text',
                            'application/vnd.oasis.opendocument.spreadsheet',
                            'text/plain','text/csv'
                        )";
            case 'archives':
                return "LOWER(a.extension) IN ('zip','rar','7z','tar','gz')
                        OR a.mime_type IN (
                            'application/zip','application/x-rar-compressed',
                            'application/x-7z-compressed','application/gzip'
                        )";
            case 'audio':
                return "a.mime_type LIKE 'audio/%'
                        OR LOWER(a.extension) IN ('mp3','wav','ogg','m4a','flac')";
            case 'all':
            default:
                return '';
        }
    }

    /**
     * Reazioni aggregate per una lista di messaggi (batch).
     * Restituisce mappa message_id => array di reazioni.
     *
     * Aggregazione lato PHP (non `GROUP_CONCAT(... ORDER BY ...)`, sintassi
     * MySQL-only non portabile su SQLite) replicando la stessa logica di
     * `getReactions()`: raggruppa per emoji, ordina i gruppi per count DESC
     * poi prima-reazione ASC, e mantiene gli user_id in ordine cronologico
     * all'interno di ciascun gruppo.
     */
    public function getReactionsForMessages(array $messageIds): array
    {
        if (empty($messageIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT message_id, emoji, user_id, created_at
            FROM teams_message_reactions
            WHERE message_id IN ($placeholders)
            ORDER BY created_at ASC
        ");
        $stmt->execute($messageIds);
        $rows = $stmt->fetchAll();

        $grouped = [];
        foreach ($rows as $r) {
            $mid = (int) $r['message_id'];
            $emoji = $r['emoji'];
            if (!isset($grouped[$mid][$emoji])) {
                $grouped[$mid][$emoji] = ['emoji' => $emoji, 'count' => 0, 'user_ids' => [], 'first_at' => $r['created_at']];
            }
            $grouped[$mid][$emoji]['count']++;
            $grouped[$mid][$emoji]['user_ids'][] = (int) $r['user_id'];
        }

        $map = [];
        foreach ($grouped as $mid => $emojiGroups) {
            $list = array_values($emojiGroups);
            usort($list, function (array $a, array $b): int {
                return $b['count'] <=> $a['count'] ?: $a['first_at'] <=> $b['first_at'];
            });
            $map[$mid] = array_map(function (array $r): array {
                return [
                    'emoji'    => $r['emoji'],
                    'count'    => $r['count'],
                    'user_ids' => $r['user_ids'],
                ];
            }, $list);
        }
        return $map;
    }
}
