<?php
declare(strict_types=1);

/**
 * Shared defect lifecycle rules used by the mobile contractor flow and the
 * manager review actions.
 */

function defectWorkflowCsrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function defectWorkflowHasValidCsrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals((string) $_SESSION['csrf_token'], $token);
}

function defectWorkflowIsReviewer(): bool
{
    $roleId = (int) ($_SESSION['role_id'] ?? 0);
    $userType = strtolower((string) ($_SESSION['user_type'] ?? ''));

    return in_array($roleId, [1, 2], true)
        || in_array($userType, ['admin', 'manager'], true);
}

function defectWorkflowAllowedFrom(string $action): array
{
    return match ($action) {
        'start' => ['open', 'rejected'],
        'submit' => ['open', 'in_progress', 'rejected'],
        'accept', 'reject' => ['pending', 'completed', 'verified'],
        'reopen' => ['accepted', 'rejected'],
        default => [],
    };
}

function defectWorkflowTargetStatus(string $action): ?string
{
    return match ($action) {
        'start' => 'in_progress',
        'submit' => 'pending',
        'accept' => 'accepted',
        'reject' => 'rejected',
        'reopen' => 'open',
        default => null,
    };
}

function defectWorkflowCanAccessTask(PDO $db, int $defectId, int $userId): bool
{
    if (defectWorkflowIsReviewer()) {
        return true;
    }

    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM defects d
        JOIN users u ON u.id = :user_id
        LEFT JOIN defect_assignments da
          ON da.defect_id = d.id
         AND da.user_id = u.id
         AND da.status = 'active'
        WHERE d.id = :defect_id
          AND d.deleted_at IS NULL
          AND (
              da.id IS NOT NULL
              OR (u.contractor_id IS NOT NULL AND u.contractor_id = d.contractor_id)
          )
    ");
    $stmt->execute([
        ':user_id' => $userId,
        ':defect_id' => $defectId,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function defectWorkflowLoadForUpdate(PDO $db, int $defectId): array
{
    $stmt = $db->prepare("
        SELECT id, status, contractor_id, reported_by
        FROM defects
        WHERE id = :defect_id AND deleted_at IS NULL
        FOR UPDATE
    ");
    $stmt->execute([':defect_id' => $defectId]);
    $defect = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$defect) {
        throw new RuntimeException('Defect not found.');
    }

    return $defect;
}

function defectWorkflowAssertTransition(string $action, string $currentStatus): string
{
    $targetStatus = defectWorkflowTargetStatus($action);
    if ($targetStatus === null || !in_array($currentStatus, defectWorkflowAllowedFrom($action), true)) {
        throw new RuntimeException(sprintf(
            'This defect cannot move from %s using the requested action.',
            ucwords(str_replace('_', ' ', $currentStatus))
        ));
    }

    return $targetStatus;
}

function defectWorkflowLogHistory(PDO $db, int $defectId, int $userId, string $description): void
{
    $stmt = $db->prepare("
        INSERT INTO defect_history (defect_id, description, updated_by, created_at)
        VALUES (:defect_id, :description, :updated_by, NOW())
    ");
    $stmt->execute([
        ':defect_id' => $defectId,
        ':description' => $description,
        ':updated_by' => (string) $userId,
    ]);
}

function defectWorkflowAssignContractorUsers(PDO $db, int $defectId, int $contractorId, int $assignedBy): array
{
    $usersStmt = $db->prepare("
        SELECT id
        FROM users
        WHERE contractor_id = :contractor_id
          AND status = 'active'
          AND is_active = 1
    ");
    $usersStmt->execute([':contractor_id' => $contractorId]);
    $userIds = array_map('intval', $usersStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

    $existingStmt = $db->prepare('SELECT COUNT(*) FROM defect_assignments WHERE defect_id = :defect_id AND user_id = :user_id');
    $insertStmt = $db->prepare("
        INSERT INTO defect_assignments (defect_id, user_id, assigned_by, assigned_at, status)
        VALUES (:defect_id, :user_id, :assigned_by, NOW(), 'active')
    ");

    foreach ($userIds as $userId) {
        $existingStmt->execute([':defect_id' => $defectId, ':user_id' => $userId]);
        if ((int) $existingStmt->fetchColumn() > 0) {
            continue;
        }
        $insertStmt->execute([
            ':defect_id' => $defectId,
            ':user_id' => $userId,
            ':assigned_by' => $assignedBy,
        ]);
    }

    return $userIds;
}
