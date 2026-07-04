<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * ISO 27001 A.6.1.2 — Separation of Duties.
 * Manages role incompatibility constraints and validates role assignments.
 */
class RoleConstraintService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = app(PDO::class);
    }

    /**
     * Get all constraints with role names.
     */
    public function allConstraints(): array
    {
        return $this->pdo->query(
            'SELECT rc.*, r1.name AS role1_name, r2.name AS role2_name
             FROM role_constraints rc
             JOIN roles r1 ON r1.id = rc.role_id_1
             JOIN roles r2 ON r2.id = rc.role_id_2
             ORDER BY rc.enabled DESC, r1.name, r2.name'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find a single constraint by ID.
     */
    public function findConstraint(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT rc.*, r1.name AS role1_name, r2.name AS role2_name
             FROM role_constraints rc
             JOIN roles r1 ON r1.id = rc.role_id_1
             JOIN roles r2 ON r2.id = rc.role_id_2
             WHERE rc.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Create a new constraint.
     */
    public function createConstraint(int $roleId1, int $roleId2, string $reason): int
    {
        // Ensure consistent ordering (lower ID first) to avoid duplicates
        if ($roleId1 > $roleId2) {
            [$roleId1, $roleId2] = [$roleId2, $roleId1];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO role_constraints (role_id_1, role_id_2, reason, enabled)
             VALUES (?, ?, ?, 1)'
        );
        $stmt->execute([$roleId1, $roleId2, $reason]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update an existing constraint.
     */
    public function updateConstraint(int $id, string $reason): void
    {
        $stmt = $this->pdo->prepare('UPDATE role_constraints SET reason = ? WHERE id = ?');
        $stmt->execute([$reason, $id]);
    }

    /**
     * Toggle a constraint's enabled state.
     */
    public function toggleConstraint(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE role_constraints SET enabled = NOT enabled WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Delete a constraint.
     */
    public function deleteConstraint(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM role_constraints WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Validate a set of role IDs against active constraints.
     * Returns an array of violations (empty = OK).
     */
    public function validateRoles(array $roleIds): array
    {
        if (count($roleIds) < 2) {
            return [];
        }

        $roleIds = array_map('intval', $roleIds);
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));

        $stmt = $this->pdo->prepare(
            "SELECT rc.id, rc.reason, r1.name AS role1_name, r2.name AS role2_name
             FROM role_constraints rc
             JOIN roles r1 ON r1.id = rc.role_id_1
             JOIN roles r2 ON r2.id = rc.role_id_2
             WHERE rc.enabled = 1
               AND rc.role_id_1 IN ({$placeholders})
               AND rc.role_id_2 IN ({$placeholders})"
        );
        // Need to bind roleIds twice (for both IN clauses)
        $params = array_merge($roleIds, $roleIds);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find all users currently violating any active constraint.
     */
    public function findViolations(): array
    {
        return $this->pdo->query(
            'SELECT u.id AS user_id, u.name AS user_name, u.email,
                    r1.name AS role1_name, r2.name AS role2_name, rc.reason
             FROM role_constraints rc
             JOIN user_role ur1 ON ur1.role_id = rc.role_id_1
             JOIN user_role ur2 ON ur2.role_id = rc.role_id_2 AND ur2.user_id = ur1.user_id
             JOIN users u ON u.id = ur1.user_id AND u.deleted_at IS NULL
             JOIN roles r1 ON r1.id = rc.role_id_1
             JOIN roles r2 ON r2.id = rc.role_id_2
             WHERE rc.enabled = 1
             ORDER BY u.name, r1.name'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get stats for the dashboard.
     */
    public function getStats(): array
    {
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM role_constraints')->fetchColumn();
        $active = (int) $this->pdo->query('SELECT COUNT(*) FROM role_constraints WHERE enabled = 1')->fetchColumn();
        $violations = count($this->findViolations());

        return [
            'total'      => $total,
            'active'     => $active,
            'violations' => $violations,
        ];
    }

    /**
     * Elenco ruoli per select di amministrazione SoD.
     */
    public function getRolesList(): array
    {
        return $this->pdo->query('SELECT id, name, slug FROM roles ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    }
}
