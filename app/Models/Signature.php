<?php

declare(strict_types=1);

namespace Models;

use Core\Database;
use Support\Mailer;

/**
 * A user's own signature, drawn on screen or uploaded on My profile.
 *
 * It goes on a document only where the system recorded that person's action:
 * the trip they dispatched, the invoice they issued, the gate pass they
 * authorised. Nobody else can set it, and it is kept outside the public web
 * root and carried inside the documents, so there is no address to copy it
 * from.
 */
final class Signature
{
    private const MAX_BYTES = 1024 * 1024;

    private static function directory(): string
    {
        return Mailer::uploadsRoot() . '/signatures';
    }

    /** The signature file of a user, or null when they have none. */
    public static function path(?int $userId): ?string
    {
        if ($userId === null || $userId <= 0) {
            return null;
        }
        $statement = Database::connection()->prepare('SELECT signature_file FROM users WHERE id = ?');
        try {
            $statement->execute([$userId]);
        } catch (\Throwable) {
            return null; // before the column exists
        }
        $name = basename((string) $statement->fetchColumn());
        if ($name === '' || $name === '.') {
            return null;
        }
        $path = self::directory() . '/' . $name;

        return is_file($path) ? $path : null;
    }

    /** A file under the uploads folder (a receiver's signature on a delivery), when it is an image. */
    public static function uploadedImage(?string $relative): ?string
    {
        $relative = trim((string) $relative);
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }
        $path = Mailer::uploadsRoot() . '/' . ltrim($relative, '/');
        $info = is_file($path) ? @getimagesize($path) : false;

        return $info !== false && in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true) ? $path : null;
    }

    public static function dataUri(?string $path): ?string
    {
        if ($path === null || !is_file($path)) {
            return null;
        }
        $info = @getimagesize($path);
        $bytes = @file_get_contents($path);

        return $info === false || $bytes === false ? null : 'data:' . $info['mime'] . ';base64,' . base64_encode($bytes);
    }

    public static function forUser(?int $userId): ?string
    {
        return self::dataUri(self::path($userId));
    }

    /**
     * Saves a signature drawn on the screen (a PNG data URL) or an uploaded
     * picture of one.
     *
     * @return string|null what is wrong, or null when it was saved
     */
    public static function save(int $userId, ?string $drawn, ?array $file): ?string
    {
        if ($drawn !== null && $drawn !== '') {
            if (preg_match('~^data:image/png;base64,([A-Za-z0-9+/=]+)$~', $drawn, $match) !== 1) {
                return 'The drawn signature could not be read. Draw it again.';
            }
            $bytes = base64_decode($match[1], true);
            $info = $bytes !== false ? @getimagesizefromstring($bytes) : false;
            if ($bytes === false || $info === false || $info[2] !== IMAGETYPE_PNG || strlen($bytes) > self::MAX_BYTES) {
                return 'The drawn signature could not be read. Draw it again.';
            }
            $extension = 'png';
        } elseif ($file !== null && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ((int) $file['error'] !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) > 2 * self::MAX_BYTES) {
                return 'The signature picture could not be uploaded. Use a PNG or JPG under 2 MB.';
            }
            $info = @getimagesize((string) $file['tmp_name']);
            $extension = match ($info[2] ?? 0) {
                IMAGETYPE_PNG => 'png',
                IMAGETYPE_JPEG => 'jpg',
                default => null,
            };
            if ($extension === null) {
                return 'The signature must be a PNG or JPG picture.';
            }
            $bytes = (string) file_get_contents((string) $file['tmp_name']);
        } else {
            return 'Draw your signature or choose a picture of it first.';
        }

        $directory = self::directory();
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return 'The uploads folder cannot be written to, so the signature was not saved.';
        }
        $name = 'user-' . $userId . '-' . date('YmdHis') . '.' . $extension;
        if (@file_put_contents($directory . '/' . $name, $bytes) === false) {
            return 'The signature could not be saved on the server.';
        }

        $old = self::path($userId);
        Database::connection()->prepare('UPDATE users SET signature_file = ? WHERE id = ?')->execute([$name, $userId]);
        if ($old !== null && basename($old) !== $name) {
            @unlink($old);
        }
        AuditLog::record('account.signature_saved', 'users', (string) $userId);

        return null;
    }

    public static function remove(int $userId): void
    {
        $old = self::path($userId);
        Database::connection()->prepare('UPDATE users SET signature_file = NULL WHERE id = ?')->execute([$userId]);
        if ($old !== null) {
            @unlink($old);
        }
        AuditLog::record('account.signature_removed', 'users', (string) $userId);
    }
}
