<?php

declare(strict_types=1);

namespace Controllers;

use Core\Database;
use Models\AuditLog;
use Models\Notifier;
use Models\Reference;
use Models\Schema;
use Models\Settings;
use Models\Tracking;
use PDO;

/**
 * The customer's two pages, open without signing in: ask for transport, and
 * follow a shipment. Neither shows a price, a cost or another customer's
 * goods: a shipment is only shown to someone who knows both its code and the
 * email or phone it was booked with.
 */
final class PortalController
{
    /** What a customer can ask for: the company's services. */
    public const SERVICES = [
        'sea' => 'Sea freight', 'air' => 'Air freight', 'road' => 'Road transport', 'clearing' => 'Customs clearing and forwarding',
        'warehousing' => 'Warehousing', 'project' => 'Project cargo',
    ];

    /** Papers a customer may send in from the tracking page. */
    private const UPLOADABLE = ['Commercial invoice', 'Packing list', 'Bill of lading', 'Air waybill', 'Certificate of origin', 'Import Declaration Form (IDF)', 'KEBS certificate of conformity', 'Import/Export permit', 'Insurance', 'Other'];

    public function request(): void
    {
        if (Settings::int('public_requests', 1) !== 1) {
            \view('portal/closed', ['title' => 'Transport requests']);
            return;
        }

        $input = [];
        $errors = [];
        $sent = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $input = array_map(static fn ($v): string => trim((string) $v), array_intersect_key($_POST, array_flip([
                'company_name', 'contact_name', 'email', 'phone', 'pickup_location', 'destination', 'cargo_description', 'weight_kg', 'packages_count', 'required_date', 'notes', 'website',
                'service_type', 'load_type',
            ])));
            if (!array_key_exists($input['service_type'] ?? '', self::SERVICES)) {
                $input['service_type'] = '';
            }
            if (!array_key_exists($input['load_type'] ?? '', \Models\FreightJobs::LOAD_TYPES)) {
                $input['load_type'] = '';
            }

            // A field only a robot fills in.
            if (($input['website'] ?? '') !== '') {
                header('Location: ' . \url('request-transport', ['sent' => 'ok']));
                exit;
            }
            foreach (['contact_name' => 'Your name', 'email' => 'Email', 'phone' => 'Phone', 'pickup_location' => 'Collect from', 'destination' => 'Deliver to', 'cargo_description' => 'What the goods are', 'required_date' => 'Needed by'] as $field => $label) {
                if (($input[$field] ?? '') === '') {
                    $errors[] = $label . ' is needed.';
                }
            }
            if (($input['email'] ?? '') !== '' && filter_var($input['email'], FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'The email address does not look right.';
            }
            $date = ($input['required_date'] ?? '') !== '' ? strtotime($input['required_date']) : false;
            if (($input['required_date'] ?? '') !== '' && ($date === false || $date < strtotime(date('Y-m-d')))) {
                $errors[] = 'The date needed must be today or later.';
            }
            foreach (['weight_kg' => 'Weight', 'packages_count' => 'Packages'] as $field => $label) {
                if (($input[$field] ?? '') !== '' && (!is_numeric(str_replace(',', '', $input[$field])) || (float) str_replace(',', '', $input[$field]) < 0)) {
                    $errors[] = $label . ' must be a number.';
                }
            }

            // Three requests an hour from one address is a busy customer; more is not.
            $db = Database::connection();
            $recent = $db->prepare("SELECT COUNT(*) FROM transport_requests WHERE source = 'website' AND contact_email = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $recent->execute([$input['email'] ?? '']);
            $sessionCount = (int) ($_SESSION['portal_requests'] ?? 0);
            if ((int) $recent->fetchColumn() >= 3 || $sessionCount >= 5) {
                $errors[] = 'We have your requests already. Our team will call you shortly.';
            }

            if ($errors === []) {
                $customer = $db->prepare('SELECT id, currency FROM customers WHERE deleted_at IS NULL AND (LOWER(email) = LOWER(?) OR (phone <> \'\' AND REPLACE(phone, \' \', \'\') = REPLACE(?, \' \', \'\'))) ORDER BY id LIMIT 1');
                $customer->execute([$input['email'], $input['phone']]);
                $known = $customer->fetch(PDO::FETCH_ASSOC) ?: null;

                $code = Reference::next('REQ', 'transport_requests', 'reference_code');
                $db->prepare(
                    "INSERT INTO transport_requests (reference_code, source, service_type, load_type, customer_id, requested_by_contact, contact_email, contact_phone, company_name, pickup_location, destination,
                                                     cargo_description, weight_kg, packages_count, required_date, priority, status, currency, notes)
                     VALUES (?, 'website', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'normal', 'pending', ?, ?)"
                )->execute([
                    $code, $input['service_type'] ?: null, $input['load_type'] ?: null, $known['id'] ?? null, mb_substr($input['contact_name'], 0, 150), mb_substr($input['email'], 0, 190), mb_substr($input['phone'], 0, 40),
                    mb_substr($input['company_name'] ?? '', 0, 180) ?: null, mb_substr($input['pickup_location'], 0, 150), mb_substr($input['destination'], 0, 150),
                    mb_substr($input['cargo_description'], 0, 255), ($input['weight_kg'] ?? '') !== '' ? (float) str_replace(',', '', $input['weight_kg']) : null,
                    ($input['packages_count'] ?? '') !== '' ? (int) $input['packages_count'] : null, date('Y-m-d', (int) $date),
                    $known['currency'] ?? \Models\Currency::base(), mb_substr($input['notes'] ?? '', 0, 2000) ?: null,
                ]);
                $id = (int) $db->lastInsertId();
                $_SESSION['portal_requests'] = $sessionCount + 1;
                AuditLog::record('request.from_website', 'requests', $code, null, ['email' => $input['email'], 'known_customer' => $known !== null]);
                \Models\Approvals::open('requests', $id);

                Notifier::toRole('logistics_manager', 'New request from the website: ' . $code, sprintf(
                    '%s%s asks for %s: %s from %s to %s by %s. Call %s.',
                    $input['contact_name'], ($input['company_name'] ?? '') !== '' ? ' (' . $input['company_name'] . ')' : '',
                    mb_strtolower(self::SERVICES[$input['service_type']] ?? 'transport') . (($input['load_type'] ?? '') !== '' ? ', ' . \Models\FreightJobs::LOAD_TYPES[$input['load_type']] : ''),
                    $input['cargo_description'],
                    $input['pickup_location'], $input['destination'], \human_date(date('Y-m-d', (int) $date)), $input['phone']
                ), 'requests', 'info', 'requests', (string) $id);

                header('Location: ' . \url('request-transport', ['sent' => $code]));
                exit;
            }
        }

        $sentCode = (string) ($_GET['sent'] ?? '');
        if ($sentCode !== '' && preg_match('/^[A-Z0-9-]{3,32}$/i', $sentCode) === 1) {
            $sent = $sentCode;
        }

        \view('portal/request', ['title' => 'Request transport', 'input' => $input, 'errors' => $errors, 'sent' => $sent]);
    }

