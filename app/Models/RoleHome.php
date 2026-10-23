<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;

/**
 * One home screen per role (design rule 3, section 20): only what that person
 * must do next, each with a count and a link. Everything is read for the
 * company the person works in, or the whole group for the head of the business.
 */
final class RoleHome
{
    private static function db(): PDO
    {
        return Database::connection();
    }

    private static function count(string $sql, string $table, string $alias): int
    {
        $company = Company::condition($table, $alias . '.company_id');
        $sql .= $company !== null ? (stripos($sql, ' WHERE ') !== false ? ' AND ' : ' WHERE ') . $company : '';
        try {
            return (int) self::db()->query($sql)->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array<string, float> currency => sum */
    private static function sums(string $sql, string $table, string $alias): array
    {
        $company = Company::condition($table, $alias . '.company_id');
        $where = $company !== null ? (stripos($sql, ' WHERE ') !== false ? ' AND ' : ' WHERE ') . $company : '';
        $parts = preg_split('/\s+GROUP BY\s+/i', $sql, 2);
        $sql = $parts[0] . $where . (isset($parts[1]) ? ' GROUP BY ' . $parts[1] : '');
        try {
            $sums = [];
            foreach (self::db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $sums[(string) $row['currency']] = (float) $row['total'];
            }

            return $sums;
        } catch (\Throwable) {
            return [];
        }
    }

    private static function money(array $sums): string
    {
        return $sums === [] ? '' : implode(' · ', array_map(static fn (string $code, float $sum): string => Currency::format($sum, $code), array_keys($sums), $sums));
    }

    /**
     * The cards of a role.
     *
     * @return list<array{label: string, count: int|string, detail: string, url: string, icon: string, tone: string}>
     */
    public static function cards(string $role): array
    {
        $cards = [];
        $add = static function (string $label, int|string $count, string $url, string $icon, string $tone = 'primary', string $detail = '') use (&$cards): void {
            $cards[] = ['label' => $label, 'count' => $count, 'detail' => $detail, 'url' => $url, 'icon' => $icon, 'tone' => $tone];
        };
        $inbox = RequestForms::inbox(300);
        $waitingStep = static fn (string $step): int => count(array_filter($inbox, static fn (array $r): bool => $r['step'] === $step));
        $statusCount = static fn (string $status): int => self::count("SELECT COUNT(*) FROM vehicles v WHERE v.deleted_at IS NULL AND v.status = '{$status}'", 'vehicles', 'v');
        $expiring = self::count("SELECT COUNT(*) FROM vehicle_documents d WHERE d.deleted_at IS NULL AND d.status <> 'cancelled' AND d.expires_on <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)", 'vehicle_documents', 'd')
            + self::count("SELECT COUNT(*) FROM driver_documents d WHERE d.deleted_at IS NULL AND d.status <> 'rejected' AND d.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)", 'driver_documents', 'd');
        $serviceDue = 0;
        foreach (self::db()->query("SELECT * FROM vehicles WHERE deleted_at IS NULL AND status <> 'inactive'" . (($c = Company::condition('vehicles', 'company_id')) !== null ? " AND {$c}" : ''))->fetchAll(PDO::FETCH_ASSOC) as $vehicle) {
            if (Fleet::serviceState($vehicle)['state'] !== 'ok') {
                $serviceDue++;
            }
        }
        $late = self::count("SELECT COUNT(*) FROM trips t WHERE t.deleted_at IS NULL AND t.return_due_at < NOW() AND t.status IN ('at_turnaround', 'loading', 'on_road', 'offloading', 'returning')", 'trips', 't');

        switch ($role) {
            case 'management':
                $add('Requests to authorize', $waitingStep('authorize'), \url('approvals'), 'award', 'warning', self::money(array_reduce(array_filter($inbox, static fn (array $r): bool => $r['step'] === 'authorize'), static function (array $carry, array $r): array {
                    $carry[$r['currency']] = ($carry[$r['currency']] ?? 0) + (float) $r['total_amount'];

                    return $carry;
                }, [])));
                $add('Trucks on the road', self::count("SELECT COUNT(*) FROM vehicles v WHERE v.deleted_at IS NULL AND v.status IN ('on_road', 'loading', 'offloading', 'returning')", 'vehicles', 'v'), \url('tracking'), 'map', 'primary');
                $add('Trips past their time', $late, \url('trips', ['status' => 'returning']), 'clock', $late > 0 ? 'danger' : 'success');
                $add('Money waiting for approval', self::money(self::sums("SELECT r.currency, SUM(r.total_amount) AS total FROM form_requests r WHERE r.deleted_at IS NULL AND r.status IN ('sent', 'verified') GROUP BY r.currency", 'form_requests', 'r')) ?: '—', \url('form_requests', ['status' => 'verified']), 'dollar-sign', 'info');
                $add('Spent this month', self::money(self::sums("SELECT e.currency, SUM(l.debit) AS total FROM gl_journal_lines l INNER JOIN gl_journal_entries e ON e.id = l.entry_id INNER JOIN gl_accounts a ON a.id = l.account_id WHERE e.deleted_at IS NULL AND e.status = 'posted' AND a.account_type IN ('expense', 'cost_of_sales') AND e.entry_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') GROUP BY e.currency", 'gl_journal_entries', 'e')) ?: '—', \url('reports'), 'trending-up', 'secondary',
                    'Last month: ' . (self::money(self::sums("SELECT e.currency, SUM(l.debit) AS total FROM gl_journal_lines l INNER JOIN gl_journal_entries e ON e.id = l.entry_id INNER JOIN gl_accounts a ON a.id = l.account_id WHERE e.deleted_at IS NULL AND e.status = 'posted' AND a.account_type IN ('expense', 'cost_of_sales') AND e.entry_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH) AND e.entry_date < DATE_FORMAT(CURDATE(), '%Y-%m-01') GROUP BY e.currency", 'gl_journal_entries', 'e')) ?: 'nothing'));
                $add('Documents expiring in 30 days', $expiring, \url('vehicle_documents', ['status' => 'expiring']), 'shield', $expiring > 0 ? 'warning' : 'success');
                break;

            case 'logistics_manager':
                foreach (['available' => ['Available', 'check-circle', 'success'], 'assigned' => ['Assigned', 'clipboard', 'info'], 'on_road' => ['On the road', 'navigation', 'primary'], 'in_garage' => ['In garage', 'tool', 'warning'], 'off_road' => ['Off road', 'x-octagon', 'danger']] as $status => [$label, $icon, $tone]) {
                    $add('Trucks ' . strtolower($label), $statusCount($status), \url('vehicles', ['status' => $status]), $icon, $tone);
                }
                $add('Trips waiting for money', self::count("SELECT COUNT(*) FROM trips t WHERE t.deleted_at IS NULL AND t.status = 'planned'", 'trips', 't'), \url('trips', ['status' => 'planned']), 'clock', 'warning');
                $add('Drivers not confirmed the money', self::count("SELECT COUNT(*) FROM payment_batch_lines bl INNER JOIN payment_batches b ON b.id = bl.batch_id WHERE bl.payee_type = 'driver' AND bl.status = 'paid' AND bl.confirmed_at IS NULL", 'payment_batches', 'b'), \url('payment_batches'), 'smartphone', 'warning');
                $add('Trips past their time', $late, \url('trips'), 'alert-triangle', $late > 0 ? 'danger' : 'success');
                $add('Support requests open', self::count("SELECT COUNT(*) FROM support_requests s WHERE s.deleted_at IS NULL AND s.status = 'open'", 'support_requests', 's'), \url('support_requests', ['status' => 'open']), 'life-buoy', 'danger');
                $add('To confirm (delivery notes, handovers)', $waitingStep('confirm'), \url('approvals'), 'check-square', 'warning');
                $add('Requests returned to you', self::count("SELECT COUNT(*) FROM form_requests r WHERE r.deleted_at IS NULL AND r.status = 'returned' AND r.prepared_by = " . (int) \current_user_id(), 'form_requests', 'r'), \url('form_requests', ['status' => 'returned']), 'corner-up-left', 'danger');
                $add('Fuel top-ups to send', self::count("SELECT COUNT(*) FROM form_requests r WHERE r.deleted_at IS NULL AND r.form_code = 'F2' AND r.status = 'draft'", 'form_requests', 'r'), \url('form_requests', ['form' => 'F2', 'status' => 'draft']), 'droplet', 'warning');
                $add('Documents expiring in 30 days', $expiring, \url('vehicle_documents', ['status' => 'expiring']), 'shield', $expiring > 0 ? 'warning' : 'success');
                $add('Service due or soon', $serviceDue, \url('vehicles'), 'tool', $serviceDue > 0 ? 'warning' : 'success');
                $add('MoMo changes to approve', self::count("SELECT COUNT(*) FROM driver_momo_changes m WHERE m.status = 'pending'", 'driver_momo_changes', 'm'), \url('driver_momo_changes', ['status' => 'pending']), 'smartphone', 'warning');
                // Drivers (the OM also does what HR used to).
                $add('Active drivers', self::count("SELECT COUNT(*) FROM drivers d WHERE d.deleted_at IS NULL AND d.status IN ('active', 'on_trip')", 'drivers', 'd'), \url('drivers', ['status' => 'active']), 'users', 'primary');
                $add('Driver documents expiring', self::count("SELECT COUNT(*) FROM driver_documents d WHERE d.deleted_at IS NULL AND d.status <> 'rejected' AND d.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)", 'driver_documents', 'd'), \url('driver_documents'), 'file-text', 'warning');
                $add('Phones not heard from in 24 h', self::count("SELECT COUNT(*) FROM drivers d WHERE d.deleted_at IS NULL AND d.status = 'on_trip' AND (d.last_seen_at IS NULL OR d.last_seen_at < DATE_SUB(NOW(), INTERVAL " . max(1, Settings::int('driver_unreachable_hours', 24)) . ' HOUR))', 'drivers', 'd'), \url('drivers', ['status' => 'on_trip']), 'wifi-off', 'danger');
                break;

            case 'finance':
                $add('Requests to verify', $waitingStep('verify'), \url('approvals'), 'check-square', 'warning');
                $add('Waiting for the MD', self::count("SELECT COUNT(*) FROM form_requests r WHERE r.deleted_at IS NULL AND r.status = 'verified'", 'form_requests', 'r'), \url('form_requests', ['status' => 'verified']), 'award', 'info');
                $add('Customer invoices unpaid', self::money(self::sums("SELECT i.currency, SUM(i.total_amount - i.amount_paid) AS total FROM invoices i WHERE i.deleted_at IS NULL AND i.status IN ('issued', 'partially_paid', 'overdue') GROUP BY i.currency", 'invoices', 'i')) ?: '—', \url('invoices'), 'file-text', 'primary');
                $add('Supplier bills to pay', self::money(self::sums("SELECT b.currency, SUM(b.total_amount - b.amount_paid) AS total FROM supplier_bills b WHERE b.deleted_at IS NULL AND b.status IN ('approved', 'partially_paid') GROUP BY b.currency", 'supplier_bills', 'b')) ?: '—', \url('supplier_bills'), 'inbox', 'secondary');
                $add('Fuel checks flagged', self::count("SELECT COUNT(*) FROM trips t WHERE t.deleted_at IS NULL AND t.fuel_flag IS NOT NULL AND t.closed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", 'trips', 't'), \url('trips', ['status' => 'closed']), 'droplet', 'warning');
                $add('Delivery notes with shortage', self::count("SELECT COUNT(*) FROM form_requests r WHERE r.deleted_at IS NULL AND r.form_code = 'F8' AND JSON_EXTRACT(r.payload, '$.shortage') > 0 AND r.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", 'form_requests', 'r'), \url('form_requests', ['form' => 'F8']), 'alert-circle', 'warning');
                break;

            case 'cashier':
                $toPay = Payments::toPay();
                $sums = [];
                foreach ($toPay as $row) {
                    $sums[$row['currency']] = ($sums[$row['currency']] ?? 0) + (float) $row['amount'];
                }
                $add('Lines to pay', count($toPay), \url('payment_batches'), 'dollar-sign', 'warning', self::money($sums));
                $add('Batches waiting for MTN proof', self::count("SELECT COUNT(*) FROM payment_batches b WHERE b.status = 'built' AND b.method <> 'cash'", 'payment_batches', 'b'), \url('payment_batches'), 'upload', 'info');
                $add('Cash lines to pay at the counter', self::count("SELECT COUNT(*) FROM payment_batch_lines bl INNER JOIN payment_batches b ON b.id = bl.batch_id WHERE b.method = 'cash' AND bl.status = 'pending'", 'payment_batches', 'b'), \url('payment_batches'), 'archive', 'info');
                $cash = [];
                foreach (array_keys(Currency::options()) as $code) {
                    $balance = Payments::cashBox(\current_company_id(), $code);
                    if (abs($balance) > 0.004) {
                        $cash[$code] = $balance;
                    }
                }
                $add('Cash box', self::money($cash) ?: '0', \url('payment_batches'), 'briefcase', 'secondary');
                break;

            case 'warehouse_manager':
                $add('Issue notes to issue', self::count("SELECT COUNT(*) FROM form_requests r WHERE r.deleted_at IS NULL AND r.form_code = 'F4' AND r.status = 'sent'", 'form_requests', 'r'), \url('store_issues'), 'package', 'warning');
                $add('Job cards open', self::count("SELECT COUNT(*) FROM maintenance_orders m WHERE m.deleted_at IS NULL AND m.status IN ('open', 'scheduled', 'in_progress')", 'maintenance_orders', 'm'), \url('garage'), 'tool', 'primary');
                $add('Orders to receive', self::count("SELECT COUNT(*) FROM form_requests r WHERE r.deleted_at IS NULL AND r.form_code = 'F5' AND r.status = 'authorized' AND EXISTS (SELECT 1 FROM form_request_lines l WHERE l.request_id = r.id AND l.quantity > l.done_quantity)", 'form_requests', 'r'), \url('store_issues'), 'download', 'info');
                $add('Items below minimum', self::count('SELECT COUNT(*) FROM inventory_items i WHERE i.deleted_at IS NULL AND i.minimum_level > 0 AND i.quantity <= i.minimum_level', 'inventory_items', 'i'), \url('warehouse', ['status' => 'reorder']), 'alert-triangle', 'danger');
                $add('Service due or soon', $serviceDue, \url('vehicles'), 'alert-circle', $serviceDue > 0 ? 'warning' : 'success');
                $add('Stock counts open', self::count("SELECT COUNT(*) FROM stock_counts c WHERE c.status IN ('draft', 'rejected')", 'stock_counts', 'c'), \url('stock_counts'), 'check-square', 'info');
                break;
        }

        return $cards;
    }
}
