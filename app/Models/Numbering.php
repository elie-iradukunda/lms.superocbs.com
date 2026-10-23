<?php

declare(strict_types=1);

namespace Models;

use Core\Database;

/**
 * Document numbers per company, from a format Admin sets:
 *
 *   FUEL ORDER {SEQ}/{MONTHNAME}/{YEAR}  ->  FUEL ORDER 040/OCTOBER/2026
 *   BATCH-{YEAR}-{MONTH}-{SEQ}           ->  BATCH-2026-10-031
 *
 * The running number restarts every month, every year or never, and is taken
 * under a row lock so two people sending at the same moment never share one.
 */
final class Numbering
{
    public static function next(int $companyId, string $key, string $format, string $reset = 'month', ?string $date = null): string
    {
        $time = $date !== null ? (int) strtotime($date) : time();
        $period = match ($reset) {
            'year' => date('Y', $time),
            'never' => 'all',
            default => date('Y-m', $time),
        };

        $db = Database::connection();
        $owning = !$db->inTransaction();
        if ($owning) {
            $db->beginTransaction();
        }

        try {
            $db->prepare('INSERT IGNORE INTO document_sequences (company_id, sequence_key, period_key, last_no) VALUES (?, ?, ?, 0)')
                ->execute([$companyId, $key, $period]);
            $select = $db->prepare('SELECT last_no FROM document_sequences WHERE company_id = ? AND sequence_key = ? AND period_key = ? FOR UPDATE');
            $select->execute([$companyId, $key, $period]);
            $number = (int) $select->fetchColumn() + 1;
            $db->prepare('UPDATE document_sequences SET last_no = ? WHERE company_id = ? AND sequence_key = ? AND period_key = ?')
                ->execute([$number, $companyId, $key, $period]);
            if ($owning) {
                $db->commit();
            }
        } catch (\Throwable $exception) {
            if ($owning && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        return self::format($format, $number, $time);
    }

    public static function format(string $format, int $number, ?int $time = null): string
    {
        $time ??= time();
        $replace = [
            '{SEQ}' => str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            '{SEQ4}' => str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            '{MONTH}' => date('m', $time),
            '{MONTHNAME}' => strtoupper(date('F', $time)),
            '{YEAR}' => date('Y', $time),
            '{YY}' => date('y', $time),
        ];
        $result = strtr($format, $replace);

        // A format without a running number would give every document the same one.
        if (!str_contains($format, '{SEQ')) {
            $result .= '-' . $replace['{SEQ}'];
        }

        return $result;
    }
}
