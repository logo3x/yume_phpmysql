<?php
/**
 * API de Ventas
 */

require_once __DIR__ . '/helpers.php';
setCorsHeaders();
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getPDO();

// ============================================
// GET /api/sales - Obtener todas las ventas
// ============================================
if ($method === 'GET') {
    $stmt = $pdo->query("
        SELECT s.*, p.name AS product_name, c.name AS client_name 
        FROM sales s 
        JOIN products p ON p.id = s.product_id 
        LEFT JOIN clients c ON c.id = s.client_id 
        ORDER BY s.id DESC
    ");
    $sales = $stmt->fetchAll();
    jsonResponse($sales);
}

// ============================================
// POST /api/sales - Crear venta
// ============================================
if ($method === 'POST') {
    $input = getJsonInput();
    $saleDate = $input['sale_date'] ?? date('Y-m-d');
    $productId = (int)($input['product_id'] ?? 0);
    $quantity = (int)($input['quantity'] ?? 0);
    $salePrice = (float)($input['sale_price'] ?? 0);
    $clientId = $input['client_id'] ?: null;
    $paymentMethod = $input['payment_method'] ?? '';
    $includesShipping = (int)($input['includes_shipping'] ?? 0);
    $shippingValue = (float)($input['shipping_value'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        errorResponse('Producto no existe', 404);
    }
    
    if ($quantity <= 0) {
        errorResponse('Cantidad invalida', 400);
    }
    
    if ($product['stock'] < $quantity) {
        errorResponse('Stock insuficiente', 400);
    }
    
    $unitSale = $salePrice ?: (float)$product['sale_price'];
    $totalAmount = $unitSale * $quantity + ($includesShipping ? $shippingValue : 0);
    $totalCost = (float)$product['total_real_cost'] * $quantity;
    $profit = $totalAmount - $totalCost;
    
    $stmt = $pdo->prepare("
        INSERT INTO sales 
        (sale_date, product_id, quantity, sale_price, client_id, payment_method, includes_shipping, shipping_value, total_amount, total_cost, profit) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $saleDate, $productId, $quantity, $unitSale, $clientId, $paymentMethod, 
        $includesShipping ? 1 : 0, $shippingValue, $totalAmount, $totalCost, $profit
    ]);
    
    $newId = (int)$pdo->lastInsertId();
    
    // Actualizar stock
    $newStock = $product['stock'] - $quantity;
    $status = getProductStatus($newStock);
    $stmt = $pdo->prepare("UPDATE products SET stock = ?, status = ? WHERE id = ?");
    $stmt->execute([$newStock, $status, $productId]);
    
    // Registrar movimiento de caja
    $stmt = $pdo->prepare("INSERT INTO cash_movements (movement_date, type, category, amount, notes) VALUES (?, 'Ingreso', 'Ventas', ?, ?)");
    $stmt->execute([$saleDate, $totalAmount, "Venta producto {$product['name']}"]);
    
    jsonResponse(['id' => $newId, 'profit' => $profit, 'remaining_stock' => $newStock]);
}

// ============================================
// DELETE /api/sales/:id - Eliminar venta
// ============================================
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    
    $stmt = $pdo->prepare("DELETE FROM sales WHERE id = ?");
    $stmt->execute([$id]);
    
    successResponse();
}

errorResponse('Método no permitido', 405);
