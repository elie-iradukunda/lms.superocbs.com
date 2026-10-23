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
use Models\PriceOffer;
use Models\ReportSchedule;
use Models\Settings;
use Models\Workflow;
use Support\Mailer;
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
                    t.reference_code AS trip_code, t.pickup_location, t.departure_at, t.dispatched_at, t.dispatched_by AS trip_dispatched_by, d.user_id AS driver_user_id,
                    v.plate_number, v.make, v.model,
                    d.full_name AS driver_name, d.phone AS driver_phone, d.license_number,
                    c.customer_name, c.phone AS customer_phone, c.address AS customer_address, c.tin_number AS customer_tin,
                    r.lpo_number AS request_lpo, r.reference_code AS request_code,
                    u.full_name AS delivered_by_name, ud.full_name AS dispatched_by_name
               FROM deliveries dl
               LEFT JOIN shipments s ON s.id = dl.shipment_id
               LEFT JOIN trips t ON t.id = dl.trip_id
               LEFT JOIN vehicles v ON v.id = t.vehicle_id
               LEFT JOIN drivers d ON d.id = t.driver_id
               LEFT JOIN customers c ON c.id = COALESCE(s.customer_id, t.customer_id)
               LEFT JOIN transport_requests r ON r.id = COALESCE(s.request_id, t.request_id)
               LEFT JOIN users u ON u.id = dl.delivered_by
               LEFT JOIN users ud ON ud.id = t.dispatched_by
              WHERE dl.id = ?"
        );
        $statement->execute([$id]);
        $note = $statement->fetch();

        AuditLog::record('delivery.note_printed', 'deliveries', (string) $note['delivery_code']);

        // Signatures where each person has done their part: the dispatcher once
        // the truck left, the driver once the goods are at the door, the
        // receiver from the signature taken at delivery.
        $handedOver = in_array((string) $note['status'], ['at_destination', 'delivered'], true);
        $signatures = [
            'dispatched' => !empty($note['dispatched_at']) ? \Models\Signature::forUser($note['trip_dispatched_by'] !== null ? (int) $note['trip_dispatched_by'] : null) : null,
            'driver' => $handedOver ? \Models\Signature::forUser($note['driver_user_id'] !== null ? (int) $note['driver_user_id'] : null) : null,
            'received' => \Models\Signature::dataUri(\Models\Signature::uploadedImage($note['recipient_signature'] ?? null)),
        ];

        \view('deliveries/note', [
            'note' => $note,
            'company' => $this->company(),
            'signatures' => $signatures,
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
            'seal' => \Models\CompanyImage::dataUri(\Models\CompanyImage::SEAL),
        ];
    }

    /** The invoice as a document to print, save as PDF or hand over, with the seal once it is issued. */
    public function invoicePrint(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        if (LogisticsData::find('invoices', $id, \current_context()) === null) {
            Flash::error('That invoice does not exist, or it is not visible to your role.');
            $this->redirect(\url('invoices'));
        }

        $db = Database::connection();
        $statement = $db->prepare(
            'SELECT i.*, c.customer_name, c.contact_name, c.address AS customer_address, c.tin_number AS customer_tin,
                    c.phone AS customer_phone, c.email AS customer_email,
                    t.reference_code AS trip_code, j.journey_code, u.full_name AS issued_by_name
               FROM invoices i
               LEFT JOIN customers c ON c.id = i.customer_id
               LEFT JOIN trips t ON t.id = i.trip_id
               LEFT JOIN journeys j ON j.id = i.journey_id
               LEFT JOIN users u ON u.id = i.issued_by
              WHERE i.id = ? AND i.deleted_at IS NULL'
        );
        $statement->execute([$id]);
        $invoice = $statement->fetch(\PDO::FETCH_ASSOC);

        $lines = $db->prepare('SELECT description, quantity, unit_price, line_total, line_kind FROM invoice_lines WHERE invoice_id = ? ORDER BY line_kind, id');
        $lines->execute([$id]);

        // Where to pay: the accounts shown on invoices that take this currency.
        $currency = (string) ($invoice['currency'] ?: \Models\Currency::base());
        $payTo = [];
        foreach (\Models\PaymentMethod::all() as $method) {
            if ((int) $method['is_active'] !== 1 || (int) $method['show_on_invoice'] !== 1 || !in_array((string) $method['direction'], ['in', 'both'], true)) {
                continue;
            }
            if (trim((string) ($method['currency'] ?? '')) !== '' && strtoupper((string) $method['currency']) !== strtoupper($currency)) {
                continue;
            }
            $payTo[] = ['name' => (string) $method['method_name'], 'details' => \Models\PaymentMethod::describe($method)];
        }

        AuditLog::record('invoice.printed', 'invoices', (string) $invoice['invoice_number']);
        \view('invoices/print', [
            'invoice' => $invoice,
            'lines' => $lines->fetchAll(\PDO::FETCH_ASSOC),
            'payTo' => $payTo,
            'currency' => $currency,
            'company' => $this->company(),
            // Whoever issued it signs it, once it is issued; a draft is never signed.
            'issuerSignature' => !in_array((string) $invoice['status'], ['draft', 'cancelled'], true) && $invoice['issued_by'] !== null ? \Models\Signature::forUser((int) $invoice['issued_by']) : null,
        ]);
    }

    /** The readiness analysis for one shipment, for the dispatch check form as soon as a shipment is chosen. */
    public function readiness(): void
    {
        $id = (int) ($_GET['shipment_id'] ?? 0);
        $analysis = $id > 0 && LogisticsData::find('shipments', $id, \current_context()) !== null ? \Models\DispatchReadiness::forShipment($id) : null;

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        if ($analysis === null) {
            echo json_encode(['html' => '', 'ticks' => []]);
            exit;
        }
        ob_start();
        \view('partials/readiness', ['analysis' => $analysis]);
        echo json_encode(['html' => (string) ob_get_clean(), 'ticks' => \Models\DispatchReadiness::ticks($analysis), 'ready' => $analysis['ready']]);
        exit;
    }

    /**
     * The trip report: as a page to read or print, a PDF or an Excel workbook.
     * A driver sees only his own trips, and never the prices.
     */
    public function tripReport(array $params): void
    {
        $data = $this->tripReportData((int) ($params['id'] ?? 0));
        $format = (string) ($params['format'] ?? '');

        if ($format === 'pdf' || $format === 'xlsx') {
            $bytes = $format === 'pdf' ? \Models\TripReportPdf::build($data) : \Models\TripReportSheet::build($data);
            if ($bytes === null || $bytes === '') {
                Flash::error('The Excel file could not be made on this server.');
                $this->redirect(\url(['trips', $data['trip']['id'], 'report']));
            }
            AuditLog::record('trip.report_downloaded', 'trips', (string) $data['trip']['reference_code'], null, ['format' => $format]);
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            header('Content-Type: ' . ($format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
            header('Content-Disposition: ' . ($format === 'pdf' && !isset($_GET['download']) ? 'inline' : 'attachment') . '; filename="' . \Models\TripReport::filename($data, $format) . '"');
            header('Content-Length: ' . strlen($bytes));
            header('Cache-Control: private, max-age=0');
            echo $bytes;
            exit;
        }

        \view('trips/report', ['title' => 'Trip report ' . $data['trip']['reference_code'], 'report' => $data, 'brand' => $this->company()]);
    }

    private function tripReportData(int $id): array
    {
        if (LogisticsData::find('trips', $id, \current_context()) === null) {
            Flash::error('That trip does not exist, or it is not visible to your role.');
            $this->redirect(\url('trips'));
        }
        // Prices for whoever deals with them: those who see rate cards or invoices.
        $commercial = \current_role() !== 'driver' && (\role_can('rates', 'view') || \role_can('invoices', 'view'));
        $data = \Models\TripReport::data($id, $commercial);
        if ($data === null) {
            Flash::error('That trip does not exist.');
            $this->redirect(\url('trips'));
        }

        return $data;
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

    /**
     * "Send to customer" on a price offer: the letter as it will go, in an
     * editor, so the office can change any word before it is sent.
     */
    public function composeOffer(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $offer = LogisticsData::find('price_offers', $id, \current_context());
        if ($offer === null) {
            Flash::error('That price offer does not exist.');
            $this->redirect(\url('price_offers'));
        }
        if (!in_array((string) $offer['status'], ['draft', 'sent'], true)) {
            Flash::error('This offer has already been answered by the customer, so it cannot be sent again.');
            $this->redirect(\url(['price_offers', $id]));
        }
        $problem = PriceOffer::guard('send', $offer);
        if ($problem !== null) {
            Flash::error($problem);
            $this->redirect(\url(['price_offers', $id]));
        }

        $letter = PriceOffer::letter($id);
        $draft = $_SESSION['offer_email_draft'][$id] ?? null;
        unset($_SESSION['offer_email_draft'][$id]);

        \view('mail/compose', [
            'title' => 'Send ' . $offer['offer_code'],
            'kicker' => 'Price offer ' . $offer['offer_code'],
            'formAction' => \url(['price_offers', $id, 'email']),
            'backUrl' => \url(['price_offers', $id]),
            'footNote' => 'Sending marks the offer as sent.',
            'letter' => $letter,
            'original' => Mailer::content($letter),
            'to' => $draft['to'] ?? $letter['to'],
            'subject' => $draft['subject'] ?? $letter['subject'],
            'body' => $draft['body'] ?? Mailer::content($letter),
            'errors' => $draft['errors'] ?? [],
            'company' => \company_name(),
            'colour' => '#' . Report::brand()['rgb'],
        ]);
    }

    /** The LPO form for an accepted offer, to open, check or print. */
    public function lpoForm(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        if (LogisticsData::find('price_offers', $id, \current_context()) === null) {
            Flash::error('That price offer does not exist.');
            $this->redirect(\url('price_offers'));
        }
        $built = \Models\LpoForm::build($id);
        if ($built === null) {
            Flash::error('The LPO form could not be prepared.');
            $this->redirect(\url(['price_offers', $id]));
        }

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . (isset($_GET['download']) ? 'attachment' : 'inline') . '; filename="' . $built['filename'] . '"');
        header('Content-Length: ' . strlen($built['bytes']));
        header('Cache-Control: private, max-age=0');
        echo $built['bytes'];
        exit;
    }

    /** "Send LPO form": the covering email, editable, with the form attached. */
    public function composeLpo(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $offer = LogisticsData::find('price_offers', $id, \current_context());
        if ($offer === null) {
            Flash::error('That price offer does not exist.');
            $this->redirect(\url('price_offers'));
        }
        if ((string) $offer['status'] !== 'accepted') {
            Flash::error('The LPO form is sent once the customer has accepted the offer.');
            $this->redirect(\url(['price_offers', $id]));
        }

        $letter = PriceOffer::lpoLetter($id);
        $draft = $_SESSION['lpo_email_draft'][$id] ?? null;
        unset($_SESSION['lpo_email_draft'][$id]);

        \view('mail/compose', [
            'title' => 'LPO form ' . $offer['offer_code'],
            'kicker' => 'Price offer ' . $offer['offer_code'],
            'pageHeading' => 'Send the LPO form to the customer',
            'formAction' => \url(['price_offers', $id, 'lpo-email']),
            'backUrl' => \url(['price_offers', $id]),
            'footNote' => 'The LPO form goes as a PDF attachment the customer can fill in.',
            'attachments' => [['filename' => \Models\LpoForm::filename($offer), 'url' => \url(['price_offers', $id, 'lpo'])]],
            'letter' => $letter,
            'original' => $letter['body_html'],
            'to' => $draft['to'] ?? $letter['to'],
            'subject' => $draft['subject'] ?? $letter['subject'],
            'body' => $draft['body'] ?? $letter['body_html'],
            'errors' => $draft['errors'] ?? [],
            'company' => \company_name(),
            'colour' => '#' . Report::brand()['rgb'],
        ]);
    }

    public function sendLpo(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $offer = LogisticsData::find('price_offers', $id, \current_context());
        if ($offer === null || (string) $offer['status'] !== 'accepted') {
            Flash::error('The LPO form is sent once the customer has accepted the offer.');
            $this->redirect(\url($offer === null ? 'price_offers' : ['price_offers', $id]));
        }

        [$to, $subject, $body, $errors] = $this->composed();
        if ($errors !== []) {
            $_SESSION['lpo_email_draft'][$id] = ['to' => $to, 'subject' => $subject, 'body' => $body, 'errors' => $errors];
            $this->redirect(\url(['price_offers', $id, 'lpo-email']));
        }

        $result = PriceOffer::sendLpo($id, $to, mb_substr($subject, 0, 200), $body);
        $result['ok'] ? Flash::success($result['message']) : Flash::error($result['message']);
        $this->redirect(\url(['price_offers', $id]));
    }

    /** @return array{0: string, 1: string, 2: string, 3: list<string>} the address, subject and cleaned body posted, and what is wrong with them */
    private function composed(): array
    {
        $to = trim((string) ($_POST['to'] ?? ''));
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $body = Mailer::clean((string) ($_POST['body'] ?? ''));

        $errors = [];
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Enter a valid email address to send to.';
        }
        if ($subject === '') {
            $errors[] = 'The email needs a subject.';
        }
        if (trim(strip_tags($body)) === '') {
            $errors[] = 'The email is empty.';
        }

        return [$to, $subject, $body, $errors];
    }

    /** "Email the clearing agent" on a clearance: the instruction, the papers to tick, from inside the system. */
    public function composeAgent(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        if (LogisticsData::find('crossings', $id, \current_context()) === null) {
            Flash::error('That clearance does not exist.');
            $this->redirect(\url('crossings'));
        }
        $letter = \Models\AgentMail::letter($id);
        $draft = $_SESSION['agent_email_draft'][$id] ?? null;
        unset($_SESSION['agent_email_draft'][$id]);
        $c = $letter['clearance'];
        if (trim((string) $letter['to']) === '' && $draft === null) {
            Flash::info(empty($c['clearing_agent_id'])
                ? 'Choose the clearing agent on this clearance first, or type their email below.'
                : sprintf('%s has no email on record. Type it below, and add it to the supplier for next time.', $c['agent_name']));
        }

        \view('mail/compose', [
            'title' => 'Email the agent · ' . $c['reference'],
            'kicker' => 'Customs clearance ' . $c['reference'] . ' · ' . ($c['post_name'] ?? ''),
            'pageHeading' => 'Email the clearing agent',
            'formAction' => \url(['crossings', $id, 'agent-email']),
            'backUrl' => \url(['crossings', $id]),
            'footNote' => 'The agent\'s reply comes to your email. Add what they send back under Clearance documents.',
            'letter' => $letter,
            'original' => $letter['body_html'],
            'to' => $draft['to'] ?? $letter['to'],
            'cc' => $draft['cc'] ?? '',
            'showCc' => true,
            'subject' => $draft['subject'] ?? $letter['subject'],
            'body' => $draft['body'] ?? $letter['body_html'],
            'choices' => \Models\AgentMail::documents($id),
            'errors' => $draft['errors'] ?? [],
            'company' => \company_name(),
            'colour' => '#' . Report::brand()['rgb'],
        ]);
    }

    public function sendAgent(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        if (LogisticsData::find('crossings', $id, \current_context()) === null) {
            Flash::error('That clearance does not exist.');
            $this->redirect(\url('crossings'));
        }
        [$to, $subject, $body, $errors] = $this->composed();
        $ccTyped = trim((string) ($_POST['cc'] ?? ''));
        $cc = array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', $ccTyped) ?: [])));
        foreach ($cc as $address) {
            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = sprintf('"%s" in Cc is not an email address.', $address);
            }
        }
        if ($errors !== []) {
            $_SESSION['agent_email_draft'][$id] = ['to' => $to, 'cc' => $ccTyped, 'subject' => $subject, 'body' => $body, 'errors' => $errors];
            $this->redirect(\url(['crossings', $id, 'agent-email']));
        }

        $ticked = array_map('strval', (array) ($_POST['attach'] ?? []));
        $result = \Models\AgentMail::send($id, $to, $cc, mb_substr($subject, 0, 200), $body, $ticked, $_FILES['extra_file'] ?? null);
        if (!$result['ok']) {
            $_SESSION['agent_email_draft'][$id] = ['to' => $to, 'cc' => $ccTyped, 'subject' => $subject, 'body' => $body, 'errors' => [$result['message']]];
            $this->redirect(\url(['crossings', $id, 'agent-email']));
        }
        Flash::success($result['message']);
        $this->redirect(\url(['crossings', $id]));
    }

    /** Sends the letter as edited, then marks the offer sent. */
    public function sendOffer(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        [$to, $subject, $body, $errors] = $this->composed();
        if ($errors !== []) {
            $_SESSION['offer_email_draft'][$id] = ['to' => $to, 'subject' => $subject, 'body' => $body, 'errors' => $errors];
            $this->redirect(\url(['price_offers', $id, 'email']));
        }

        PriceOffer::useEdited($to, mb_substr($subject, 0, 200), $body);
        $result = Workflow::apply('price_offers', $id, 'send');
        $result['ok'] ? Flash::success($result['message']) : Flash::error($result['message']);

        $this->redirect(\url(['price_offers', $id]));
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }
}
