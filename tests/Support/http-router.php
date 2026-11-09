<?php

declare(strict_types=1);

// The router for the test app, served by `php -S` from tests/Support/HttpClient.php. Real files, such
// as published control panel resources, are served directly; everything else goes to the app's index.
$root = $_SERVER['DOCUMENT_ROOT'] ?? '';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

if ($path !== '/' && is_file($root . $path)) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';

require $root . '/index.php';
