<?php

/*
 * Serveur local de test (composer start) : php -S 127.0.0.1:8000 -t public serve.php
 * Remplace "php artisan serve", qui échoue parfois sous Windows (« Failed to listen »).
 * Ne sert pas en production (le serveur utilise Nginx).
 */

$public = __DIR__.'/public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

// Fichier existant (CSS, images…) : servi directement.
if ($uri !== '/' && is_file($public.$uri)) {
    return false;
}

chdir($public);
require $public.'/index.php';
