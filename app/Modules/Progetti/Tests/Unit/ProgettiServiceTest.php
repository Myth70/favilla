<?php

namespace App\Modules\Progetti\Tests\Unit;

use App\Core\Container;
use App\Core\ModuleLoader;
use App\Modules\Progetti\Services\ProgettiService;
use Tests\ModuleTestCase;

/**
 * Test della logica pura di ProgettiService.
 *
 * Si concentra su:
 * - wouldCreateCycle(): algoritmo DFS di rilevamento cicli
 * - normalizzatori di stato/priorità (via chiamata riflessa)
 */
class ProgettiServiceTest extends ModuleTestCase
{
    private ProgettiService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $loader = new ModuleLoader(BASE_PATH);
        $loader->loadConfig();
        Container::getInstance()->instance(ModuleLoader::class, $loader);

        // Schema minimo richiesto dal costruttore di ProgettiRepository
        $this->migrate('
            CREATE TABLE IF NOT EXISTS users (
                id                   INTEGER PRIMARY KEY AUTOINCREMENT,
                name                 TEXT NOT NULL,
                teams_conversation_id INTEGER NULL
            );
            CREATE TABLE IF NOT EXISTS projects (
                id                   INTEGER PRIMARY KEY AUTOINCREMENT,
                name                 TEXT NOT NULL,
                owner_user_id        INTEGER NOT NULL DEFAULT 1,
                status               TEXT NOT NULL DEFAULT "planning",
                estimated_hours      REAL NOT NULL DEFAULT 0,
                budget_planned       REAL NOT NULL DEFAULT 0,
                budget_actual_cached REAL NOT NULL DEFAULT 0,
                progress_cached      REAL NOT NULL DEFAULT 0,
                teams_conversation_id INTEGER NULL,
                created_by           INTEGER NULL,
                created_at           TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at           TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at           TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS project_milestones (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NOT NULL,
                name       TEXT NOT NULL,
                status     TEXT NOT NULL DEFAULT "pending",
                deleted_at TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS project_tasks (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id       INTEGER NOT NULL,
                title            TEXT NOT NULL,
                assigned_user_id INTEGER NULL,
                status           TEXT NOT NULL DEFAULT "todo",
                priority         TEXT NOT NULL DEFAULT "medium",
                position         INTEGER NOT NULL DEFAULT 0,
                start_date       TEXT NULL,
                due_date            TEXT NULL,
                last_reminded_date  TEXT NULL,
                created_by          INTEGER NULL,
                deleted_at          TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS project_task_dependencies (
                predecessor_task_id INTEGER NOT NULL,
                successor_task_id   INTEGER NOT NULL,
                dependency_type     TEXT NOT NULL DEFAULT "FS",
                PRIMARY KEY (predecessor_task_id, successor_task_id)
            );
            CREATE TABLE IF NOT EXISTS project_members (
                project_id           INTEGER NOT NULL,
                user_id              INTEGER NOT NULL,
                role                 TEXT NOT NULL DEFAULT "member",
                hourly_rate_override REAL NULL,
                joined_at            TEXT DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (project_id, user_id)
            );
            CREATE TABLE IF NOT EXISTS project_timesheets (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NOT NULL,
                task_id    INTEGER NOT NULL,
                user_id    INTEGER NOT NULL,
                work_date  TEXT NOT NULL,
                hours      REAL NOT NULL,
                note       TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS project_files (
                project_id INTEGER NOT NULL,
                file_id    INTEGER NOT NULL,
                linked_by  INTEGER NULL,
                linked_at  TEXT DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (project_id, file_id)
            );
            CREATE TABLE IF NOT EXISTS files (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                original_name TEXT NOT NULL,
                stored_name   TEXT NOT NULL,
                directory     TEXT NOT NULL DEFAULT "files",
                mime_type     TEXT NOT NULL,
                extension     TEXT NOT NULL,
                size_bytes    INTEGER NOT NULL DEFAULT 0,
                deleted_at    TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS teams_conversations (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                type        TEXT NOT NULL DEFAULT "group",
                name        TEXT NULL,
                description TEXT NULL,
                created_by  INTEGER NULL,
                archived_at TEXT NULL,
                created_at  TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at  TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS teams_conversation_members (
                conversation_id      INTEGER NOT NULL,
                user_id              INTEGER NOT NULL,
                role                 TEXT NOT NULL DEFAULT "member",
                notifications_muted  INTEGER NOT NULL DEFAULT 0,
                last_read_at         TEXT NULL,
                hidden_at            TEXT NULL,
                left_at              TEXT NULL,
                joined_at            TEXT DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (conversation_id, user_id)
            );
            CREATE TABLE IF NOT EXISTS teams_messages (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                conversation_id INTEGER NOT NULL,
                user_id         INTEGER NULL,
                body            TEXT NOT NULL,
                type            TEXT NOT NULL DEFAULT "text",
                edited_at       TEXT NULL,
                deleted_at      TEXT NULL,
                created_at      TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');

        // ── Tabelle Notifications (per reminder test) ────────────────────────────────

        $this->migrate('
            CREATE TABLE IF NOT EXISTS notification_channels (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                slug        TEXT NOT NULL UNIQUE,
                name        TEXT NOT NULL,
                description TEXT NULL,
                is_enabled  INTEGER NOT NULL DEFAULT 1,
                sort_order  INTEGER NOT NULL DEFAULT 10,
                created_at  TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at  TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS notification_event_types (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                slug          TEXT NOT NULL UNIQUE,
                module_slug   TEXT NOT NULL,
                name          TEXT NOT NULL,
                description   TEXT NULL,
                context_schema TEXT NULL,
                source        TEXT NULL,
                default_level TEXT NOT NULL DEFAULT "info",
                icon          TEXT NULL
                ,color        TEXT NULL,
                is_system     INTEGER NOT NULL DEFAULT 0,
                created_at    TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at    TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS notification_event_channel_bindings (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                event_type_id    INTEGER NOT NULL,
                channel_slug     TEXT NOT NULL,
                is_enabled       INTEGER NOT NULL DEFAULT 1,
                subject_template TEXT NULL,
                body_template    TEXT NULL,
                layout_config    TEXT NULL,
                created_at       TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at       TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(event_type_id, channel_slug)
            );
            CREATE TABLE IF NOT EXISTS user_notification_preferences (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id      INTEGER NOT NULL,
                module_slug  TEXT NOT NULL,
                event_slug   TEXT NOT NULL DEFAULT "",
                channel_slug TEXT NOT NULL,
                is_enabled   INTEGER NOT NULL DEFAULT 1,
                created_at   TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at   TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, module_slug, event_slug, channel_slug)
            );
            CREATE TABLE IF NOT EXISTS notification_dispatches (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                event_slug TEXT NULL,
                source_module TEXT NOT NULL,
                recipient_user_id INTEGER NULL,
                recipient_role_slug TEXT NULL,
                title TEXT NOT NULL,
                body TEXT NULL,
                type TEXT NOT NULL DEFAULT "info",
                link TEXT NULL,
                icon TEXT NULL,
                color TEXT NULL,
                payload_json TEXT NULL,
                created_by INTEGER NULL,
                bypass_preferences INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT "pending",
                total_recipients INTEGER NOT NULL DEFAULT 0,
                total_deliveries INTEGER NOT NULL DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS notification_deliveries (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                dispatch_id INTEGER NOT NULL,
                user_id    INTEGER NOT NULL,
                channel_slug TEXT NOT NULL,
                status     TEXT NOT NULL DEFAULT "pending",
                subject    TEXT NULL,
                body       TEXT NULL,
                link       TEXT NULL,
                icon       TEXT NULL,
                color      TEXT NULL,
                provider_message_id TEXT NULL,
                error_message TEXT NULL,
                attempts   INTEGER NOT NULL DEFAULT 0,
                sent_at    TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS notification_queue (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                delivery_id  INTEGER NOT NULL,
                channel_slug TEXT NOT NULL,
                payload_json TEXT NULL,
                status       TEXT NOT NULL DEFAULT "pending",
                available_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                locked_at    TEXT NULL,
                attempts     INTEGER NOT NULL DEFAULT 0,
                max_attempts INTEGER NOT NULL DEFAULT 5,
                last_error   TEXT NULL,
                created_at   TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at   TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS notifications (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL,
                title      TEXT NOT NULL,
                body       TEXT NULL,
                type       TEXT DEFAULT "info",
                icon       TEXT NULL,
                color      TEXT NULL,
                link       TEXT NULL,
                read_at    TEXT NULL,
                created_by INTEGER NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');

        $this->insertRow('users', ['name' => 'Mario']);
        $this->insertRow('users', ['name' => 'Luigi']);

        $this->service = new ProgettiService();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Chiama il metodo privato wouldCreateCycle() via Reflection.
     */
    private function wouldCreateCycle(int $predecessorId, int $successorId, array $edges): bool
    {
        $method = new \ReflectionMethod(ProgettiService::class, 'wouldCreateCycle');
        $method->setAccessible(true);
        return $method->invoke($this->service, $predecessorId, $successorId, $edges);
    }

    /**
     * Costruisce un edge record nel formato restituito da getDependencyEdges().
     */
    private function edge(int $pred, int $succ): array
    {
        return ['predecessor_task_id' => $pred, 'successor_task_id' => $succ];
    }

    // ── wouldCreateCycle ─────────────────────────────────────────────────────

    public function testNoCycleWhenNoEdgesExist(): void
    {
        // Grafo vuoto: qualsiasi arco aggiunto non può creare un ciclo
        $this->assertFalse($this->wouldCreateCycle(1, 2, []));
    }

    public function testNoCycleForSimpleLinearChain(): void
    {
        // A→B, vogliamo aggiungere B→C: nessun ciclo
        $edges = [$this->edge(1, 2)]; // A→B
        $this->assertFalse($this->wouldCreateCycle(2, 3, $edges));
    }

    public function testNoCycleForBranchingGraph(): void
    {
        // A→B, A→C, vogliamo aggiungere B→D: nessun ciclo
        $edges = [$this->edge(1, 2), $this->edge(1, 3)];
        $this->assertFalse($this->wouldCreateCycle(2, 4, $edges));
    }

    public function testDetectsDirectCycle(): void
    {
        // A→B esiste già; vogliamo aggiungere B→A: ciclo diretto A-B-A
        $edges = [$this->edge(1, 2)]; // A→B
        $this->assertTrue($this->wouldCreateCycle(2, 1, $edges));
    }

    public function testDetectsTransitiveCycle(): void
    {
        // A→B, B→C esistono; vogliamo aggiungere C→A: ciclo A→B→C→A
        $edges = [$this->edge(1, 2), $this->edge(2, 3)];
        $this->assertTrue($this->wouldCreateCycle(3, 1, $edges));
    }

    public function testDetectsCycleInLargerGraph(): void
    {
        // A→B, B→C, C→D, A→E; vogliamo D→A: ciclo A→B→C→D→A
        $edges = [
            $this->edge(1, 2),
            $this->edge(2, 3),
            $this->edge(3, 4),
            $this->edge(1, 5),
        ];
        $this->assertTrue($this->wouldCreateCycle(4, 1, $edges));
    }

    public function testNoCycleWhenReachableButNotCircular(): void
    {
        // A→B, B→C, A→C; vogliamo aggiungere C→D: non è un ciclo
        $edges = [$this->edge(1, 2), $this->edge(2, 3), $this->edge(1, 3)];
        $this->assertFalse($this->wouldCreateCycle(3, 4, $edges));
    }

    public function testSelfReferenceIsNotTestedByCycleFunction(): void
    {
        // La funzione wouldCreateCycle(A, A) non dovrebbe esplodere:
        // predecessor e successor identici (la validazione "non self" è nel service)
        // Il DFS: successor=A → visita A → A == predecessor A → true
        $this->assertTrue($this->wouldCreateCycle(1, 1, []));
    }

    public function testAddMemberSyncsLinkedTeamsConversation(): void
    {
        $conversationId = $this->insertRow('teams_conversations', [
            'type' => 'group',
            'name' => 'Progetto - Test',
            'created_by' => 1,
        ]);

        $this->insertRow('teams_conversation_members', [
            'conversation_id' => $conversationId,
            'user_id' => 1,
            'role' => 'admin',
        ]);

        $this->insertRow('projects', [
            'name' => 'Project Sync',
            'owner_user_id' => 1,
            'teams_conversation_id' => $conversationId,
        ]);
        $this->insertRow('project_members', [
            'project_id' => 1,
            'user_id' => 1,
            'role' => 'owner',
            'hourly_rate_override' => null,
        ]);

        $this->service->addMember(1, 2, 'member', null, 1);

        $teamMember = $this->pdo->query('SELECT role, left_at FROM teams_conversation_members WHERE conversation_id = 1 AND user_id = 2')->fetch();
        $systemMessage = $this->pdo->query('SELECT body, type FROM teams_messages WHERE conversation_id = 1 ORDER BY id DESC LIMIT 1')->fetch();

        $this->assertSame('member', $teamMember['role']);
        $this->assertNull($teamMember['left_at']);
        $this->assertSame('system', $systemMessage['type']);
        $this->assertStringContainsString('ha aggiunto Luigi al gruppo progetto', $systemMessage['body']);
    }

    public function testRemoveMemberSyncsLinkedTeamsConversation(): void
    {
        $conversationId = $this->insertRow('teams_conversations', [
            'type' => 'group',
            'name' => 'Progetto - Test',
            'created_by' => 1,
        ]);

        $this->insertRow('teams_conversation_members', [
            'conversation_id' => $conversationId,
            'user_id' => 1,
            'role' => 'admin',
        ]);
        $this->insertRow('teams_conversation_members', [
            'conversation_id' => $conversationId,
            'user_id' => 2,
            'role' => 'member',
        ]);

        $this->insertRow('projects', [
            'name' => 'Project Sync',
            'owner_user_id' => 1,
            'teams_conversation_id' => $conversationId,
        ]);
        $this->insertRow('project_members', [
            'project_id' => 1,
            'user_id' => 1,
            'role' => 'owner',
            'hourly_rate_override' => null,
        ]);
        $this->insertRow('project_members', [
            'project_id' => 1,
            'user_id' => 2,
            'role' => 'member',
            'hourly_rate_override' => null,
        ]);

        $this->service->removeMember(1, 2, 1);

        $teamMember = $this->pdo->query('SELECT left_at FROM teams_conversation_members WHERE conversation_id = 1 AND user_id = 2')->fetch();
        $systemMessage = $this->pdo->query('SELECT body, type FROM teams_messages WHERE conversation_id = 1 ORDER BY id DESC LIMIT 1')->fetch();

        $this->assertNotNull($teamMember['left_at']);
        $this->assertSame('system', $systemMessage['type']);
        $this->assertStringContainsString('ha rimosso Luigi dal gruppo progetto', $systemMessage['body']);
    }

    // ── removeTaskDependency: scoping IDOR ──────────────────────────────────

    public function testRemoveTaskDependencyDeletesEdgeWhenBothTasksBelongToProject(): void
    {
        $this->insertRow('projects', ['name' => 'Project A', 'owner_user_id' => 1]);
        $this->insertRow('project_tasks', ['project_id' => 1, 'title' => 'Task A1', 'created_by' => 1]);
        $this->insertRow('project_tasks', ['project_id' => 1, 'title' => 'Task A2', 'created_by' => 1]);
        $this->pdo->exec('INSERT INTO project_task_dependencies (predecessor_task_id, successor_task_id) VALUES (1, 2)');

        $result = $this->service->removeTaskDependency(1, 2, 1, 1);

        $this->assertTrue($result);
        $remaining = $this->pdo->query('SELECT COUNT(*) FROM project_task_dependencies')->fetchColumn();
        $this->assertSame(0, (int) $remaining);
    }

    public function testRemoveTaskDependencyRejectsTasksFromAnotherProject(): void
    {
        // Progetto B (a cui l'utente NON dovrebbe poter toccare le dipendenze)
        // con un edge tra due suoi task.
        $this->insertRow('projects', ['name' => 'Project A', 'owner_user_id' => 1]);
        $this->insertRow('projects', ['name' => 'Project B', 'owner_user_id' => 2]);
        $this->insertRow('project_tasks', ['project_id' => 2, 'title' => 'Task B1', 'created_by' => 2]);
        $this->insertRow('project_tasks', ['project_id' => 2, 'title' => 'Task B2', 'created_by' => 2]);
        $this->pdo->exec('INSERT INTO project_task_dependencies (predecessor_task_id, successor_task_id) VALUES (1, 2)');

        // L'attaccante passa projectId=1 (progetto proprio) ma taskId/predecessorId
        // appartengono al progetto 2: senza scoping, l'edge verrebbe cancellato
        // anche se l'utente non ha alcun accesso al progetto 2 (IDOR cross-project).
        $this->expectException(\RuntimeException::class);
        $this->service->removeTaskDependency(1, 2, 1, 1);
    }

    public function testRemoveTaskDependencyDoesNotDeleteEdgeWhenTasksNotInProject(): void
    {
        $this->insertRow('projects', ['name' => 'Project A', 'owner_user_id' => 1]);
        $this->insertRow('projects', ['name' => 'Project B', 'owner_user_id' => 2]);
        $this->insertRow('project_tasks', ['project_id' => 2, 'title' => 'Task B1', 'created_by' => 2]);
        $this->insertRow('project_tasks', ['project_id' => 2, 'title' => 'Task B2', 'created_by' => 2]);
        $this->pdo->exec('INSERT INTO project_task_dependencies (predecessor_task_id, successor_task_id) VALUES (1, 2)');

        try {
            $this->service->removeTaskDependency(1, 2, 1, 1);
        } catch (\RuntimeException) {
            // atteso
        }

        $remaining = $this->pdo->query('SELECT COUNT(*) FROM project_task_dependencies')->fetchColumn();
        $this->assertSame(1, (int) $remaining, 'The cross-project dependency edge must survive the rejected call.');
    }

    public function testSendTaskDueRemindersDispatchesNotifications(): void
    {
        // Setup task with due_date in 5 days
        $dueDate = date('Y-m-d', strtotime('+5 days'));
        $this->insertRow('projects', [
            'id' => 1,
            'name' => 'Task Reminder Test',
            'owner_user_id' => 1,
        ]);
        $this->insertRow('project_tasks', [
            'project_id' => 1,
            'title' => 'Review spec',
            'status' => 'todo',
            'due_date' => $dueDate,
            'assigned_user_id' => 2,
        ]);

        // Setup notification channel and event
        $this->insertRow('notification_channels', [
            'slug' => 'in_app',
            'name' => 'In-App',
            'is_enabled' => 1,
        ]);
        $eventTypeId = $this->insertRow('notification_event_types', [
            'slug' => 'progetti.task_due_soon',
            'module_slug' => 'progetti',
            'name' => 'Task in scadenza',
            'default_level' => 'warning',
        ]);
        $this->insertRow('notification_event_channel_bindings', [
            'event_type_id' => $eventTypeId,
            'channel_slug' => 'in_app',
            'is_enabled' => 1,
            'subject_template' => 'Scadenza vicina: {{task_title}}',
            'body_template' => 'Il task {{task_title}} del progetto {{project_name}} scade il {{due_date}}',
        ]);

        $sent = $this->service->sendTaskDueReminders(7);

        $notification = $this->pdo->query('SELECT title, body, link FROM notifications ORDER BY id DESC LIMIT 1')->fetch();

        $this->assertSame(1, $sent);
        $this->assertSame('Scadenza vicina: Review spec', $notification['title']);
        $this->assertStringContainsString('Task Reminder Test', $notification['body']);
        $this->assertStringContainsString($dueDate, $notification['body']);
    }

    // ── validateProjectData ──────────────────────────────────────────────────

    public function testValidateProjectDataPassesForValidInput(): void
    {
        $errors = ProgettiService::validateProjectData([
            'name' => 'Progetto Demo',
            'status' => 'active',
            'estimated_hours' => 40,
            'budget_planned' => 5000,
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
        ]);
        $this->assertSame([], $errors);
    }

    public function testValidateProjectDataRequiresName(): void
    {
        $errors = ProgettiService::validateProjectData(['name' => '   ']);
        $this->assertArrayHasKey('name', $errors);
    }

    public function testValidateProjectDataRejectsUnknownStatus(): void
    {
        $errors = ProgettiService::validateProjectData([
            'name' => 'X',
            'status' => 'banana',
        ]);
        $this->assertArrayHasKey('status', $errors);
    }

    public function testValidateProjectDataRejectsNegativeHoursAndBudget(): void
    {
        $errors = ProgettiService::validateProjectData([
            'name' => 'X',
            'status' => 'planning',
            'estimated_hours' => -1,
            'budget_planned' => -100,
        ]);
        $this->assertArrayHasKey('estimated_hours', $errors);
        $this->assertArrayHasKey('budget_planned', $errors);
    }

    public function testValidateProjectDataRejectsEndDateBeforeStartDate(): void
    {
        $errors = ProgettiService::validateProjectData([
            'name' => 'X',
            'status' => 'planning',
            'start_date' => '2026-06-01',
            'end_date'   => '2026-05-15',
        ]);
        $this->assertArrayHasKey('end_date', $errors);
    }
}
