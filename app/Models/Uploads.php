<?php

declare(strict_types=1);

namespace Models;

/**
 * Files people send with a form: an MTN proof, a photo of the truck at
 * handover, the customer's signed delivery note, a supplier's invoice scan.
 * They are kept outside the web root and served by /files/{folder}/{name}
 * to whoever may open that folder.
 */
final class Uploads
{
    public const IMAGES = ['png', 'jpg', 'jpeg', 'webp'];
    public const DOCUMENTS = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv'];

    /** Folders that are not a generic module, and the permission that opens them. */
    public const FOLDERS = [
        'form_requests' => 'form_requests',
        'vehicle_handovers' => 'vehicle_handovers',
        'payment_batches' => 'payment_batches',
        'trip_events' => 'trips',
        'fuel_orders' => 'fuel_orders',
        'cash_receipts' => 'payment_batches',
        'delivery_notes' => 'deliveries',
    ];

    public static function root(): string
    {
        return rtrim((string) \config('uploads.path', dirname(__DIR__, 2) . '/storage/uploads'), "/\\");
    }

    /** What is wrong with an upload, or null when it is fine (or absent). */
    public static function problem(?array $file, string $label, array $allowed = self::DOCUMENTS): ?string
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return "{$label} could not be uploaded.";
        }
        $limit = (int) \config('uploads.max_bytes', 5242880);
        if ((int) ($file['size'] ?? 0) > $limit) {
            return sprintf('%s must be %s MB or smaller.', $label, rtrim(rtrim(number_format($limit / 1048576, 1, '.', ''), '0'), '.'));
        }
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed, true)) {
            return sprintf('%s must be one of: %s.', $label, strtoupper(implode(', ', $allowed)));
        }
        if (in_array($extension, self::IMAGES, true) && @getimagesize((string) $file['tmp_name']) === false) {
            return "{$label} is not a readable picture.";
        }

        return null;
    }

    /**
     * Keeps one uploaded file and returns its path under the uploads folder
     * (folder/name), or null when nothing was sent.
     */
    public static function store(?array $file, string $folder, array $allowed = self::DOCUMENTS): ?string
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $problem = self::problem($file, 'The file', $allowed);
        if ($problem !== null) {
            throw new \RuntimeException($problem);
        }
        if (preg_match('/^[a-z_]+$/', $folder) !== 1) {
            throw new \RuntimeException('Unknown upload folder.');
        }
        $directory = self::root() . '/' . $folder;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('The upload folder could not be created.');
        }
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $name = date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
        $target = $directory . '/' . $name;
        $moved = is_uploaded_file((string) $file['tmp_name'])
            ? move_uploaded_file((string) $file['tmp_name'], $target)
            : (PHP_SAPI === 'cli' && @copy((string) $file['tmp_name'], $target));
        if (!$moved) {
            throw new \RuntimeException('The uploaded file could not be saved.');
        }

        return $folder . '/' . $name;
    }

    public static function url(?string $relative): ?string
    {
        $relative = trim((string) $relative);
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        return \url(array_merge(['files'], explode('/', $relative)));
    }
}
