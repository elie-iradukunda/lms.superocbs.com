<?php

declare(strict_types=1);

namespace Controllers;

use Core\Database;
use Core\Flash;
use Models\AuditLog;
use Models\Billing;
use Models\Containers;
use Models\Journey;
use Models\LogisticsData;
use Models\Payables;
use Models\ReportSchedule;
use Models\Settings;
use Models\Storage;
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

    /** "Hand cargo to next leg" on a trip that is a leg of a journey. */
    public function handOver(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $result = Journey::handOver($id);
            $result['ok'] ? Flash::success($result['message']) : Flash::error($result['message']);
        } catch (\Throwable $exception) {
            error_log('Handover failed: ' . $exception->getMessage());
            Flash::error('The cargo could not be handed over: ' . $exception->getMessage());
        }

        $this->redirect(\url(['trips', $id]));
    }

    /** "Create invoice" on a journey: billed once, across all legs. */
    public function invoiceJourney(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $legs = array_values(array_filter(Journey::legs($id), static fn (array $leg): bool => $leg['status'] !== 'cancelled'));
        if ($legs === []) {
            Flash::error('This journey has no legs yet.');
            $this->redirect(\url(['journeys', $id]));
        }

        $this->invoiceTrip(['id' => (string) $legs[0]['id']]);
    }

    /** "Add disbursements" on a draft invoice. */
    public function invoiceDisbursements(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        if (LogisticsData::find('invoices', $id, \current_context()) === null) {
            Flash::error('That invoice does not exist.');
            $this->redirect(\url('invoices'));
        }

        try {
            $added = Payables::addToInvoice($id);
            if ($added > 0) {
                AuditLog::record('invoice.disbursements_added', 'invoices', (string) $id, null, ['lines' => $added]);
                Flash::success(sprintf('%d disbursement%s added at cost, without VAT.', $added, $added === 1 ? '' : 's'));
            } else {
                Flash::error('Nothing is waiting to be recharged to this customer in this currency.');
            }
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
        }

        $this->redirect(\url(['invoices', $id]));
    }

    /** The goods received note for a storage lot. */
    public function storageGrn(array $params): void
    {
        $doc = $this->lotDocument((int) ($params['id'] ?? 0));
        if ($doc === null) {
            Flash::error('That storage lot does not exist.');
            $this->redirect(\url('storage_lots'));
        }

        AuditLog::record('storage.grn_printed', 'storage_lots', (string) $doc['lot_code']);
        \view('storage/document', ['kind' => 'grn', 'doc' => $doc, 'company' => $this->company()]);
    }

    /** The gate pass for one release from storage. */
    public function storagePass(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $statement = Database::connection()->prepare(
            'SELECT r.*, r.id AS release_id, r.vehicle_plate AS release_plate, u.full_name AS authorised_name, t.reference_code AS trip_code
               FROM storage_releases r
               LEFT JOIN users u ON u.id = r.authorised_by
               LEFT JOIN trips t ON t.id = r.trip_id
              WHERE r.id = ? AND r.deleted_at IS NULL'
        );
        $statement->execute([$id]);
        $release = $statement->fetch(\PDO::FETCH_ASSOC);
        $lot = $release === false ? null : $this->lotDocument((int) $release['lot_id']);
        if ($lot === null) {
            Flash::error('That gate pass does not exist.');
            $this->redirect(\url('storage_releases'));
        }

        // What was left in the bay once this pass had gone through.
        $before = Database::connection()->prepare(
            'SELECT COALESCE(SUM(quantity), 0) FROM storage_releases
              WHERE lot_id = ? AND deleted_at IS NULL AND (released_at < ? OR (released_at = ? AND id <= ?))'
        );
        $before->execute([(int) $release['lot_id'], $release['released_at'], $release['released_at'], $id]);
        $release['left_after'] = (float) $lot['quantity_received'] - (float) $before->fetchColumn();

        AuditLog::record('storage.pass_printed', 'storage_releases', (string) $release['gate_pass_no']);
        \view('storage/document', ['kind' => 'pass', 'doc' => $release + $lot, 'company' => $this->company()]);
    }

    /** "Bill storage to date" on a lot: bills every lot of that customer. */
    public function billStorage(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $lot = Storage::lot($id);
        if ($lot === null) {
            Flash::error('That storage lot does not exist.');
            $this->redirect(\url('storage_lots'));
        }

        try {
            $result = Storage::bill((int) $lot['customer_id']);
            if (!$result['ok']) {
                Flash::error($result['message']);
                $this->redirect(\url(['storage_lots', $id]));
            }
            Flash::success($result['message'] . ' Check and issue.');
            $this->redirect(count($result['invoices']) === 1 ? \url(['invoices', $result['invoices'][0]]) : \url('invoices'));
        } catch (\Throwable $exception) {
            error_log('Storage billing failed: ' . $exception->getMessage());
            Flash::error('The storage invoice could not be raised: ' . $exception->getMessage());
            $this->redirect(\url(['storage_lots', $id]));
        }
    }

    /** "Recharge detention to customer" on a container. */
    public function rechargeContainer(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        try {
            $disbursementId = Containers::recharge($id);
            Flash::success('Detention added to the customer\'s disbursements. It goes on their next invoice at cost.');
            $this->redirect(\url(['disbursements', $disbursementId]));
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
            $this->redirect(\url(['containers', $id]));
        }
    }

    private function lotDocument(int $lotId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT l.*, c.customer_name, c.tin_number, c.phone AS customer_phone, w.warehouse_name, s.shipment_code
               FROM storage_lots l
               INNER JOIN customers c ON c.id = l.customer_id
               INNER JOIN warehouses w ON w.id = l.warehouse_id
               LEFT JOIN shipments s ON s.id = l.shipment_id
              WHERE l.id = ? AND l.deleted_at IS NULL'
        );
        $statement->execute([$lotId]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** Name, details, colour and logo for a printed document. */
    private function company(): array
    {
        $brand = Report::brand();
        $logo = null;
        if (!empty($brand['logo']) && is_file($brand['logo'])) {
            $mime = (string) (getimagesize($brand['logo'])['mime'] ?? 'image/png');
            $logo = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($brand['logo']));
        }

        return [
            'name' => Settings::get('company_name', 'LMS Logistics'),
            'details' => Report::companyDetails(),
            'color' => '#' . $brand['rgb'],
            'logo' => $logo,
        ];
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
