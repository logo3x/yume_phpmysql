<?php
/**
 * API de Configuración
 */

require_once __DIR__ . '/helpers.php';
setCorsHeaders();
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getPDO();

// ============================================
// GET /api/settings - Obtener configuración
// ============================================
if ($method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
    $settings = $stmt->fetch();
    jsonResponse($settings ?: ['default_margin_percent' => 30, 'initial_investment' => 0]);
}

// ============================================
// PUT /api/settings - Actualizar configuración
// ============================================
if ($method === 'PUT') {
    $input = getJsonInput();
    $defaultMargin = $input['default_margin_percent'] ?? 30;
    $initialInvestment = $input['initial_investment'] ?? 0;
    
    $stmt = $pdo->prepare("
        UPDATE settings 
        SET default_margin_percent = COALESCE(?, 30), initial_investment = COALESCE(?, 0) 
        WHERE id = 1
    ");
    $stmt->execute([$defaultMargin, $initialInvestment]);
    
    successResponse();
}

errorResponse('Método no permitido', 405);
