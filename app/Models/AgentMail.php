<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use PDO;
use Support\Mailer;

/**
 * The clearing instruction: the email the logistics manager sends the clearing
 * agent for one border clearance, from inside the system.
 *
 * Everything the agent needs to lodge the declaration is already on record:
 * the importer and its TIN, the goods, weight and value, the container, seal
 * and bill of lading, the truck, trailer and driver, the border and when the
 * truck is expected there. So the letter is written from those, and the papers
 * already scanned (the commercial invoice, packing list, bill of lading, the
 * driver's passport, the truck's papers) are offered as attachments to tick.
 * Replies go to the person who sent it, and every email is listed on the
 * clearance.
 */
final class AgentMail
{
    private const MAX_UPLOAD = 10 * 1024 * 1024;

    private static function db(): PDO
    {
        return Database::connection();
    }

    /** The clearance with everything the letter talks about. */
    public static function clearance(int $id): ?array
    {
        $statement = self::db()->prepare(
            'SELECT bc.*, bp.post_name, bp.country_a, bp.country_b,
                    ag.supplier_name AS agent_name, ag.contact_name AS agent_contact, ag.email AS agent_email, ag.phone AS agent_phone,
                    s.shipment_code, s.cargo_description, s.cargo_type, s.packages_count, s.weight_kg, s.volume_m3, s.declared_value, s.currency AS goods_currency,
                    s.container_no, s.container_size, s.seal_no AS shipment_seal, s.trailer_plate, s.bill_of_lading, s.customer_reference, s.consignee_name, s.consignee_phone,
                    s.origin, s.destination,
                    c.customer_name, c.tin_number AS customer_tin, c.address AS customer_address,
                    t.reference_code AS trip_code, t.id AS trip_ref_id, t.pickup_location, t.destination AS trip_destination, t.planned_arrival_at AS trip_arrival,
                    v.plate_number, d.full_name AS driver_name, d.phone AS driver_phone, d.license_number
               FROM border_crossings bc
               LEFT JOIN border_posts bp ON bp.id = bc.border_post_id
               LEFT JOIN suppliers ag ON ag.id = bc.clearing_agent_id
               LEFT JOIN shipments s ON s.id = bc.shipment_id
               LEFT JOIN customers c ON c.id = s.customer_id
               LEFT JOIN trips t ON t.id = COALESCE(bc.trip_id, s.trip_id)
               LEFT JOIN vehicles v ON v.id = COALESCE(bc.vehicle_id, t.vehicle_id)
               LEFT JOIN drivers d ON d.id = COALESCE(bc.driver_id, t.driver_id)
              WHERE bc.id = ? AND bc.deleted_at IS NULL'
        );
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** When the truck is expected at this border, and where it was last reported. */
    private static function whereIsTheTruck(array $c): array
    {
        $expected = null;
        $last = null;
        if (!empty($c['trip_ref_id'])) {
            $stops = self::db()->prepare('SELECT location_name, stop_type, planned_arrival_at, actual_arrival_at FROM trip_stops WHERE trip_id = ? ORDER BY stop_sequence, id');
            $stops->execute([(int) $c['trip_ref_id']]);
            $word = strtolower((string) strtok((string) ($c['post_name'] ?? ''), ' /'));
            foreach ($stops->fetchAll(PDO::FETCH_ASSOC) as $stop) {
                if ($word !== '' && str_contains(strtolower((string) $stop['location_name']), $word)) {
                    $expected = $stop['actual_arrival_at'] ? 'arrived ' . date('j M Y, H:i', (int) strtotime((string) $stop['actual_arrival_at'])) : ($stop['planned_arrival_at'] ? 'expected ' . date('j M Y, H:i', (int) strtotime((string) $stop['planned_arrival_at'])) : null);
                }
                if (!empty($stop['actual_arrival_at'])) {
                    $last = $stop['location_name'] . ', ' . date('j M H:i', (int) strtotime((string) $stop['actual_arrival_at']));
                }
            }
        }

        return [$expected, $last];
    }

    /** The letter, ready to edit: subject, body and who it goes to. */
    public static function letter(int $id): ?array
    {
        $c = self::clearance($id);
        if ($c === null) {
            return null;
        }
        [$expected, $last] = self::whereIsTheTruck($c);
        $company = \company_name();
        $me = self::db()->prepare('SELECT full_name, email, phone, job_title FROM users WHERE id = ?');
        $me->execute([(int) \current_user_id()]);
        $sender = $me->fetch(PDO::FETCH_ASSOC) ?: [];

        $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $p = static fn (string $html): string => '<p style="margin:0 0 12px;font-size:14px;line-height:1.6;color:#33404c">' . $html . '</p>';
        $table = static function (string $title, array $rows) use ($e): string {
            $html = '<p style="margin:16px 0 6px;font-size:12px;letter-spacing:.6px;text-transform:uppercase;color:#77828d;font-weight:700">' . $e($title) . '</p>'
                . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:0 0 6px">';
            foreach ($rows as $label => $value) {
                if (trim((string) $value) === '') {
                    continue;
                }
                $html .= '<tr><td style="padding:6px 10px 6px 0;border-bottom:1px solid #e9edf1;font-size:13px;color:#77828d;width:38%">' . $e($label) . '</td>'
                    . '<td style="padding:6px 0;border-bottom:1px solid #e9edf1;font-size:14px;color:#22303c;font-weight:600">' . $e($value) . '</td></tr>';
            }

            return $html . '</table>';
        };
        $num = static fn ($v): string => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
        $direction = Schema::label((string) $c['direction']);

        $body = $p($e(sprintf('Dear %s,', trim((string) ($c['agent_contact'] ?: $c['agent_name'])) ?: 'Sir or Madam')))
            . $p($e(sprintf('Please find below our clearing instruction for the load below at %s (%s, %s). The documents we hold are attached.',
                $c['post_name'] ?? 'the border', strtolower($direction), $c['customs_country'] ?: ($c['country_b'] ?? ''))))
            . $table('The goods', [
                'Our reference' => $c['shipment_code'] . ' / ' . $c['reference'],
                'Importer' => trim($c['customer_name'] . ($c['customer_tin'] ? ', TIN ' . $c['customer_tin'] : '')),
                'Importer address' => (string) ($c['customer_address'] ?? ''),
                'Receiver' => trim(($c['consignee_name'] ?? '') . ' ' . ($c['consignee_phone'] ?? '')),
                'Goods' => (string) $c['cargo_description'],
                'Packages / weight / volume' => trim(($c['packages_count'] ? number_format((float) $c['packages_count']) . ' packages · ' : '') . ($c['weight_kg'] ? number_format((float) $c['weight_kg']) . ' kg' : '') . ((float) $c['volume_m3'] > 0 ? ' · ' . $num($c['volume_m3']) . ' m³' : '')),
                'Declared value' => (float) $c['declared_value'] > 0 ? Currency::format((float) $c['declared_value'], (string) ($c['goods_currency'] ?: Currency::base())) : '',
                'Customer order (LPO)' => (string) ($c['customer_reference'] ?? ''),
                'Bill of lading' => (string) ($c['bill_of_lading'] ?? ''),
                'Container / seal' => trim(($c['container_no'] ?? '') . ($c['container_size'] ? ' (' . Schema::label((string) $c['container_size']) . ')' : '') . (($c['seal_no'] ?: $c['shipment_seal']) ? ' · seal ' . ($c['seal_no'] ?: $c['shipment_seal']) : '')),
            ])
            . $table('Truck and driver', [
                'Truck / trailer' => trim(($c['plate_number'] ?? '') . ($c['trailer_plate'] ? ' / trailer ' . $c['trailer_plate'] : '')),
                'Driver' => trim(($c['driver_name'] ?? '') . ($c['driver_phone'] ? ', ' . $c['driver_phone'] : '')),
                'Trip' => trim(($c['trip_code'] ?? '') . ($c['pickup_location'] ? ': ' . $c['pickup_location'] . ' to ' . $c['trip_destination'] : '')),
                'Last reported at' => (string) ($last ?? ''),
            ])
            . $table('The clearance', [
                'Border post' => (string) ($c['post_name'] ?? ''),
                'Customs country' => (string) ($c['customs_country'] ?? ''),
                'Direction' => $direction,
                'Truck at the border' => (string) ($expected ?? ''),
                'Declaration number' => (string) ($c['declaration_no'] ?? ''),
                'T1 / bond number' => (string) ($c['transit_bond_no'] ?? ''),
            ])
            . $p('<strong>Please:</strong>')
            . '<ol style="margin:0 0 14px;padding-left:20px;font-size:14px;line-height:1.6;color:#33404c">'
            . '<li>' . $e(trim((string) $c['declaration_no']) === '' ? 'Lodge the declaration and arrange the bond before the truck reaches the border.' : 'Confirm the declaration is in order and the bond is in place.') . '</li>'
            . '<li>Reply to this email with the declaration number and any amount to be paid, with its assessment.</li>'
            . '<li>Send us the receipts and the release as soon as customs releases the truck.</li>'
            . '</ol>'
            . $p($e(sprintf('Our driver%s will contact your person at the border%s.', $c['driver_name'] ? ', ' . $c['driver_name'] : '', $c['driver_phone'] ? ' (' . $c['driver_phone'] . ')' : '')))
            . $p($e('Kind regards,') . '<br>' . $e(trim(implode(', ', array_filter([(string) ($sender['full_name'] ?? ''), (string) ($sender['job_title'] ?? ''), $company])))) . '<br>'
                . $e(implode('  ·  ', array_filter([(string) ($sender['phone'] ?? '') ?: Settings::get('company_phone', ''), (string) ($sender['email'] ?? '')]))));

        return [
            'to' => (string) ($c['agent_email'] ?? ''),
            'to_name' => (string) ($c['agent_contact'] ?: $c['agent_name']),
            'subject' => sprintf('Clearing instruction — %s · %s · %s — %s', $c['shipment_code'], $c['container_no'] ?: $c['cargo_description'], $c['plate_number'] ?: 'truck', $c['post_name'] ?? 'border'),
            'heading' => 'Clearing instruction',
            'body_html' => $body,
            'signature' => trim($company . '  ·  ' . \Support\Report::companyDetails(), ' ·'),
            'reply_to' => (string) ($sender['email'] ?? ''),
            'clearance' => $c,
        ];
    }

    /**
     * The papers on record that can go with the letter: the shipment's, this
     * clearance's, the driver's and the truck's, only those with a scan.
     *
     * @return list<array{key: string, group: string, label: string, path: string, filename: string, ticked: bool}>
     */
    public static function documents(int $id): array
    {
        $c = self::clearance($id);
        if ($c === null) {
            return [];
        }
        $db = self::db();
        $out = [];
        $add = static function (string $key, string $group, string $label, ?string $file, bool $ticked) use (&$out): void {
            $file = trim((string) $file);
            if ($file === '' || !is_file(Mailer::uploadsRoot() . '/' . ltrim($file, '/'))) {
                return;
            }
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $out[] = ['key' => $key, 'group' => $group, 'label' => $label, 'path' => $file,
                      'filename' => preg_replace('/[^A-Za-z0-9._-]+/', '-', $label) . '.' . $extension, 'ticked' => $ticked];
        };

        $shipment = $db->prepare('SELECT id, document_type, document_number, document_file FROM shipment_documents WHERE shipment_id = ? ORDER BY id');
        $shipment->execute([(int) $c['shipment_id']]);
        foreach ($shipment->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $add('sd:' . $d['id'], 'Shipment papers', trim($d['document_type'] . ' ' . $d['document_number']), $d['document_file'], true);
        }
        $clearance = $db->prepare('SELECT id, document_type, document_no, document_file FROM border_documents WHERE crossing_id = ? ORDER BY id');
        $clearance->execute([$id]);
        foreach ($clearance->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $add('bd:' . $d['id'], 'This clearance', trim($d['document_type'] . ' ' . $d['document_no']), $d['document_file'], true);
        }
        $driverId = $c['driver_id'] ?? null;
        if ($driverId) {
            $papers = $db->prepare('SELECT id, document_type, document_number, document_file FROM driver_documents WHERE driver_id = ? AND status <> "rejected" ORDER BY id');
            $papers->execute([(int) $driverId]);
            foreach ($papers->fetchAll(PDO::FETCH_ASSOC) as $d) {
                $add('dd:' . $d['id'], 'Driver', 'Driver ' . Schema::label((string) $d['document_type']) . ' ' . $d['document_number'], $d['document_file'], in_array($d['document_type'], ['passport', 'driving_licence'], true));
            }
        }
        $vehicleId = $c['vehicle_id'] ?? null;
        if ($vehicleId) {
            $papers = $db->prepare('SELECT id, document_type, document_number, document_file FROM vehicle_documents WHERE vehicle_id = ? AND deleted_at IS NULL AND status <> "cancelled" ORDER BY id');
            $papers->execute([(int) $vehicleId]);
            foreach ($papers->fetchAll(PDO::FETCH_ASSOC) as $d) {
                $add('vd:' . $d['id'], 'Truck', 'Truck ' . Schema::label((string) $d['document_type']) . ' ' . $d['document_number'], $d['document_file'], in_array($d['document_type'], ['registration', 'insurance', 'permit'], true));
            }
        }

        return $out;
    }

    /**
     * Sends the letter as edited, with the papers ticked and any file added.
     *
     * @param list<string> $cc
     * @param list<string> $ticked document keys
     * @return array{ok: bool, message: string}
     */
    public static function send(int $id, string $to, array $cc, string $subject, string $bodyHtml, array $ticked, ?array $upload): array
    {
        $letter = self::letter($id);
        if ($letter === null) {
            return ['ok' => false, 'message' => 'That clearance no longer exists.'];
        }
        $attachments = [];
        foreach (self::documents($id) as $document) {
            if (in_array($document['key'], $ticked, true)) {
                $attachments[] = ['filename' => $document['filename'], 'path' => $document['path']];
            }
        }

        // A file from the computer: kept with the clearance so a retry sends it again.
        if ($upload !== null && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $extension = strtolower(pathinfo((string) ($upload['name'] ?? ''), PATHINFO_EXTENSION));
            if ((int) $upload['error'] !== UPLOAD_ERR_OK || (int) $upload['size'] > self::MAX_UPLOAD) {
                return ['ok' => false, 'message' => 'The extra file could not be uploaded. Use a file under 10 MB.'];
            }
            if (!in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'], true)) {
                return ['ok' => false, 'message' => 'The extra file must be a PDF, a photo, or a Word or Excel file.'];
            }
            $directory = Mailer::uploadsRoot() . '/crossings/outgoing';
            if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
                return ['ok' => false, 'message' => 'The uploads folder cannot be written to.'];
            }
            $name = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
            $moved = is_uploaded_file((string) $upload['tmp_name']) ? @move_uploaded_file((string) $upload['tmp_name'], $directory . '/' . $name) : @copy((string) $upload['tmp_name'], $directory . '/' . $name);
            if (!$moved) {
                return ['ok' => false, 'message' => 'The extra file could not be saved.'];
            }
            $original = preg_replace('/[^A-Za-z0-9._-]+/', '-', pathinfo((string) $upload['name'], PATHINFO_FILENAME)) ?: 'document';
            $attachments[] = ['filename' => $original . '.' . $extension, 'path' => 'crossings/outgoing/' . $name];
        }

        $c = $letter['clearance'];
        $result = Mailer::send([
            'key' => 'agent-instruction-' . $id . '-' . date('YmdHis'),
            'category' => 'notification',
            'to' => $to,
            'to_name' => $letter['to_name'],
            'cc' => $cc,
            'reply_to' => $letter['reply_to'],
            'user_id' => null,
            'subject' => $subject,
            'heading' => $letter['heading'],
            'body_html' => $bodyHtml,
            'signature' => $letter['signature'],
            'attachments' => $attachments,
            'entity_type' => 'border_crossings',
            'entity_id' => (string) $c['reference'],
        ]);
        if (!$result['queued']) {
            return ['ok' => false, 'message' => 'The email could not be queued: ' . $result['reason']];
        }
        AuditLog::record('clearing_agent.emailed', 'crossings', (string) $c['reference'], null, ['to' => $to, 'cc' => $cc, 'files' => count($attachments)]);

        return ['ok' => true, 'message' => sprintf('Clearing instruction sent to %s with %d document%s%s. Their reply comes to your inbox; add what they send under Clearance documents.',
            $to, count($attachments), count($attachments) === 1 ? '' : 's', $result['sent'] ? '' : ' (queued: see the email outbox)')];
    }
}
