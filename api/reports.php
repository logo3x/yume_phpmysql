<?php
/**
 * API de Reportes
 */

require_once __DIR__ . '/helpers.php';
setCorsHeaders();
requireAuth();
extractPathParams();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$pdo = getPDO();

// ============================================
// GET /api/reports/summary - Resumen general
// ============================================
if ($method === 'GET' && $action === 'summary') {
    $today = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as v FROM sales WHERE DATE(sale_date) = CURDATE()")->fetch()['v'];
    $week = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as v FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)")->fetch()['v'];
    $month = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as v FROM sales WHERE DATE_FORMAT(sale_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')")->fetch()['v'];
    $totalProfit = $pdo->query("SELECT COALESCE(SUM(profit), 0) as v FROM sales")->fetch()['v'];
    $outOfStock = $pdo->query("SELECT * FROM products WHERE stock <= 0 ORDER BY name")->fetchAll();
    $income = $pdo->query("SELECT COALESCE(SUM(amount), 0) as v FROM cash_movements WHERE type = 'Ingreso'")->fetch()['v'];
    $expense = $pdo->query("SELECT COALESCE(SUM(amount), 0) as v FROM cash_movements WHERE type = 'Egreso'")->fetch()['v'];
    $mostSold = $pdo->query("SELECT p.name, COALESCE(SUM(s.quantity), 0) as qty FROM products p LEFT JOIN sales s ON s.product_id = p.id GROUP BY p.id ORDER BY qty DESC LIMIT 5")->fetchAll();
    
    jsonResponse([
        'today' => (float)$today,
        'week' => (float)$week,
        'month' => (float)$month,
        'totalProfit' => (float)$totalProfit,
        'mostSold' => $mostSold,
        'outOfStock' => $outOfStock,
        'income' => (float)$income,
        'expense' => (float)$expense
    ]);
}

// ============================================
// GET /api/reports/charts - Datos para gráficas
// ============================================
if ($method === 'GET' && $action === 'charts') {
    $salesByMonth = $pdo->query("
        SELECT DATE_FORMAT(sale_date, '%Y-%m') as month, 
               COALESCE(SUM(total_amount),0) as total_sales, 
               COALESCE(SUM(profit),0) as total_profit 
        FROM sales 
        GROUP BY DATE_FORMAT(sale_date, '%Y-%m') 
        ORDER BY month
    ")->fetchAll();
    
    $topProducts = $pdo->query("
        SELECT p.name, COALESCE(SUM(s.quantity),0) as qty 
        FROM products p 
        LEFT JOIN sales s ON s.product_id = p.id 
        GROUP BY p.id 
        ORDER BY qty DESC 
        LIMIT 10
    ")->fetchAll();
    
    jsonResponse([
        'salesByMonth' => $salesByMonth,
        'topProducts' => $topProducts
    ]);
}

// ============================================
// GET /api/reports/filtered - Reporte con filtros de fecha
// ============================================
if ($method === 'GET' && $action === 'filtered') {
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    
    $dateFilter = '';
    $params = [];
    
    if ($startDate && $endDate) {
        $dateFilter = " WHERE sale_date >= ? AND sale_date <= ?";
        $params = [$startDate, $endDate];
    } elseif ($startDate) {
        $dateFilter = " WHERE sale_date >= ?";
        $params = [$startDate];
    } elseif ($endDate) {
        $dateFilter = " WHERE sale_date <= ?";
        $params = [$endDate];
    }
    
    $totalSales = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) as v FROM sales{$dateFilter}");
    $totalSales->execute($params);
    $totalSalesVal = $totalSales->fetch()['v'];
    
    $totalProfit = $pdo->prepare("SELECT COALESCE(SUM(profit),0) as v FROM sales{$dateFilter}");
    $totalProfit->execute($params);
    $totalProfitVal = $totalProfit->fetch()['v'];
    
    $totalQty = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) as v FROM sales{$dateFilter}");
    $totalQty->execute($params);
    $totalQtyVal = $totalQty->fetch()['v'];
    
    $byDay = $pdo->prepare("
        SELECT sale_date as day, 
               COALESCE(SUM(total_amount),0) as total_sales, 
               COALESCE(SUM(profit),0) as total_profit, 
               COALESCE(SUM(quantity),0) as total_qty 
        FROM sales{$dateFilter} 
        GROUP BY sale_date 
        ORDER BY sale_date
    ");
    $byDay->execute($params);
    $byDayRows = $byDay->fetchAll();
    
    $topP = $pdo->prepare("
        SELECT p.name, COALESCE(SUM(s.quantity),0) as qty, COALESCE(SUM(s.total_amount),0) as total 
        FROM sales s 
        JOIN products p ON p.id = s.product_id{$dateFilter} 
        GROUP BY p.id 
        ORDER BY qty DESC 
        LIMIT 10
    ");
    $topP->execute($params);
    $topProducts = $topP->fetchAll();
    
    jsonResponse([
        'totalSales' => (float)$totalSalesVal,
        'totalProfit' => (float)$totalProfitVal,
        'totalQty' => (int)$totalQtyVal,
        'salesByDay' => $byDayRows,
        'topProducts' => $topProducts
    ]);
}

errorResponse('Ruta no encontrada', 404);
