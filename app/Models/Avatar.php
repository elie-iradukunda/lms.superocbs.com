<?php

declare(strict_types=1);

namespace Models;

use Core\Database;

/**
 * The picture beside somebody's name.
 *
 * Every user was shown the same stock photograph a theme shipped with, so the
 * face in the corner was a stranger's and the face beside a name meant nothing.
 * A person can now upload their own, and anyone who has not is shown their
 * initials rather than somebody else.
 *
 * Photos are stored with the other uploads, outside the web root, and served
 * through a route that requires a sign-in — a staff photograph is not something
 * to leave on a public URL that can be guessed.
 */
final class Avatar
{
    /** What a browser will actually display, and nothing that can execute. */
    private const ALLOWED = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

    /** A face needs no more than this, and a 12 MB phone photo helps nobody. */
    private const MAX_BYTES = 2097152;

    /**
     * Saves an uploaded photograph and returns its stored path.
     *
     * @param array<string, mixed>|null $file one entry of $_FILES
     * @return array{path: ?string, error: ?string}
     */
    public static function store(?array $file, ?string $existing = null): array
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['path' => $existing, 'error' => null];
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['path' => $existing, 'error' => 'The photo could not be uploaded. Try a smaller file.'];
        }

        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['path' => $existing, 'error' => 'A photo must be 2 MB or smaller.'];
        }

        // The extension is what the browser claims; the image header is what the
        // file actually is. Both have to agree, or a script renamed to .png
        // would be stored and later served.
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED, true)) {
            return ['path' => $existing, 'error' => 'A photo must be a PNG, JPG, WEBP or GIF image.'];
        }

        $size = @getimagesize((string) ($file['tmp_name'] ?? ''));
        if ($size === false) {
            return ['path' => $existing, 'error' => 'That file is not an image.'];
        }

        $directory = self::directory();
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return ['path' => $existing, 'error' => 'The photo folder could not be created.'];
        }

        $name = date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
        if (!move_uploaded_file((string) $file['tmp_name'], $directory . '/' . $name)) {
            return ['path' => $existing, 'error' => 'The photo could not be saved.'];
        }

        // The one it replaces is of no further use to anybody.
        self::forget($existing);

        return ['path' => 'avatars/' . $name, 'error' => null];
    }

    /** Removes a stored photo from disk. Missing files are not an error. */
    public static function forget(?string $path): void
    {
        if ($path === null || trim($path) === '') {
            return;
        }

        $file = self::resolve($path);
        if ($file !== null && is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * The full path of a stored photo, or null when it is not one of ours.
     *
     * Everything the route serves goes through here, so a name that tries to
     * climb out of the folder resolves to nothing rather than to a file.
     */
    public static function resolve(string $path): ?string
    {
        $name = basename(str_replace('\\', '/', $path));
        if (preg_match('/^[A-Za-z0-9._-]+$/', $name) !== 1 || str_contains($name, '..')) {
            return null;
        }

        $root = realpath(self::directory());
        $file = $root === false ? false : realpath($root . '/' . $name);

        if ($root === false || $file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $file;
    }

    /** The web address of a person's photo, or null when they have none. */
    public static function url(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        return \url(['avatar', basename(str_replace('\\', '/', $path))]);
    }

    /**
     * What to show instead of a photo.
     *
     * Initials are not a placeholder for a face: they are the person's own name,
     * which is more use than a stock photograph of somebody who does not work
     * here. Two letters from a full name, one from a single word.
     */
    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $words = array_values(array_filter($words, static fn (string $w): bool => $w !== ''));

        if ($words === []) {
            return '?';
        }

        $first = mb_strtoupper(mb_substr($words[0], 0, 1));
        if (count($words) === 1) {
            return $first;
        }

        return $first . mb_strtoupper(mb_substr($words[count($words) - 1], 0, 1));
    }

    /**
     * A colour taken from the name, so the same person is always the same
     * colour and two people beside each other are rarely the same one.
     */
    public static function tint(string $name): string
    {
        $palette = ['#5e72e4', '#11cdef', '#2dce89', '#fb6340', '#f5365c', '#8965e0', '#ffd600', '#f3a4b5'];

        return $palette[abs(crc32($name)) % count($palette)];
    }

    /** The photo on record for a user, read fresh rather than from the session. */
    public static function forUser(int $userId): ?string
    {
        $statement = Database::connection()->prepare('SELECT avatar_path FROM users WHERE id = ?');
        $statement->execute([$userId]);
        $path = $statement->fetchColumn();

        return $path === false || $path === null || $path === '' ? null : (string) $path;
    }

    private static function directory(): string
    {
        return rtrim((string) \config('uploads.path', dirname(__DIR__, 2) . '/storage/uploads'), "/\\") . '/avatars';
    }
}
