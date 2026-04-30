<?php
/**
 * API de Resumen de Caja
 */

require_once __DIR__ . '/helpers.php';
setCorsHeaders();
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getPDO();

// ============================================
// GET /api/cash/summary - Resumen de caja
// ============================================
if ($method === 'GET') {
    $settings = $pdo->query("SELECT * FROM settings WHERE id = 1")->fetch();
    $incomes = $pdo->query("SELECT COALESCE(SUM(amount),0) as v FROM cash_movements WHERE type = 'Ingreso'")->fetch()['v'];
    $expenses = $pdo->query("SELECT COALESCE(SUM(amount),0) as v FROM cash_movements WHERE type = 'Egreso'")->fetch()['v'];
    
    $initialInvestment = (float)($settings['initial_investment'] ?? 0);
    // Dinero en caja = solo flujo real (ingresos por ventas - egresos).
    // La inversión inicial es capital convertido en inventario, NO efectivo.
    $current = (float)$incomes - (float)$expenses;

    jsonResponse([
        'initial_investment' => $initialInvestment,
        'incomes' => (float)$incomes,
        'expenses' => (float)$expenses,
        'current' => $current
    ]);
}

errorResponse('Método no permitido', 405);
