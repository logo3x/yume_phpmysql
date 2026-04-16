<?php
/**
 * API de Clientes
 */

require_once __DIR__ . '/helpers.php';
setCorsHeaders();
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getPDO();

// ============================================
// GET /api/clients - Obtener todos los clientes
// GET /api/clients/:id - Obtener un cliente específico
// ============================================
if ($method === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$id]);
        $client = $stmt->fetch();
        if (!$client) {
            errorResponse('Cliente no encontrado', 404);
        }
        jsonResponse($client);
    } else {
        $stmt = $pdo->query("SELECT * FROM clients ORDER BY id DESC");
        $clients = $stmt->fetchAll();
        jsonResponse($clients);
    }
}

// ============================================
// POST /api/clients - Crear cliente
// ============================================
if ($method === 'POST') {
    $input = getJsonInput();
    $name = $input['name'] ?? '';
    $phone = $input['phone'] ?? '';
    $address = $input['address'] ?? '';
    $city = $input['city'] ?? '';
    
    if (!$name) {
        errorResponse('Nombre es obligatorio', 400);
    }
    
    $stmt = $pdo->prepare("INSERT INTO clients (name, phone, address, city) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $phone, $address, $city]);
    
    $newId = (int)$pdo->lastInsertId();
    jsonResponse(['id' => $newId]);
}

// ============================================
// PUT /api/clients/:id - Actualizar cliente
// ============================================
if ($method === 'PUT') {
    $id = (int)($_GET['id'] ?? 0);
    $input = getJsonInput();
    $name = $input['name'] ?? '';
    $phone = $input['phone'] ?? '';
    $address = $input['address'] ?? '';
    $city = $input['city'] ?? '';
    
    if (!$name) {
        errorResponse('Nombre es obligatorio', 400);
    }
    
    $stmt = $pdo->prepare("UPDATE clients SET name = ?, phone = ?, address = ?, city = ? WHERE id = ?");
    $stmt->execute([$name, $phone, $address, $city, $id]);
    
    successResponse();
}

// ============================================
// DELETE /api/clients/:id - Eliminar cliente
// ============================================
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    
    $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    
    successResponse();
}

errorResponse('Método no permitido', 405);
