<?php
/**
 * API de Compras
 */

require_once __DIR__ . '/helpers.php';
setCorsHeaders();
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getPDO();

// ============================================
// GET /api/purchases - Obtener todas las compras
// ============================================
if ($method === 'GET') {
    $stmt = $pdo->query("
        SELECT p.*, pr.name AS product_name 
        FROM purchases p 
        JOIN products pr ON pr.id = p.product_id 
        ORDER BY p.id DESC
    ");
    $purchases = $stmt->fetchAll();
    jsonResponse($purchases);
}

// ============================================
// POST /api/purchases - Crear compra
// ============================================
if ($method === 'POST') {
    $input = getJsonInput();
    $productId = (int)($input['product_id'] ?? 0);
    $quantity = (int)($input['quantity'] ?? 0);
    $purchasePrice = (float)($input['purchase_price'] ?? 0);
    $supplier = $input['supplier'] ?? '';
    $shippingCost = (float)($input['shipping_cost'] ?? 0);
    $purchaseDate = $input['purchase_date'] ?? date('Y-m-d');
    
    $totalInvested = $quantity * $purchasePrice + $shippingCost;
    
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        errorResponse('Producto no existe', 404);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO purchases (product_id, quantity, purchase_price, supplier, shipping_cost, purchase_date, total_invested) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$productId, $quantity, $purchasePrice, $supplier ?: $product['supplier'], $shippingCost, $purchaseDate, $totalInvested]);
    
    $newId = (int)$pdo->lastInsertId();
    
    // Actualizar stock del producto
    $newStock = $product['stock'] + $quantity;
    $status = getProductStatus($newStock);
    $stmt = $pdo->prepare("UPDATE products SET stock = ?, status = ?, purchase_price = ?, extra_costs = ? WHERE id = ?");
    $stmt->execute([$newStock, $status, $purchasePrice, $shippingCost, $productId]);
    
    // Registrar movimiento de caja
    $stmt = $pdo->prepare("INSERT INTO cash_movements (movement_date, type, category, amount, notes) VALUES (?, 'Egreso', 'Compra de productos', ?, ?)");
    $stmt->execute([$purchaseDate, $totalInvested, "Compra producto {$product['name']}"]);
    
    jsonResponse(['id' => $newId, 'total_invested' => $totalInvested]);
}

errorResponse('Método no permitido', 405);
