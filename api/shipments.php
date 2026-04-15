<?php
/**
 * API de Envíos
 */

require_once __DIR__ . '/helpers.php';
setCorsHeaders();
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getPDO();

// ============================================
// GET /api/shipments - Obtener todos los envíos
// ============================================
if ($method === 'GET') {
    $stmt = $pdo->query("
        SELECT sh.*, s.total_amount as sale_total 
        FROM shipments sh 
        LEFT JOIN sales s ON s.id = sh.sale_id 
        ORDER BY sh.id DESC
    ");
    $shipments = $stmt->fetchAll();
    jsonResponse($shipments);
}

// ============================================
// POST /api/shipments - Crear envío
// ============================================
if ($method === 'POST') {
    $input = getJsonInput();
    $clientName = $input['client_name'] ?? '';
    $clientAddress = $input['client_address'] ?? '';
    $city = $input['city'] ?? '';
    $shippingValue = (float)($input['shipping_value'] ?? 0);
    $transportCompany = $input['transport_company'] ?? '';
    $status = $input['status'] ?? 'Pendiente';
    
    if (!$clientName || !$clientAddress || !$city) {
        errorResponse('Cliente, direccion y ciudad son obligatorios', 400);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO shipments (client_name, client_address, city, shipping_value, transport_company, status) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$clientName, $clientAddress, $city, $shippingValue, $transportCompany, $status]);
    
    $newId = (int)$pdo->lastInsertId();
    jsonResponse(['id' => $newId]);
}

// ============================================
// PUT /api/shipments/:id - Actualizar envío
// ============================================
if ($method === 'PUT') {
    $id = (int)($_GET['id'] ?? 0);
    $input = getJsonInput();
    $clientName = $input['client_name'] ?? '';
    $clientAddress = $input['client_address'] ?? '';
    $city = $input['city'] ?? '';
    $shippingValue = (float)($input['shipping_value'] ?? 0);
    $transportCompany = $input['transport_company'] ?? '';
    $status = $input['status'] ?? 'Pendiente';
    
    $stmt = $pdo->prepare("
        UPDATE shipments SET 
        client_name = ?, client_address = ?, city = ?, shipping_value = ?, transport_company = ?, status = ? 
        WHERE id = ?
    ");
    $stmt->execute([$clientName, $clientAddress, $city, $shippingValue, $transportCompany, $status, $id]);
    
    successResponse();
}

// ============================================
// DELETE /api/shipments/:id - Eliminar envío
// ============================================
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    
    $stmt = $pdo->prepare("DELETE FROM shipments WHERE id = ?");
    $stmt->execute([$id]);
    
    successResponse();
}

errorResponse('Método no permitido', 405);
