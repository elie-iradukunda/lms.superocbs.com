<?php

declare(strict_types=1);

namespace Models;

final class AuditLog extends BaseModel
{
    public static function record(string $action, string $entityType, ?string $entityId = null, ?string $reason = null, array $metadata = []): void
    {
        try {
            $logger = new self();
            $statement = $logger->db->prepare(
                'INSERT INTO audit_logs (user_id, action_name, entity_type, entity_id, reason, metadata)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                \current_user_id(),
                $action,
                $entityType,
                $entityId,
                $reason,
                $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            ]);
        } catch (\Throwable) {
            // Audit logging must not break the user-facing workflow.
        }
    }
}
