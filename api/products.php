<?php
/**
 * API de Productos
 */

require_once __DIR__ . '/helpers.php';
setCorsHeaders();
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getPDO();

// ============================================
// GET /api/products - Obtener todos los productos
// ============================================
if ($method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY id DESC");
    $products = $stmt->fetchAll();
    jsonResponse($products);
}

// ============================================
// POST /api/products - Crear producto
// ============================================
if ($method === 'POST') {
    $code = $_POST['code'] ?? '';
    $name = $_POST['name'] ?? '';
    $category = $_POST['category'] ?? '';
    $description = $_POST['description'] ?? '';
    $features = $_POST['features'] ?? '';
    $stock = (int)($_POST['stock'] ?? 0);
    $entryDate = $_POST['entry_date'] ?? date('Y-m-d');
    $supplier = $_POST['supplier'] ?? '';
    $purchasePrice = (float)($_POST['purchase_price'] ?? 0);
    $extraCosts = (float)($_POST['extra_costs'] ?? 0);
    $marginPercent = (float)($_POST['margin_percent'] ?? 30);
    
    $pricing = calcPricing($purchasePrice, $extraCosts, $marginPercent);
    $status = getProductStatus($stock);
    
    $photoPath = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $filename = time() . '-' . round(rand() . 1E9) . '.' . $ext;
        $targetPath = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
            $photoPath = '/uploads/' . $filename;
        }
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO products 
        (code, name, category, description, features, stock, status, entry_date, supplier, photo_path, purchase_price, extra_costs, total_real_cost, margin_percent, sale_price) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $code, $name, $category, $description, $features, $stock, $status, $entryDate, $supplier, 
        $photoPath, $purchasePrice, $extraCosts, $pricing['totalRealCost'], $marginPercent, $pricing['salePrice']
    ]);
    
    $newId = (int)$pdo->lastInsertId();
    jsonResponse(['id' => $newId]);
}

// ============================================
// PUT /api/products/:id - Actualizar producto
// ============================================
if ($method === 'PUT') {
    $putVars = parsePutInput();
    $id = (int)($putVars['id'] ?? $_GET['id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        errorResponse('Producto no encontrado', 404);
    }
    
    $code = $putVars['code'] ?? $product['code'];
    $name = $putVars['name'] ?? $product['name'];
    $category = $putVars['category'] ?? $product['category'];
    $description = $putVars['description'] ?? $product['description'];
    $features = $putVars['features'] ?? $product['features'];
    $stock = (int)($putVars['stock'] ?? $product['stock']);
    $entryDate = $putVars['entry_date'] ?? $product['entry_date'];
    $supplier = $putVars['supplier'] ?? $product['supplier'];
    $purchasePrice = (float)($putVars['purchase_price'] ?? $product['purchase_price']);
    $extraCosts = (float)($putVars['extra_costs'] ?? $product['extra_costs']);
    $marginPercent = (float)($putVars['margin_percent'] ?? $product['margin_percent']);
    
    $pricing = calcPricing($purchasePrice, $extraCosts, $marginPercent);
    $status = getProductStatus($stock);
    
    // Handle photo upload
    $photoPath = $product['photo_path'];
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $filename = time() . '-' . round(rand() * 1E9) . '.' . $ext;
        $targetPath = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
            $photoPath = '/uploads/' . $filename;
        }
    }
    
    $stmt = $pdo->prepare("
        UPDATE products SET 
        code = ?, name = ?, category = ?, description = ?, features = ?, 
        stock = ?, status = ?, entry_date = ?, supplier = ?, photo_path = ?, 
        purchase_price = ?, extra_costs = ?, total_real_cost = ?, margin_percent = ?, sale_price = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $code, $name, $category, $description, $features, $stock, $status, 
        $entryDate, $supplier, $photoPath, $purchasePrice, $extraCosts, 
        $pricing['totalRealCost'], $marginPercent, $pricing['salePrice'], $id
    ]);
    
    successResponse();
}

// ============================================
// DELETE /api/products/:id - Eliminar producto
// ============================================
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);
    
    successResponse();
}

errorResponse('Método no permitido', 405);
