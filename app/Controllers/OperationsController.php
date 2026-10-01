<?php

declare(strict_types=1);

namespace Controllers;

use Core\Database;
use Core\Flash;
use Models\AuditLog;
use Models\Billing;
use Models\LogisticsData;
use Models\ReportSchedule;
use Models\Settings;
use Support\Report;

/**
 * The documents that come out of a job: the delivery note the receiver signs,
 * and the invoices finance raises from a finished trip.
 */
final class OperationsController
{
    /** Printable delivery note, generated from the delivery, its shipment and its trip. */
    public function deliveryNote(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        // Same visibility as the delivery itself: a driver only prints their own.
        if (LogisticsData::find('deliveries', $id, \current_context()) === null) {
            Flash::error('That delivery does not exist, or it is not visible to your role.');
            $this->redirect(\url('deliveries'));
        }

        $statement = Database::connection()->prepare(
            "SELECT dl.*, s.shipment_code, s.cargo_type, s.cargo_description, s.packages_count, s.weight_kg, s.volume_m3,
                    s.origin, s.destination AS shipment_destination, s.consignee_name, s.consignee_phone,
                    s.container_no, s.container_size, s.seal_no, s.trailer_plate, s.bill_of_lading, s.customer_reference,
                    s.special_instructions, s.temperature_min_c, s.temperature_max_c, s.is_hazardous,
                    t.reference_code AS trip_code, t.pickup_location, t.departure_at,
                    v.plate_number, v.make, v.model,
                    d.full_name AS driver_name, d.phone AS driver_phone, d.license_number,
                    c.customer_name, c.phone AS customer_phone, c.address AS customer_address, c.tin_number AS customer_tin,
                    r.lpo_number AS request_lpo, r.reference_code AS request_code,
                    u.full_name AS delivered_by_name
               FROM deliveries dl
               LEFT JOIN shipments s ON s.id = dl.shipment_id
               LEFT JOIN trips t ON t.id = dl.trip_id
               LEFT JOIN vehicles v ON v.id = t.vehicle_id
               LEFT JOIN drivers d ON d.id = t.driver_id
               LEFT JOIN customers c ON c.id = COALESCE(s.customer_id, t.customer_id)
               LEFT JOIN transport_requests r ON r.id = COALESCE(s.request_id, t.request_id)
               LEFT JOIN users u ON u.id = dl.delivered_by
              WHERE dl.id = ?"
        );
        $statement->execute([$id]);
        $note = $statement->fetch();

        $brand = Report::brand();
        $logo = null;
        if (!empty($brand['logo']) && is_file($brand['logo'])) {
            $mime = (string) (getimagesize($brand['logo'])['mime'] ?? 'image/png');
            $logo = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($brand['logo']));
        }

        AuditLog::record('delivery.note_printed', 'deliveries', (string) $note['delivery_code']);

        \view('deliveries/note', [
            'note' => $note,
            'company' => [
                'name' => Settings::get('company_name', 'LMS Logistics'),
                'details' => Report::companyDetails(),
                'color' => '#' . $brand['rgb'],
                'logo' => $logo,
            ],
        ]);
    }

    /** Raises draft invoices for every customer carried on a trip. */
    public function invoiceTrip(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        if (LogisticsData::find('trips', $id, \current_context()) === null) {
            Flash::error('That trip does not exist, or it is not visible to your role.');
            $this->redirect(\url('trips'));
        }

        try {
            $result = Billing::invoiceTrip($id);
        } catch (\Throwable $exception) {
            error_log('Invoice from trip failed: ' . $exception->getMessage());
            $result = ['ok' => false, 'message' => 'The invoice could not be raised. Nothing was saved; try again or raise it by hand.', 'invoices' => []];
        }

        if (!$result['ok']) {
            Flash::error($result['message']);
            $this->redirect(\url(['trips', $id]));
        }

        Flash::success($result['message']);

        // One invoice: open it, because checking it is the next thing to do.
        $invoices = $result['invoices'];
        $this->redirect(count($invoices) === 1 ? \url(['invoices', $invoices[0]['id']]) : \url('invoices', ['q' => '']));
    }

    /** "Send now" on a report schedule. */
    public function sendReport(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $result = ReportSchedule::send($id);
            $result['sent'] > 0 ? Flash::success($result['status']) : Flash::error($result['status']);
        } catch (\Throwable $exception) {
            error_log('Report email failed: ' . $exception->getMessage());
            Flash::error('The report could not be sent: ' . $exception->getMessage());
        }

        $this->redirect(\url(['report_emails', $id]));
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }
}
