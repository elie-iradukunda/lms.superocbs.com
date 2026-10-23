<?php

declare(strict_types=1);

namespace Models;

use Support\FormPdf;
use Support\Mailer;

/**
 * The trip sheet (section 7, "Departed"): the full sheet goes to the driver by
 * email, and a short summary to their phone. It names the route and its legs,
 * the truck and trailer, the cargo, the expected days and the OM to call.
 */
final class TripSheet
{
    private const M = 36.0;

    public static function render(int $tripId): ?string
    {
        $trip = TripLife::find($tripId);
        if ($trip === null) {
            return null;
        }
        $company = Company::header((int) $trip['company_id']);
        $pdf = new FormPdf('Trip sheet ' . $trip['reference_code']);
        $pdf->text(self::M, self::M, strtoupper($company['name']), 13, true);
        $pdf->text(self::M, self::M + 17, trim($company['address'] . '   ' . $company['phone']), 8, false, [0.42, 0.46, 0.52]);
        $pdf->line(self::M, self::M + 32, FormPdf::WIDTH - self::M, self::M + 32, 1.2, [0.12, 0.31, 0.55]);
        $pdf->text(self::M, self::M + 44, 'TRIP SHEET ' . $trip['reference_code'], 12, true);

        $top = self::M + 70;
        $facts = [
            'Route' => trim($trip['route_code'] . ' — ' . $trip['route_name']),
            'Truck' => (string) $trip['plate_number'],
            'Trailer' => (string) ($trip['trailer_number'] ?? '—'),
            'Driver' => (string) $trip['driver_name'],
            'Customer' => (string) ($trip['customer_name'] ?? '—'),
            'Cargo' => trim(($trip['cargo_quantity'] !== null ? rtrim(rtrim(number_format((float) $trip['cargo_quantity'], 2), '0'), '.') . ' ' . ($trip['cargo_type'] ?? '') : '') . ' ' . ($trip['container_numbers'] ?? '') . ' ' . ($trip['cargo_summary'] ?? '')),
            'Departure' => human_date((string) ($trip['departure_at'] ?: $trip['planned_departure_at']), true),
            'Expected back' => human_date((string) $trip['expected_back_at'], true),
            'Return limit' => $trip['max_return_days'] !== null ? rtrim(rtrim((string) $trip['max_return_days'], '0'), '.') . ' days from ' . ($trip['turnaround_point'] ?: 'the turnaround point') : '—',
        ];
        foreach ($facts as $label => $value) {
            $pdf->text(self::M, $top, $label, 9, true);
            $pdf->text(self::M + 100, $top, $value === '' ? '—' : $value, 9);
            $top += 15;
        }

        $legs = \Core\Database::connection()->prepare(
            'SELECT l.*, b.post_name FROM route_legs l LEFT JOIN border_posts b ON b.id = l.border_post_id WHERE l.route_id = ? ORDER BY l.leg_no'
        );
        $legs->execute([(int) $trip['route_id']]);
        $top += 10;
        $pdf->text(self::M, $top, 'Legs and border posts', 10, true);
        $top += 16;
        foreach ($legs->fetchAll(\PDO::FETCH_ASSOC) as $leg) {
            $text = sprintf('%d. %s → %s%s%s', $leg['leg_no'], $leg['from_place'], $leg['to_place'], $leg['distance_km'] ? ' (' . number_format((float) $leg['distance_km']) . ' km)' : '', $leg['post_name'] ? ' — border: ' . $leg['post_name'] : '');
            $pdf->text(self::M + 6, $top, $text, 9);
            $top += 13;
        }

        $top += 14;
        $pdf->paragraph(self::M, $top, FormPdf::WIDTH - 2 * self::M, 'In the app: tap Departed when you leave, Arrived at the turnaround point, Loading done, Offloading, then fill the delivery note at the customer and hand the truck over at the end. Report any problem or delay in the app at once.', 9);

        return $pdf->output();
    }

    /** Sent at departure: the sheet by email, a summary on the phone. */
    public static function send(int $tripId): void
    {
        $trip = TripLife::find($tripId);
        if ($trip === null) {
            return;
        }
        $summary = sprintf('%s: %s with %s%s. Expected back %s.', $trip['reference_code'], $trip['route_code'], $trip['plate_number'], $trip['trailer_number'] ? ' + ' . $trip['trailer_number'] : '', human_date((string) $trip['expected_back_at']));
        Notifier::toUser($trip['driver_user_id'] !== null ? (int) $trip['driver_user_id'] : null, 'Trip sheet', $summary, 'my-trips', 'info');

        $email = \Core\Database::connection()->prepare('SELECT u.email, u.full_name FROM drivers d INNER JOIN users u ON u.id = d.user_id WHERE d.id = ?');
        $email->execute([(int) $trip['driver_id']]);
        $person = $email->fetch(\PDO::FETCH_ASSOC);
        if ($person === false) {
            return;
        }
        $bytes = self::render($tripId);
        $attachments = [];
        if ($bytes !== null) {
            $directory = Mailer::uploadsRoot() . '/trip_sheets';
            if (!is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }
            $name = preg_replace('/[^A-Za-z0-9\-]+/', '-', (string) $trip['reference_code']) . '.pdf';
            file_put_contents($directory . '/' . $name, $bytes);
            $attachments[] = ['filename' => $name, 'path' => 'trip_sheets/' . $name];
        }
        Mailer::send([
            'key' => 'trip-sheet-' . $tripId,
            'category' => 'trip_sheet',
            'to' => (string) $person['email'],
            'to_name' => (string) $person['full_name'],
            'subject' => 'Trip sheet ' . $trip['reference_code'],
            'heading' => 'Your trip sheet',
            'lines' => [sprintf('Hello %s,', $person['full_name']), $summary, 'The full trip sheet is attached.'],
            'attachments' => $attachments,
            'entity_type' => 'trips',
            'entity_id' => (string) $tripId,
        ]);
    }
}
