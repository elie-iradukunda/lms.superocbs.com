<?php

declare(strict_types=1);

namespace Models;

class Notification extends BaseModel
{
    public function forCurrentUser(?int $userId, string $roleKey, int $limit = 6): array
    {
        if ($userId === null) {
            return [];
        }

        $statement = $this->db->prepare(
            'SELECT notification_key, title, message, link_route, severity, is_read, created_at
             FROM notifications
             WHERE user_id = ?
                OR (user_id IS NULL AND (role_key = ? OR role_key IS NULL))
             ORDER BY is_read ASC, created_at DESC, id DESC
             LIMIT ' . max(1, $limit)
        );
        $statement->execute([$userId, $roleKey]);

        return $statement->fetchAll();
    }

    public function unreadCount(?int $userId, string $roleKey): int
    {
        if ($userId === null) {
            return 0;
        }

        $statement = $this->db->prepare(
            'SELECT COUNT(*)
             FROM notifications
             WHERE is_read = 0
               AND (
                    user_id = ?
                    OR (user_id IS NULL AND (role_key = ? OR role_key IS NULL))
               )'
        );
        $statement->execute([$userId, $roleKey]);

        return (int) $statement->fetchColumn();
    }
}
