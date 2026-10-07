<?php

declare(strict_types=1);

namespace Controllers;

use Models\CompanyImage;

/**
 * The company logo at a public address, for the top of every email.
 *
 * Mail programs do not show images embedded in the message, and they cannot
 * sign in, so the logo is the one file served without a session. Only the
 * logo: the seal and every upload stay behind the login.
 */
final class BrandController
{
    public function logo(): void
    {
        $path = CompanyImage::path(CompanyImage::LOGO);
        $info = $path !== null ? @getimagesize($path) : false;
        if ($path === null || $info === false || !in_array($info['mime'], ['image/png', 'image/jpeg'], true)) {
            http_response_code(404);
            exit;
        }

        header('Content-Type: ' . $info['mime']);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: public, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
}
