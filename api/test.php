<?php
/**
 * Prueba de API
 */
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'message' => 'API funcionando',
    'route' => $_GET['_route'] ?? 'no route',
    'method' => $_SERVER['REQUEST_METHOD']
]);
