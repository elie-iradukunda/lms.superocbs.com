<?php

declare(strict_types=1);

namespace Controllers;

use Models\AuditLog;
use Models\Schema;

/**
 * Serves proof-of-delivery files, signatures, receipts and scanned documents.
 *
 * Uploads live outside the web root, so before this route existed a file could
 * be uploaded but never opened again. Access is checked against the permission
 * of the module the file belongs to.
 */
final class FileController
{
    private const MIME = [
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function show(array $params): void
    {
        $module = (string) ($params['module'] ?? '');
        $name = (string) ($params['name'] ?? '');

        // The module segment must be a real module and the file name must be a
        // plain name, so no request can walk out of the uploads folder.
        $permission = $module === 'driver_documents' ? 'drivers' : (\Models\Uploads::FOLDERS[$module] ?? $module);
        if ((!Schema::has($module) && $module !== 'driver_documents' && !isset(\Models\Uploads::FOLDERS[$module])) || preg_match('/^[A-Za-z0-9._-]+$/', $name) !== 1 || str_contains($name, '..')) {
            $this->fail(404, 'File not found.');
        }

        if (!\role_can($permission, 'view') && !$this->driverOwns($module, $name)) {
            $this->fail(403, 'This file is not available for your role.');
        }

        $root = realpath((string) \config('uploads.path', dirname(__DIR__, 2) . '/storage/uploads'));
        $path = realpath($root . '/' . $module . '/' . $name);

        if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
            $this->fail(404, 'File not found.');
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $type = self::MIME[$extension] ?? 'application/octet-stream';
        $inline = in_array($extension, ['pdf', 'png', 'jpg', 'jpeg', 'webp'], true);

        AuditLog::record('file.opened', $module, $name);

        header('Content-Type: ' . $type);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . basename($path) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=600');
        readfile($path);
        exit;
    }

    /**
     * Serves a profile photograph.
     *
     * It goes through this route rather than sitting under public/ because a
     * staff photograph is not something to leave on a guessable public address:
     * anyone signed in may see a colleague's face, and nobody else may.
     */
    public function avatar(array $params): void
    {
        $name = (string) ($params['name'] ?? '');
        $path = \Models\Avatar::resolve($name);

        if ($path === null || !is_file($path)) {
            $this->fail(404, 'No photo there.');
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!isset(self::MIME[$extension])) {
            $this->fail(404, 'No photo there.');
        }

        header('Content-Type: ' . self::MIME[$extension]);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        header('X-Content-Type-Options: nosniff');
        // A face does not change often, and it is on every page.
        header('Cache-Control: private, max-age=86400');
        readfile($path);
        exit;
    }

    /**
     * A driver carries the papers for his own load: the shipment documents, the
     * customs documents of the crossings on his trip, his truck's insurance and
     * inspection, and his own licence. He may open those, and only those, even
     * though his role cannot browse the document lists of the whole company.
     */
    private function driverOwns(string $module, string $name): bool
    {
        $driverId = \current_role() === 'driver' ? \current_driver_id() : null;
        if ($driverId === null) {
            return false;
        }

        $sql = match ($module) {
            'shipment_documents' => 'SELECT 1 FROM shipment_documents d
                                       INNER JOIN shipments s ON s.id = d.shipment_id
                                       INNER JOIN trips t ON t.id = s.trip_id
                                      WHERE d.document_file LIKE ? AND t.driver_id = ?',
            'clearance_documents' => 'SELECT 1 FROM border_documents d
                                        INNER JOIN border_crossings c ON c.id = d.crossing_id
                                        LEFT JOIN shipments s ON s.id = c.shipment_id
                                        INNER JOIN trips t ON t.id = COALESCE(c.trip_id, s.trip_id)
                                       WHERE d.document_file LIKE ? AND t.driver_id = ?',
            'vehicle_documents' => 'SELECT 1 FROM vehicle_documents d
                                      WHERE d.document_file LIKE ? AND d.deleted_at IS NULL
                                        AND d.vehicle_id IN (SELECT vehicle_id FROM trips WHERE driver_id = ? AND vehicle_id IS NOT NULL)',
            'driver_documents' => 'SELECT 1 FROM driver_documents d WHERE d.document_file LIKE ? AND d.driver_id = ?',
            default => null,
        };

        if ($sql === null) {
            return false;
        }

        $statement = \Core\Database::connection()->prepare($sql . ' LIMIT 1');
        $statement->execute(['%/' . $name, $driverId]);

        return $statement->fetchColumn() !== false;
    }

    private function fail(int $status, string $message): never
    {
        http_response_code($status);
        \view('layouts/error', ['title' => $status === 403 ? 'Not allowed' : 'File not found', 'message' => $message]);
        exit;
    }
}
