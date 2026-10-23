<?php

declare(strict_types=1);

namespace Models;

final class AuditLog extends BaseModel
{
    /**
     * One line of the audit log (section 21): who, role, company, the record,
     * the values before and after, the time and the device. The database
     * refuses any change or deletion of it.
     */
    public static function record(string $action, string $entityType, ?string $entityId = null, ?string $reason = null, array $metadata = [], ?array $before = null, ?array $after = null): void
    {
        try {
            $logger = new self();
            // Passwords and signatures never go into the log, even hashed.
            $clean = static function (?array $values): ?string {
                if ($values === null || $values === []) {
                    return null;
                }
                unset($values['password_hash'], $values['cash_pin_hash'], $values['api_password'], $values['api_key'], $values['whatsapp_access_token']);

                return json_encode($values, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            };
            $statement = $logger->db->prepare(
                'INSERT INTO audit_logs (company_id, user_id, role_key, action_name, entity_type, entity_id, reason, metadata, before_values, after_values, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                \is_logged_in() ? \current_company_id() : null,
                \current_user_id(),
                \is_logged_in() ? \current_role() : null,
                $action,
                $entityType,
                $entityId !== null ? mb_substr($entityId, 0, 64) : null,
                $reason,
                $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
                $clean($before),
                $clean($after),
                substr((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? (PHP_SAPI === 'cli' ? 'console' : '')), 0, 45) ?: null,
                mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? (PHP_SAPI === 'cli' ? 'console' : '')), 0, 255) ?: null,
            ]);
        } catch (\Throwable) {
            // Audit logging must not break the user-facing workflow.
        }
    }
}
