<?php
/**
 * Debug route
 */

require_once __DIR__ . '/helpers.php';
setCorsHeaders();

header('Content-Type: text/plain');
echo "METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "GET['action']: " . ($_GET['action'] ?? 'NOT SET') . "\n";
echo "GET['_route']: " . ($_GET['_route'] ?? 'NOT SET') . "\n";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";

// This is auth.php logic
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET' && $action === 'status') {
    $pdo = getPDO();
    $stmt = $pdo->query("SELECT COUNT(*) as c FROM users");
    $hasUsers = (int)$stmt->fetch()['c'] > 0;
    jsonResponse(['authenticated' => false, 'hasUsers' => $hasUsers]);
}
