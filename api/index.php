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

// Also remove project directory prefix if present
$projectDir = basename(__DIR__ . '/../');
if (strpos($route, $projectDir . '/api/') === 0) {
    $route = str_replace($projectDir . '/api/', '', $route);
} elseif (strpos($route, 'yume_phpmysql-master/api/') === 0) {
    $route = str_replace('yume_phpmysql-master/api/', '', $route);
}

// Extract ID from route if present (e.g., "products/123" -> route="products", id="123")
$id = null;
if (preg_match('#^([a-zA-Z_-]+)/(\d+)$#', $route, $matches)) {
    $route = $matches[1];
    $id = (int)$matches[2];
    $_GET['id'] = $id;
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
    
    // Admin routes (with or without ID)
    case strpos($route, 'admin/') === 0:
        $action = str_replace('admin/', '', $route);
        // Extract ID if present
        if (preg_match('#^([a-z_]+)/(\d+)$#', $action, $matches)) {
            $action = $matches[1];
            $_GET['id'] = (int)$matches[2];
        }
        $_GET['action'] = $action;
        require __DIR__ . '/admin.php';
        break;
    
    // Products (with or without ID)
    case strpos($route, 'products') === 0:
        require __DIR__ . '/products.php';
        break;
    
    // Clients (with or without ID)
    case strpos($route, 'clients') === 0:
        require __DIR__ . '/clients.php';
        break;
    
    // Purchases (with or without ID)
    case strpos($route, 'purchases') === 0:
        require __DIR__ . '/purchases.php';
        break;
    
    // Sales (with or without ID)
    case strpos($route, 'sales') === 0:
        require __DIR__ . '/sales.php';
        break;
    
    // Shipments (with or without ID)
    case strpos($route, 'shipments') === 0:
        require __DIR__ . '/shipments.php';
        break;
    
    // Cash movements (with or without ID)
    case strpos($route, 'cash-movements') === 0:
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
    case strpos($route, 'backups/') === 0:
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
    
    // Templates
    case strpos($route, 'template/') === 0:
        $_GET['template'] = str_replace('template/', '', $route);
        require __DIR__ . '/export.php';
        break;
    
    default:
        errorResponse('Ruta no encontrada: ' . $route, 404);
}
