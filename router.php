<?php
// router.php for PHP built-in server (Backend)

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Map /api requests to the API index
if (strpos($uri, '/api/') === 0) {
    $_SERVER['SCRIPT_NAME'] = '/api/index.php';
    require __DIR__ . '/api/index.php';
    return;
}

// Serve static files if they exist
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false; // serve the requested resource as-is.
}

// Return 404 for anything else not found
http_response_code(404);
echo "404 Not Found";