    public function track(): void
    {
        $code = strtoupper(trim((string) ($_POST['code'] ?? $_GET['code'] ?? '')));
        $contact = trim((string) ($_POST['contact'] ?? ''));
        $result = null;
        $error = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $attempts = (int) ($_SESSION['portal_track_attempts'] ?? 0);
            if ($attempts >= 20) {
                $error = 'Too many searches. Please try again later or call us.';
            } elseif ($code === '' || $contact === '') {
                $error = 'Give the shipment code and the email or phone it was booked with.';
            } else {
                $_SESSION['portal_track_attempts'] = $attempts + 1;
                $result = self::find($code, $contact);
                if ($result === null) {
                    $error = 'No shipment matches that code and contact. Check both, or call us.';
                } else {
                    // Proven once, the customer may send papers for this shipment for an hour.
                    $_SESSION['portal_verified'][(int) $result['shipment_id']] = time();
                }
            }
        }

        // Back from sending a paper: show the same shipment again.
        $flashId = (int) ($_SESSION['portal_last_shipment'] ?? 0);
        if ($result === null && $error === null && $flashId > 0 && self::verified($flashId)) {
            $result = self::find('', '', $flashId);
        }
        $notice = (string) ($_SESSION['portal_notice'] ?? '');
        unset($_SESSION['portal_notice']);

