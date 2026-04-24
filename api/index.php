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
    
    // Products (admite /products y /products/{id})
    case $route === 'products' || strpos($route, 'products/') === 0:
        if (strpos($route, 'products/') === 0) {
            $_GET['id'] = (int)str_replace('products/', '', $route);
        }
        require __DIR__ . '/products.php';
        break;

    // Clients (admite /clients y /clients/{id})
    case $route === 'clients' || strpos($route, 'clients/') === 0:
        if (strpos($route, 'clients/') === 0) {
            $_GET['id'] = (int)str_replace('clients/', '', $route);
        }
        require __DIR__ . '/clients.php';
        break;

    // Purchases (admite /purchases y /purchases/{id})
    case $route === 'purchases' || strpos($route, 'purchases/') === 0:
        if (strpos($route, 'purchases/') === 0) {
            $_GET['id'] = (int)str_replace('purchases/', '', $route);
        }
        require __DIR__ . '/purchases.php';
        break;

    // Sales (admite /sales y /sales/{id})
    case $route === 'sales' || strpos($route, 'sales/') === 0:
        if (strpos($route, 'sales/') === 0) {
            $_GET['id'] = (int)str_replace('sales/', '', $route);
        }
        require __DIR__ . '/sales.php';
        break;

    // Shipments (admite /shipments y /shipments/{id})
    case $route === 'shipments' || strpos($route, 'shipments/') === 0:
        if (strpos($route, 'shipments/') === 0) {
            $_GET['id'] = (int)str_replace('shipments/', '', $route);
        }
        require __DIR__ . '/shipments.php';
        break;

    // Cash movements (admite /cash-movements y /cash-movements/{id})
    case $route === 'cash-movements' || strpos($route, 'cash-movements/') === 0:
        if (strpos($route, 'cash-movements/') === 0) {
            $_GET['id'] = (int)str_replace('cash-movements/', '', $route);
        }
        require __DIR__ . '/cash-movements.php';
        break;
    
    // Cash summary
    case $route === 'cash/summary':
        require __DIR__ . '/cash-summary.php';
        break;
    
    // Reports (admite /reports?action=X y /reports/X)
    case $route === 'reports' || strpos($route, 'reports/') === 0:
        if (strpos($route, 'reports/') === 0) {
            $_GET['action'] = str_replace('reports/', '', $route);
        }
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
