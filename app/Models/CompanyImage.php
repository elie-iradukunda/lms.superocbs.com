<?php

declare(strict_types=1);

namespace Models;

use Support\Mailer;

/**
 * The company's images uploaded on Company settings: the logo, printed at the
 * top of documents and exports, and the seal (stamp), printed where a document
 * is issued by the company.
 *
 * Both are kept under the uploads folder, outside the public web root, so
 * there is no address anyone could download them from; documents carry them
 * inside themselves. That matters most for the seal: whoever holds the image
 * can put it on a paper of their own.
 */
final class CompanyImage
{
    public const SEAL = 'company_seal';
    public const LOGO = 'company_logo';

    private const MAX_BYTES = 2 * 1024 * 1024;

    /** What each image is called on screen and in the audit trail. */
    private const NAMES = [self::SEAL => 'seal', self::LOGO => 'logo'];

    private static function directory(): string
    {
        return Mailer::uploadsRoot() . '/company';
    }

    /** The image on disk, or null when none was uploaded. */
    public static function path(string $key): ?string
    {
        $value = trim(Settings::get($key, ''));
        $name = basename($value);
        if ($name === '' || $name === '.') {
            return null;
        }
        $path = self::directory() . '/' . $name;
        if (is_file($path)) {
            return $path;
        }

        // A logo from before uploads: a file name typed in, under public/assets/img.
        if ($key === self::LOGO && !str_contains($value, '..')) {
            $legacy = dirname(__DIR__, 2) . '/public/assets/img/' . ltrim($value, '/\\');
            if (is_file($legacy) && @getimagesize($legacy) !== false) {
                return $legacy;
            }
        }

        return null;
    }

    /** The image ready to put in an <img>, or null. */
    public static function dataUri(string $key): ?string
    {
        $path = self::path($key);
        if ($path === null) {
            return null;
        }
        $info = @getimagesize($path);
        $bytes = @file_get_contents($path);
        if ($info === false || $bytes === false) {
            return null;
        }

        return 'data:' . $info['mime'] . ';base64,' . base64_encode($bytes);
    }

    /**
     * Saves an uploaded image in place of the old one.
     *
     * @return string|null what is wrong with the file, or null when it was saved
     */
    public static function upload(string $key, array $file): ?string
    {
        $what = self::NAMES[$key] ?? null;
        if ($what === null) {
            return 'That image cannot be uploaded here.';
        }
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            return in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? sprintf('The %s image is too large. Use a file under 2 MB.', $what)
                : sprintf('The %s image could not be uploaded. Try again.', $what);
        }
        $temporary = (string) ($file['tmp_name'] ?? '');
        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return sprintf('The %s image is too large. Use a file under 2 MB.', $what);
        }
        $info = @getimagesize($temporary);
        $extension = match ($info[2] ?? 0) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_JPEG => 'jpg',
            default => null,
        };
        if ($extension === null) {
            return sprintf('The %s must be a PNG or JPG image.', $what);
        }

        $directory = self::directory();
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return sprintf('The uploads folder cannot be written to, so the %s was not saved.', $what);
        }

        // A new name each time, so no browser shows the old image from its cache.
        $name = $what . '-' . date('YmdHis') . '.' . $extension;
        $moved = is_uploaded_file($temporary) ? @move_uploaded_file($temporary, $directory . '/' . $name) : @copy($temporary, $directory . '/' . $name);
        if (!$moved) {
            return sprintf('The %s could not be saved on the server.', $what);
        }

        $old = self::uploaded($key);
        Settings::put($key, $name);
        if ($old !== null && basename($old) !== $name) {
            @unlink($old);
        }
        AuditLog::record('settings.' . $what . '_uploaded', 'settings', $key);

        return null;
    }

    public static function remove(string $key): void
    {
        $what = self::NAMES[$key] ?? null;
        if ($what === null) {
            return;
        }
        $old = self::uploaded($key);
        Settings::put($key, '');
        if ($old !== null) {
            @unlink($old);
        }
        AuditLog::record('settings.' . $what . '_removed', 'settings', $key);
    }

    /** The uploaded file only, never a legacy one under public, which is not ours to delete. */
    private static function uploaded(string $key): ?string
    {
        $path = self::path($key);

        return $path !== null && str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', self::directory()) . '/') ? $path : null;
    }
}
