<?php

declare(strict_types=1);

/**
 * PHP built-in server router:
 *   cd backend && php -S localhost:8080 router.php
 *
 * Vite proxy targets http://localhost:8080/api
 */


 if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return substr($haystack, -strlen($needle)) === $needle;
    }
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';

if (preg_match('#^/api(?:/index\.php)?(.*)$#', $uri)) {
    require __DIR__ . '/api/index.php';
    return true;
}

if ($uri === '/seed.php' || str_ends_with($uri, '/seed.php')) {
    require __DIR__ . '/seed.php';
    return true;
}

return false;
