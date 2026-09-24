<?php

declare(strict_types=1);

namespace Controllers;

use Core\Database;
use Models\AuditLog;

/** A searchable, paginated view of the audit trail that was previously only a 20-line strip. */
final class AuditController
{
    private const PER_PAGE = 40;

    public function index(): void
    {
        [$where, $parameters] = $this->conditions();

        $total = (int) $this->scalar("SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE {$where}", $parameters);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($pages, (int) ($_GET['page'] ?? 1)));
        $offset = ($page - 1) * self::PER_PAGE;

        $statement = Database::connection()->prepare(
            "SELECT a.id, a.action_name, a.entity_type, a.entity_id, a.reason, a.metadata, a.created_at,
                    COALESCE(u.full_name, 'System') AS actor, u.email AS actor_email
               FROM audit_logs a
               LEFT JOIN users u ON u.id = a.user_id
              WHERE {$where}
              ORDER BY a.id DESC
              LIMIT " . self::PER_PAGE . " OFFSET {$offset}"
        );
        $statement->execute($parameters);

        \view('admin/audit', [
            'title' => 'Audit trail',
            'entries' => $statement->fetchAll(),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'search' => (string) ($_GET['q'] ?? ''),
            'entity' => (string) ($_GET['entity'] ?? ''),
            'entities' => $this->entities(),
        ]);
    }

    public function export(): void
    {
        [$where, $parameters] = $this->conditions();

        AuditLog::record('audit.exported', 'audit', null, null, ['filters' => array_intersect_key($_GET, array_flip(['q', 'entity', 'from', 'to']))]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="audit-trail-' . date('Y-m-d') . '.csv"');

        $statement = Database::connection()->prepare(
            "SELECT a.created_at, COALESCE(u.full_name, 'System') AS actor, a.action_name, a.entity_type, a.entity_id, a.reason
               FROM audit_logs a
               LEFT JOIN users u ON u.id = a.user_id
              WHERE {$where}
              ORDER BY a.id DESC
              LIMIT 5000"
        );
        $statement->execute($parameters);

        $output = fopen('php://output', 'wb');
        fputcsv($output, ['When', 'Who', 'Action', 'Entity', 'Reference', 'Reason']);
        while (($row = $statement->fetch()) !== false) {
            fputcsv($output, array_values($row));
        }
        fclose($output);
        exit;
    }

    private function conditions(): array
    {
        $conditions = ['1 = 1'];
        $parameters = [];

        $search = trim((string) ($_GET['q'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(a.action_name LIKE ? OR a.entity_id LIKE ? OR a.reason LIKE ? OR u.full_name LIKE ?)';
            array_push($parameters, "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%");
        }

        $entity = trim((string) ($_GET['entity'] ?? ''));
        if ($entity !== '' && in_array($entity, $this->entities(), true)) {
            $conditions[] = 'a.entity_type = ?';
            $parameters[] = $entity;
        }

        foreach ([['from', '>='], ['to', '<=']] as [$field, $operator]) {
            $value = trim((string) ($_GET[$field] ?? ''));
            if ($value !== '' && strtotime($value) !== false) {
                $conditions[] = "DATE(a.created_at) {$operator} ?";
                $parameters[] = date('Y-m-d', (int) strtotime($value));
            }
        }

        return [implode(' AND ', $conditions), $parameters];
    }

    private function entities(): array
    {
        static $entities = null;

        if ($entities === null) {
            $entities = array_map(
                'strval',
                Database::connection()->query('SELECT DISTINCT entity_type FROM audit_logs ORDER BY entity_type')->fetchAll(\PDO::FETCH_COLUMN)
            );
        }

        return $entities;
    }

    private function scalar(string $sql, array $parameters): mixed
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn();
    }
}
