<?php
declare(strict_types=1);
namespace DefectTracker\Suite;

use PDO;
use RuntimeException;

/** Internal staging component, not a replacement for route authorization. */
final class ProjectScope
{
    public function __construct(private readonly Gateway $gateway, private readonly PDO $db, private readonly array $binding)
    {
        new Gateway($binding);
    }

    /** Revalidate Suite permission and the database binding before each operation. */
    public function current(string $token): array
    {
        $identity = $this->gateway->validate($token);
        foreach (['instance_id', 'organization_id', 'project_id', 'local_project_id'] as $field) {
            if ($identity[$field] !== $this->binding[$field]) throw new RuntimeException('Project binding mismatch.');
        }
        $this->assertDatabase();
        return $identity;
    }

    /** Read-only local preflight; it does not establish user authorization. */
    public function assertDatabase(): void
    {
        $rows = $this->db->query('SELECT instance_id, organization_id, suite_project_id, local_project_id, module_key FROM suite_instance_binding')->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 1) throw new RuntimeException('Exactly one database binding required.');
        $row = $rows[0];
        foreach (['instance_id' => 'instance_id', 'organization_id' => 'organization_id', 'suite_project_id' => 'project_id', 'local_project_id' => 'local_project_id'] as $column => $field) {
            $value = $row[$column] ?? null;
            if ((!is_int($value) && !(is_string($value) && ctype_digit($value))) || (int) $value !== ($this->binding[$field] ?? null)) {
                throw new RuntimeException('Database project binding mismatch.');
            }
        }
        if (($row['module_key'] ?? '') !== 'defects') throw new RuntimeException('Database module binding mismatch.');
        $projects = $this->db->query('SELECT id FROM projects')->fetchAll(PDO::FETCH_COLUMN);
        if (count($projects) !== 1 || (int) $projects[0] !== $this->binding['local_project_id']) throw new RuntimeException('Staging requires one bound local project.');
    }

    /** Reject caller-supplied project overrides; never substitute an unscoped query. */
    public function requireProject(array $currentIdentity, mixed $requestedProject): int
    {
        if (!is_int($requestedProject) && !(is_string($requestedProject) && preg_match('/^[1-9][0-9]*$/D', $requestedProject))) {
            throw new RuntimeException('Invalid project selection.');
        }
        if ((int) $requestedProject !== ($currentIdentity['local_project_id'] ?? null) || (int) $requestedProject !== ($this->binding['local_project_id'] ?? null)) {
            throw new RuntimeException('Project selection denied.');
        }
        return (int) $requestedProject;
    }
}
