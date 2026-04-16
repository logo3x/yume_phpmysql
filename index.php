<?php
/**
 * Punto de entrada principal
 * Sirve el frontend estático o redirige a la API
 */

$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove leading slash
$path = ltrim($path, '/');

// If it's an API request, route to api/index.php with proper route
if (strpos($path, 'api/') === 0 || $path === 'api') {
    // Extract the route after 'api/'
    $apiRoute = ($path === 'api') ? '' : str_replace('api/', '', $path);
    $_GET['_route'] = $apiRoute;
    require __DIR__ . '/api/index.php';
    exit;
}

// Serve static files from public directory (check public/ first, then root)
$publicPath = __DIR__ . '/public/' . $path;
if (file_exists($publicPath) && is_file($publicPath)) {
    $filePath = $publicPath;
} else {
    $filePath = __DIR__ . '/' . $path;
}

// If path is empty or ends with /, serve index.html
if (empty($path) || substr($path, -1) === '/' || $path === 'index.php') {
    $filePath = __DIR__ . '/public/index.html';
}

// Check if file exists
if (file_exists($filePath) && is_file($filePath)) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    
    $mimeTypes = [
        'html' => 'text/html; charset=utf-8',
        'htm' => 'text/html; charset=utf-8',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
    ];
    
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }
    
    // Cache control for static assets
    if ($ext !== 'html' && $ext !== 'htm') {
        header('Cache-Control: public, max-age=3600');
    }
    
    readfile($filePath);
    exit;
}

// If file not found, serve index.html for SPA routing
if (file_exists(__DIR__ . '/public/index.html')) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/public/index.html');
} else {
    http_response_code(404);
    echo '404 - Not Found';
}
