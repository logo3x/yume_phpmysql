<?php
/**
 * Enrutador principal de la API
 * Maneja todas las peticiones a /api/*
 */

require_once __DIR__ . '/helpers.php';

// Get the route from query string or URI
$route = $_GET['_route'] ?? '';

// If not from htaccess, try to extract from URI
if (empty($route)) {
    $requestUri = $_SERVER['REQUEST_URI'];
    $path = parse_url($requestUri, PHP_URL_PATH);
    $route = str_replace('/api/', '', $path);
}

// Route the request based on path
switch (true) {
    // Health check
    case $route === 'health' || $route === 'api/health':
        require __DIR__ . '/health.php';
        break;
    
    // Auth routes
    case $route === 'auth/status':
    case strpos($route, 'auth/') === 0:
        $action = str_replace('auth/', '', $route);
        $_GET['action'] = $action;
        require __DIR__ . '/auth.php';
        break;
    
    // Settings
    case $route === 'settings':
        require __DIR__ . '/settings.php';
        break;
    
    // Admin routes
    case $route === 'admin/users' || $route === 'admin/roles':
        $action = str_replace('admin/', '', $route);
        $_GET['action'] = $action;
        require __DIR__ . '/admin.php';
        break;
    
    // Products
    case $route === 'products':
        require __DIR__ . '/products.php';
        break;
    
    // Clients
    case $route === 'clients':
        require __DIR__ . '/clients.php';
        break;
    
    // Purchases (with optional id for DELETE)
    case $route === 'purchases':
    case preg_match('#^purchases/(\d+)$#', $route, $puM) === 1:
        if (!empty($puM[1])) $_GET['id'] = (int)$puM[1];
        require __DIR__ . '/purchases.php';
        break;
    
    // Sales
    case $route === 'sales':
        require __DIR__ . '/sales.php';
        break;
    
    // Shipments
    case $route === 'shipments':
        require __DIR__ . '/shipments.php';
        break;
    
    // Cash movements (with optional id for DELETE)
    case $route === 'cash-movements':
    case preg_match('#^cash-movements/(\d+)$#', $route, $cmM) === 1:
        if (!empty($cmM[1])) $_GET['id'] = (int)$cmM[1];
        require __DIR__ . '/cash-movements.php';
        break;

    // Cash summary
    case $route === 'cash/summary':
        require __DIR__ . '/cash-summary.php';
        break;
    
    // Reports
    case strpos($route, 'reports/') === 0:
        $action = str_replace('reports/', '', $route);
        $_GET['action'] = $action;
        require __DIR__ . '/reports.php';
        break;
    
    // Backups
    case $route === 'backups':
        require __DIR__ . '/backups.php';
        break;
    
    // Export/Import
    case strpos($route, 'export/') === 0:
        $_GET['type'] = str_replace('export/', '', $route);
        require __DIR__ . '/export.php';
        break;
    
    case strpos($route, 'import/') === 0:
        $_GET['type'] = str_replace('import/', '', $route);
        require __DIR__ . '/export.php';
        break;
    
    default:
        errorResponse('Ruta no encontrada: ' . $route, 404);
}
