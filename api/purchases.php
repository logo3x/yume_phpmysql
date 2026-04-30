<?php
/**
 * API de Compras
 */

require_once __DIR__ . '/helpers.php';
setCorsHeaders();
requireAuth();
extractPathParams();

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

// ============================================
// DELETE /api/purchases/:id - Eliminar compra (revierte stock y movimiento de caja)
// ============================================
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        errorResponse('ID invalido', 400);
    }

    $stmt = $pdo->prepare("
        SELECT p.*, pr.name AS product_name, pr.stock AS current_stock
        FROM purchases p
        JOIN products pr ON pr.id = p.product_id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $purchase = $stmt->fetch();

    if (!$purchase) {
        errorResponse('Compra no encontrada', 404);
    }

    $pdo->beginTransaction();
    try {
        // Revertir stock (descontar las unidades que la compra había sumado)
        $newStock = max(0, (int)$purchase['current_stock'] - (int)$purchase['quantity']);
        $status = getProductStatus($newStock);
        $stmt = $pdo->prepare("UPDATE products SET stock = ?, status = ? WHERE id = ?");
        $stmt->execute([$newStock, $status, (int)$purchase['product_id']]);

        // Eliminar el movimiento de caja asociado (match por fecha + monto + categoría + nota)
        $note = "Compra producto " . $purchase['product_name'];
        $stmt = $pdo->prepare("
            DELETE FROM cash_movements
            WHERE type = 'Egreso'
              AND category = 'Compra de productos'
              AND movement_date = ?
              AND ABS(amount - ?) < 0.01
              AND notes = ?
            LIMIT 1
        ");
        $stmt->execute([
            $purchase['purchase_date'],
            (float)$purchase['total_invested'],
            $note
        ]);

        // Eliminar la compra
        $stmt = $pdo->prepare("DELETE FROM purchases WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    successResponse();
}

errorResponse('Método no permitido', 405);
