<?php
/**
 * API de Caja (Movimientos de caja)
 */

require_once __DIR__ . '/helpers.php';
setCorsHeaders();
requireAuth();
extractPathParams();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getPDO();

// ============================================
// POST /api/cash-movements - Crear movimiento
// ============================================
if ($method === 'POST') {
    $input = getJsonInput();
    $movementDate = $input['movement_date'] ?? '';
    $type = $input['type'] ?? '';
    $category = $input['category'] ?? '';
    $amount = $input['amount'] ?? 0;
    $notes = $input['notes'] ?? '';
    
    if (!$movementDate || !$type || !$category || !$amount) {
        errorResponse('Todos los campos son obligatorios', 400);
    }
    
    $stmt = $pdo->prepare("INSERT INTO cash_movements (movement_date, type, category, amount, notes) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$movementDate, $type, $category, (float)$amount, $notes]);
    
    $newId = (int)$pdo->lastInsertId();
    jsonResponse(['id' => $newId]);
}

// ============================================
// GET /api/cash-movements - Obtener movimientos con filtros
// ============================================
if ($method === 'GET') {
    $sql = "SELECT * FROM cash_movements WHERE 1=1";
    $params = [];
    
    if (!empty($_GET['start_date'])) {
        $sql .= " AND movement_date >= ?";
        $params[] = $_GET['start_date'];
    }
    
    if (!empty($_GET['end_date'])) {
        $sql .= " AND movement_date <= ?";
        $params[] = $_GET['end_date'];
    }
    
    if (!empty($_GET['type']) && $_GET['type'] !== 'all') {
        $sql .= " AND type = ?";
        $params[] = $_GET['type'];
    }
    
    $sql .= " ORDER BY id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $movements = $stmt->fetchAll();
    jsonResponse($movements);
}

// ============================================
// DELETE /api/cash-movements/:id - Eliminar movimiento
// ============================================
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        errorResponse('ID invalido', 400);
    }

    $stmt = $pdo->prepare("SELECT id FROM cash_movements WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        errorResponse('Movimiento no encontrado', 404);
    }

    $stmt = $pdo->prepare("DELETE FROM cash_movements WHERE id = ?");
    $stmt->execute([$id]);

    successResponse();
}

errorResponse('Método no permitido', 405);