        \view('portal/track', ['title' => 'Track a shipment', 'code' => $code, 'result' => $result, 'error' => $error, 'notice' => $notice, 'uploadable' => self::UPLOADABLE]);
    }

    /**
     * A paper sent in by the customer: kept on the shipment as pending, and
     * checked by operations like any other before it counts.
     */
    public function upload(): void
    {
        $shipmentId = (int) ($_POST['shipment'] ?? 0);
        $type = (string) ($_POST['document_type'] ?? '');
        $file = $_FILES['document'] ?? null;
        $back = static function (string $message) use ($shipmentId): never {
            $_SESSION['portal_notice'] = $message;
            $_SESSION['portal_last_shipment'] = $shipmentId;
            header('Location: ' . \url('track'));
            exit;
        };

        if (!self::verified($shipmentId)) {
            $_SESSION['portal_notice'] = 'Track the shipment again with its code and your email or phone, then send the paper.';
            header('Location: ' . \url('track'));
            exit;
        }
        if (!in_array($type, self::UPLOADABLE, true)) {
            $back('Choose which paper it is.');
        }
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $back('Choose the file to send.');
        }
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true) || (int) $file['size'] > (int) \config('uploads.max_bytes', 5242880)) {
            $back('Send a PDF or a picture, no bigger than ' . round((int) \config('uploads.max_bytes', 5242880) / 1048576) . ' MB.');
        }
        $uploads = (int) ($_SESSION['portal_uploads'] ?? 0);
        if ($uploads >= 20) {
            $back('That is a lot of papers: please email the rest to us.');
        }

        $folder = rtrim((string) \config('uploads.path', dirname(__DIR__, 2) . '/storage/uploads'), "/\\") . '/shipment_documents';
        if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
            $back('The paper could not be saved. Please email it to us.');
        }
        $name = date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
        if (!move_uploaded_file((string) $file['tmp_name'], $folder . '/' . $name)) {
            $back('The paper could not be saved. Please email it to us.');
        }

        $db = Database::connection();
        $db->prepare("INSERT INTO shipment_documents (shipment_id, document_type, document_number, status, document_file, notes) VALUES (?, ?, ?, 'pending', ?, 'Sent by the customer from the tracking page.')")
           ->execute([$shipmentId, $type, mb_substr(trim((string) ($_POST['document_number'] ?? '')), 0, 60) ?: null, 'shipment_documents/' . $name]);
        $paperId = (int) $db->lastInsertId();
        $_SESSION['portal_uploads'] = $uploads + 1;
        $code = (string) $db->query('SELECT shipment_code FROM shipments WHERE id = ' . $shipmentId)->fetchColumn();
        AuditLog::record('document.from_customer', 'shipment_documents', $code, null, ['type' => $type]);
        \Models\Approvals::open('shipment_documents', $paperId);
        Notifier::toRole('logistics_manager', 'Customer sent a paper: ' . $code, sprintf('%s for %s arrived from the tracking page. Check it and press Verify.', $type, $code), 'shipment_documents', 'info', 'shipment_documents', (string) $paperId);

        $back(sprintf('Thank you. Your %s was received and will be checked by our team.', mb_strtolower($type)));
    }

    private static function verified(int $shipmentId): bool
    {
        $at = (int) ($_SESSION['portal_verified'][$shipmentId] ?? 0);

        return $shipmentId > 0 && $at > 0 && time() - $at < 3600;
    }

    /** The shipment, when the contact given is the one on its booking. */
    private static function find(string $code, string $contact, ?int $verifiedShipment = null): ?array
    {
        $db = Database::connection();
        $statement = $db->prepare(
            "SELECT s.id, s.shipment_code, s.status, s.origin, s.destination, s.cargo_description, s.packages_count, s.weight_kg, s.trip_id, s.consignee_phone,
                    c.email AS customer_email, c.phone AS customer_phone, r.contact_email, r.contact_phone,
                    t.reference_code AS trip, t.status AS trip_status, t.departure_at, t.planned_arrival_at, t.arrival_at, fj.id AS job_id
               FROM shipments s
               LEFT JOIN freight_jobs fj ON fj.id = s.freight_job_id AND fj.deleted_at IS NULL
               LEFT JOIN customers c ON c.id = s.customer_id
               LEFT JOIN transport_requests r ON r.id = s.request_id
               LEFT JOIN trips t ON t.id = s.trip_id
              WHERE s.deleted_at IS NULL AND (s.id = ? OR UPPER(s.shipment_code) = ? OR UPPER(fj.job_code) = ?)
              LIMIT 1"
        );
        $statement->execute([(int) $verifiedShipment, $code === '' ? '-' : $code, $code === '' ? '-' : $code]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $digits = static fn (string $v): string => substr(preg_replace('/\D+/', '', $v) ?? '', -9);
        $given = strtolower($contact);
        $matches = false;
        foreach ([$row['customer_email'], $row['contact_email']] as $email) {
            if ($email !== null && $email !== '' && strtolower((string) $email) === $given) {
                $matches = true;
            }
        }
        foreach ([$row['customer_phone'], $row['contact_phone'], $row['consignee_phone']] as $phone) {
            if ($phone !== null && strlen($digits((string) $phone)) >= 7 && $digits((string) $phone) === $digits($contact)) {
                $matches = true;
            }
        }
        if (!$matches && $verifiedShipment === null) {
            return null;
        }

        // Before the truck: the sea or air leg of a forwarded consignment.
        $job = $row['job_id'] !== null ? \Models\FreightJobs::load((int) $row['job_id']) : null;
        $timeline = $job !== null ? \Models\FreightJobs::timeline($job) : [];
        if ($row['trip_id'] !== null) {
            $stops = $db->prepare('SELECT location_name, stop_type, actual_arrival_at, departed_at FROM trip_stops WHERE trip_id = ? ORDER BY stop_sequence, id');
            $stops->execute([(int) $row['trip_id']]);
            if ($row['departure_at']) {
                $timeline[] = ['what' => 'Left ' . $row['origin'], 'when' => $row['departure_at'], 'done' => true];
            }
            foreach ($stops->fetchAll(PDO::FETCH_ASSOC) as $stop) {
                $timeline[] = [
                    'what' => ($stop['stop_type'] === 'checkpoint' ? 'Border: ' : '') . $stop['location_name'],
                    'when' => $stop['departed_at'] ?: $stop['actual_arrival_at'],
                    'done' => $stop['actual_arrival_at'] !== null,
                ];
            }
        }
        $clearances = $db->prepare('SELECT bp.post_name, bc.status FROM border_crossings bc INNER JOIN border_posts bp ON bp.id = bc.border_post_id WHERE bc.shipment_id = ? AND bc.deleted_at IS NULL ORDER BY bc.id');
        $clearances->execute([(int) $row['id']]);
        $last = $row['trip_id'] !== null && in_array((string) $row['trip_status'], ['in_transit'], true) ? Tracking::lastForTrip((int) $row['trip_id']) : null;

        $papers = $db->prepare('SELECT document_type, status FROM shipment_documents WHERE shipment_id = ? ORDER BY id');
        $papers->execute([(int) $row['id']]);

        return [
            'shipment_id' => (int) $row['id'],
            'job' => $job === null ? null : ['code' => $job['job_code'], 'mode' => \Models\FreightJobs::MODES[$job['mode']] ?? '', 'bl' => (string) ($job['house_bl'] ?: $job['master_bl'] ?: ''), 'containers' => (string) $job['container_numbers'], 'vessel' => trim($job['vessel_flight'] . ' ' . $job['voyage_no']), 'eta' => $job['eta'], 'status' => Schema::label((string) $job['status'])],
            'papers' => array_map(static fn (array $p): array => ['type' => $p['document_type'], 'status' => Schema::label((string) $p['status'])], $papers->fetchAll(PDO::FETCH_ASSOC)),
            'code' => $row['shipment_code'],
            'status' => Schema::label((string) $row['status']),
            'status_key' => (string) $row['status'],
            'from' => $row['origin'],
            'to' => $row['destination'],
            'goods' => $row['cargo_description'],
            'packages' => $row['packages_count'],
            'expected' => $row['planned_arrival_at'],
            'arrived' => $row['arrival_at'],
            'timeline' => $timeline,
            'clearances' => array_map(static fn (array $c): array => ['post' => $c['post_name'], 'status' => Schema::label((string) $c['status'])], $clearances->fetchAll(PDO::FETCH_ASSOC)),
            'last_seen' => $last === null ? null : ['at' => $last['recorded_at'], 'lat' => round((float) $last['latitude'], 2), 'lng' => round((float) $last['longitude'], 2)],
        ];
    }
}
