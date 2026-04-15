<?php
/**
 * API de Exportación e Importación
 */

require_once __DIR__ . '/helpers.php';
setCorsHeaders();
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getPDO();

// ============================================
// GET /api/export/:type - Exportar a CSV
// ============================================
if ($method === 'GET') {
    $type = $_GET['type'] ?? '';
    
    $tables = [
        'products' => [
            'headers' => ['id', 'code', 'name', 'category', 'description', 'features', 'stock', 'status', 'entry_date', 'supplier', 'purchase_price', 'extra_costs', 'total_real_cost', 'margin_percent', 'sale_price'],
            'query' => 'SELECT * FROM products ORDER BY id'
        ],
        'clients' => [
            'headers' => ['id', 'name', 'phone', 'address', 'city', 'created_at'],
            'query' => 'SELECT * FROM clients ORDER BY id'
        ],
        'sales' => [
            'headers' => ['id', 'sale_date', 'product_id', 'quantity', 'sale_price', 'client_id', 'payment_method', 'includes_shipping', 'shipping_value', 'total_amount', 'total_cost', 'profit'],
            'query' => 'SELECT * FROM sales ORDER BY id'
        ],
        'purchases' => [
            'headers' => ['id', 'product_id', 'quantity', 'purchase_price', 'supplier', 'shipping_cost', 'purchase_date', 'total_invested'],
            'query' => 'SELECT * FROM purchases ORDER BY id'
        ],
        'shipments' => [
            'headers' => ['id', 'client_name', 'client_address', 'city', 'shipping_value', 'transport_company', 'status', 'created_at'],
            'query' => 'SELECT * FROM shipments ORDER BY id'
        ],
        'cash_movements' => [
            'headers' => ['id', 'movement_date', 'type', 'category', 'amount', 'notes'],
            'query' => 'SELECT * FROM cash_movements ORDER BY id'
        ]
    ];
    
    if (!isset($tables[$type])) {
        errorResponse('Tipo invalido', 400);
    }
    
    $t = $tables[$type];
    $stmt = $pdo->query($t['query']);
    $rows = $stmt->fetchAll();
    
    $csv = "\xef\xbb\xbf" . toCsv($t['headers'], $rows);
    
    $filename = "{$type}_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    echo $csv;
    exit;
}

// ============================================
// POST /api/import/:type - Importar desde CSV/JSON
// ============================================
if ($method === 'POST') {
    $type = $_GET['type'] ?? '';
    $input = getJsonInput();
    $data = $input['data'] ?? [];
    
    if (!is_array($data)) {
        errorResponse('Datos invalidos', 400);
    }
    
    $imported = 0;
    
    try {
        foreach ($data as $row) {
            if ($type === 'products') {
                $stmt = $pdo->prepare("SELECT id FROM products WHERE code = ?");
                $stmt->execute([$row['code'] ?? '']);
                
                if ($stmt->fetch()) {
                    $stmt = $pdo->prepare("
                        UPDATE products SET name = ?, category = ?, stock = ?, purchase_price = ?, sale_price = ? 
                        WHERE code = ?
                    ");
                    $stmt->execute([
                        $row['name'], $row['category'] ?? '', $row['stock'] ?? 0, 
                        $row['purchase_price'] ?? 0, $row['sale_price'] ?? 0, $row['code']
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO products (code, name, category, stock, purchase_price, sale_price) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $row['code'], $row['name'], $row['category'] ?? '', 
                        $row['stock'] ?? 0, $row['purchase_price'] ?? 0, $row['sale_price'] ?? 0
                    ]);
                }
                $imported++;
            } elseif ($type === 'clients') {
                $stmt = $pdo->prepare("INSERT INTO clients (name, phone, address, city) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $row['name'], $row['phone'] ?? '', $row['address'] ?? '', $row['city'] ?? ''
                ]);
                $imported++;
            }
        }
    } catch (Exception $e) {
        errorResponse('Error en importacion: ' . $e->getMessage(), 500);
    }
    
    jsonResponse(['ok' => true, 'imported' => $imported]);
}

errorResponse('Método no permitido', 405);
