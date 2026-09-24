<?php

declare(strict_types=1);

namespace Models;

use Core\Database;

/**
 * Generates the next human reference for a record (TRP-0042, INV-2026-0007) when
 * the user leaves the reference field blank, instead of making them invent one.
 */
final class Reference
{
    private const ALLOWED = [
        'warehouses' => 'warehouse_code',
        'gl_cheques' => 'reference',
        'border_crossings' => 'reference',
        'vehicle_documents' => 'document_code',
        'maintenance_orders' => 'work_order_code',
        'transport_requests' => 'reference_code',
        'trips' => 'reference_code',
        'shipments' => 'shipment_code',
        'deliveries' => 'delivery_code',
        'customers' => 'customer_code',
        'rate_cards' => 'rate_code',
        'invoices' => 'invoice_number',
        'payments' => 'payment_code',
        'fuel_records' => 'reference_code',
        'expenses' => 'reference_code',
        'inventory_items' => 'sku',
        'stock_movements' => 'movement_code',
        'warehouse_cargo' => 'movement_code',
        'purchase_requests' => 'request_code',
        'suppliers' => 'supplier_code',
    ];

    public static function next(string $prefix, string $table, string $column, int $width = 4): string
    {
        if ((self::ALLOWED[$table] ?? null) !== $column) {
            throw new \InvalidArgumentException('Reference column is not allowed.');
        }

        $prefix = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $prefix) ?: 'REF');
        $year = date('Y');
        $stem = $prefix . '-' . $year . '-';

        $statement = Database::connection()->prepare(
            "SELECT {$column} FROM {$table} WHERE {$column} LIKE ? ORDER BY LENGTH({$column}) DESC, {$column} DESC LIMIT 1"
        );
        $statement->execute([$stem . '%']);
        $last = (string) ($statement->fetchColumn() ?: '');

        $sequence = 1;
        if ($last !== '' && preg_match('/(\d+)$/', $last, $matches) === 1) {
            $sequence = (int) $matches[1] + 1;
        }

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $candidate = $stem . str_pad((string) ($sequence + $attempt), $width, '0', STR_PAD_LEFT);
            $check = Database::connection()->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?");
            $check->execute([$candidate]);
            if ((int) $check->fetchColumn() === 0) {
                return $candidate;
            }
        }

        return $stem . strtoupper(bin2hex(random_bytes(3)));
    }
}
