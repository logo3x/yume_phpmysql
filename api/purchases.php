<?php
/**
 * API de Compras
 */

require_once __DIR__ . '/helpers.php';
setCorsHeaders();
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getPDO();

// Debug
file_put_contents(__DIR__ . '/purchases_debug.log', date('Y-m-d H:i:s') . " | method: $method | id: " . ($_GET['id'] ?? 'none') . "\n", FILE_APPEND);

// ============================================
// GET /api/purchases - Obtener todas las compras
// GET /api/purchases/:id - Obtener una compra específica
// ============================================
if ($method === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("
            SELECT p.*, pr.name AS product_name 
            FROM purchases p 
            JOIN products pr ON pr.id = p.product_id 
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $purchase = $stmt->fetch();
        if (!$purchase) {
            errorResponse('Compra no encontrada', 404);
        }
        jsonResponse($purchase);
    } else {
        $stmt = $pdo->query("
            SELECT p.*, pr.name AS product_name 
            FROM purchases p 
            JOIN products pr ON pr.id = p.product_id 
            ORDER BY p.id DESC
        ");
        $purchases = $stmt->fetchAll();
        jsonResponse($purchases);
    }
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

// ============================================
// PUT /api/purchases/:id - Actualizar compra
// ============================================
if ($method === 'PUT') {
    $id = (int)($_GET['id'] ?? 0);
    $input = getJsonInput();
    
    $quantity = (int)($input['quantity'] ?? 0);
    $purchasePrice = (float)($input['purchase_price'] ?? 0);
    $supplier = $input['supplier'] ?? '';
    $shippingCost = (float)($input['shipping_cost'] ?? 0);
    $purchaseDate = $input['purchase_date'] ?? date('Y-m-d');
    
    $totalInvested = $quantity * $purchasePrice + $shippingCost;
    
    $stmt = $pdo->prepare("SELECT p.*, pr.stock as current_stock FROM purchases p JOIN products pr ON pr.id = p.product_id WHERE p.id = ?");
    $stmt->execute([$id]);
    $purchase = $stmt->fetch();
    
    if (!$purchase) {
        errorResponse('Compra no encontrada', 404);
    }
    
    $stmt = $pdo->prepare("
        UPDATE purchases SET 
        quantity = ?, purchase_price = ?, supplier = ?, shipping_cost = ?, 
        purchase_date = ?, total_invested = ?
        WHERE id = ?
    ");
    $stmt->execute([$quantity, $purchasePrice, $supplier, $shippingCost, $purchaseDate, $totalInvested, $id]);
    
    // Actualizar stock del producto
    $stockDifference = $quantity - $purchase['quantity'];
    $newStock = (int)$purchase['current_stock'] + $stockDifference;
    $status = getProductStatus($newStock);
    $stmt = $pdo->prepare("UPDATE products SET stock = ?, status = ?, purchase_price = ? WHERE id = ?");
    $stmt->execute([$newStock, $status, $purchasePrice, $purchase['product_id']]);
    
    successResponse();
}

// ============================================
// DELETE /api/purchases/:id - Eliminar compra
// ============================================
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT * FROM purchases WHERE id = ?");
    $stmt->execute([$id]);
    $purchase = $stmt->fetch();
    
    if (!$purchase) {
        errorResponse('Compra no encontrada', 404);
    }
    
    $stmt = $pdo->prepare("DELETE FROM purchases WHERE id = ?");
    $stmt->execute([$id]);
    
    successResponse();
}

errorResponse('Método no permitido', 405);
