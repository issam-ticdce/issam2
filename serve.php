<?php

/*
 * Serveur intégré de PHP (composer start, demarrer.bat) :
 *   php -S 127.0.0.1:8000 -t public serve.php
 * Remplace "php artisan serve", qui échoue parfois sous Windows (« Failed to listen »).
 * Ne sert pas en production derrière Nginx.
 */

$root = __DIR__;
$public = $root.'/public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

// Fichier existant dans public/ (CSS, images…) : servi directement par PHP.
if ($uri !== '/' && is_file($public.$uri)) {
    return false;
}

// Images envoyées par les startups : servies depuis storage/app/public,
// sans avoir besoin du lien "public/storage" (difficile à créer sous Windows).
if (str_starts_with($uri, '/storage/') && ! str_contains($uri, '..')) {
    $file = $root.'/storage/app/public/'.substr($uri, 9);

    if (is_file($file)) {
        header('Content-Type: '.(mime_content_type($file) ?: 'application/octet-stream'));
        header('Content-Length: '.filesize($file));
        header('Cache-Control: public, max-age=86400');
        readfile($file);

        return true;
    }
}

chdir($public);
require $public.'/index.php';
