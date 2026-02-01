<?php

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if (strpos($uri, '/api/') === 0) {
    $_SERVER['SCRIPT_NAME'] = '/api/index.php';
    require __DIR__ . '/api/index.php';
    return;
}

if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

http_response_code(404);
echo "404 Not Found";
