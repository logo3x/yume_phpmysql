<?php
/**
 * Health check endpoint
 */

require_once __DIR__ . '/helpers.php';
setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $pdo = getPDO();
        $stmt = $pdo->query("SELECT NOW() as now");
        $row = $stmt->fetch();
        jsonResponse([
            'status' => 'ok',
            'db' => 'connected',
            'time' => $row['now']
        ]);
    } catch (Exception $e) {
        jsonResponse([
            'status' => 'degraded',
            'db' => 'disconnected',
            'error' => $e->getMessage()
        ]);
    }
}
