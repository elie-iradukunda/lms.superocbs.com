<?php

declare(strict_types=1);

namespace Models;

/**
 * The updates one person sees.
 *
 * An update sent to a person is theirs to read. An update sent to a role is
 * one row for everybody holding it, and who has read it is kept per person in
 * `notification_reads`, so one accountant reading it does not clear it for the
 * others. Rows marked read before that table existed stay read.
 */
class Notification extends BaseModel
{
    private const COLUMNS = 'n.id, n.notification_key, n.user_id, n.title, n.message, n.link_route, n.entity_type, n.entity_id, n.severity, n.created_at';

    private const VISIBLE = '(n.user_id = ? OR (n.user_id IS NULL AND (n.role_key = ? OR n.role_key IS NULL) AND %s))';

    private const READ = 'CASE WHEN n.user_id IS NOT NULL THEN n.is_read WHEN n.is_read = 1 OR r.user_id IS NOT NULL THEN 1 ELSE 0 END';

    /**
     * An update sent to a role belongs to one company: only the people of that
     * company see it, except whoever may see the whole group (section 3).
     */
    private static function visible(): string
    {
        $company = \is_logged_in() && !\can_see_all_companies()
            ? '(n.company_id IS NULL OR n.company_id = ' . (int) \home_company_id() . ')'
            : '1 = 1';

        return sprintf(self::VISIBLE, $company);
    }

    public function forCurrentUser(?int $userId, string $roleKey, int $limit = 6): array
    {
        return $this->page($userId, $roleKey, false, $limit, 0);
    }

    /** @return list<array<string, mixed>> */
    public function page(?int $userId, string $roleKey, bool $unreadOnly, int $limit, int $offset): array
    {
        if ($userId === null) {
            return [];
        }

        try {
            $statement = $this->db->prepare(
                'SELECT ' . self::COLUMNS . ', ' . self::READ . ' AS is_read
                   FROM notifications n
                   LEFT JOIN notification_reads r ON r.notification_id = n.id AND r.user_id = ?
                  WHERE ' . self::visible() . ($unreadOnly ? ' AND (' . self::READ . ') = 0' : '') . '
                  ORDER BY is_read ASC, n.created_at DESC, n.id DESC
                  LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset)
            );
            $statement->execute([$userId, $userId, $roleKey]);

            return $statement->fetchAll();
        } catch (\Throwable) {
            // Before the per-person table exists: the shared flag.
            $statement = $this->db->prepare(
                'SELECT ' . self::COLUMNS . ', n.is_read FROM notifications n WHERE ' . self::visible() . ($unreadOnly ? ' AND n.is_read = 0' : '') . '
                  ORDER BY n.is_read ASC, n.created_at DESC, n.id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset)
            );
            $statement->execute([$userId, $roleKey]);

            return $statement->fetchAll();
        }
    }

    public function total(?int $userId, string $roleKey, bool $unreadOnly): int
    {
        if ($userId === null) {
            return 0;
        }

        try {
            $statement = $this->db->prepare(
                'SELECT COUNT(*) FROM notifications n
                   LEFT JOIN notification_reads r ON r.notification_id = n.id AND r.user_id = ?
                  WHERE ' . self::visible() . ($unreadOnly ? ' AND (' . self::READ . ') = 0' : '')
            );
            $statement->execute([$userId, $userId, $roleKey]);
        } catch (\Throwable) {
            $statement = $this->db->prepare('SELECT COUNT(*) FROM notifications n WHERE ' . self::visible() . ($unreadOnly ? ' AND n.is_read = 0' : ''));
            $statement->execute([$userId, $roleKey]);
        }

        return (int) $statement->fetchColumn();
    }

    public function unreadCount(?int $userId, string $roleKey): int
    {
        return $this->total($userId, $roleKey, true);
    }

    /** @return list<array<string, mixed>> unread updates newer than an id, oldest first, for the pop-ups */
    public function newerThan(?int $userId, string $roleKey, int $afterId, int $limit = 5): array
    {
        if ($userId === null) {
            return [];
        }
        try {
            $statement = $this->db->prepare(
                'SELECT ' . self::COLUMNS . ' FROM notifications n
                   LEFT JOIN notification_reads r ON r.notification_id = n.id AND r.user_id = ?
                  WHERE n.id > ? AND ' . self::visible() . ' AND (' . self::READ . ') = 0
                  ORDER BY n.id DESC LIMIT ' . max(1, $limit)
            );
            $statement->execute([$userId, $afterId, $userId, $roleKey]);

            return array_reverse($statement->fetchAll());
        } catch (\Throwable) {
            return [];
        }
    }

    public function latestId(): int
    {
        return (int) $this->db->query('SELECT COALESCE(MAX(id), 0) FROM notifications')->fetchColumn();
    }
}
