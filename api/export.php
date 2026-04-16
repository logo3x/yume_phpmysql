<?php
/**
 * API de Exportación e Importación
 */

require_once __DIR__ . '/helpers.php';
setCorsHeaders();
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getPDO();

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

// Plantillas de ejemplo para importar
$templates = [
    'products' => [
        'headers' => ['code', 'name', 'category', 'stock', 'purchase_price', 'sale_price'],
        'example' => [
            ['PROD001', 'Nombre del producto', 'Categoría', '10', '10000', '15000'],
            ['PROD002', 'Otro producto', 'Categoría B', '5', '25000', '35000'],
        ]
    ],
    'clients' => [
        'headers' => ['name', 'phone', 'address', 'city'],
        'example' => [
            ['Juan Pérez', '3001234567', 'Calle 123 #45-67', 'Bogotá'],
            ['María López', '3007654321', 'Carrera 5 #12-34', 'Medellín'],
        ]
    ],
    'sales' => [
        'headers' => ['sale_date', 'product_id', 'quantity', 'sale_price', 'client_id', 'payment_method', 'total_amount', 'profit'],
        'example' => [
            ['2024-01-15', '1', '2', '15000', '', 'Efectivo', '30000', '5000'],
        ]
    ],
    'purchases' => [
        'headers' => ['product_id', 'quantity', 'purchase_price', 'supplier', 'shipping_cost', 'purchase_date', 'total_invested'],
        'example' => [
            ['1', '10', '10000', 'Proveedor ABC', '5000', '2024-01-15', '105000'],
        ]
    ],
];

// ============================================
// GET /api/export/template/:type - Descargar plantilla CSV
// ============================================
if ($method === 'GET' && isset($_GET['template'])) {
    $type = $_GET['template'] ?? '';
    
    if (!isset($templates[$type])) {
        errorResponse('Plantilla no disponible para: ' . $type, 400);
    }
    
    $t = $templates[$type];
    $lines = [];
    $lines[] = implode(',', $t['headers']);
    
    foreach ($t['example'] as $row) {
        $line = [];
        foreach ($row as $val) {
            if (strpos($val, ',') !== false || strpos($val, '"') !== false) {
                $val = '"' . str_replace('"', '""', $val) . '"';
            }
            $line[] = $val;
        }
        $lines[] = implode(',', $line);
    }
    
    $csv = "\xef\xbb\xbf" . implode("\n", $lines);
    $filename = "plantilla_{$type}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    echo $csv;
    exit;
}

// ============================================
// GET /api/export/:type - Exportar a CSV o JSON
// ============================================
if ($method === 'GET') {
    $type = $_GET['type'] ?? '';
    
    // Remove format suffix if present (e.g., "products_json" -> "products")
    $format = 'csv';
    if (preg_match('#^(.+?)_(csv|json)$#', $type, $matches)) {
        $type = $matches[1];
        $format = $matches[2];
    }
    
    if (!isset($tables[$type])) {
        errorResponse('Tipo invalido', 400);
    }
    
    $t = $tables[$type];
    $stmt = $pdo->query($t['query']);
    $rows = $stmt->fetchAll();
    
    if ($format === 'json') {
        $filename = "{$type}_" . date('Y-m-d') . ".json";
        header('Content-Type: application/json; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        echo json_encode(['data' => $rows, 'exported_at' => date('Y-m-d H:i:s')], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        $csv = "\xef\xbb\xbf" . toCsv($t['headers'], $rows);
        $filename = "{$type}_" . date('Y-m-d') . ".csv";
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        echo $csv;
    }
    exit;
}

// ============================================
// POST /api/import/:type - Importar desde JSON
// ============================================
if ($method === 'POST') {
    $type = $_GET['type'] ?? '';
    $input = getJsonInput();
    $data = $input['data'] ?? [];
    
    if (!is_array($data) || empty($data)) {
        errorResponse('Datos invalidos o vacios', 400);
    }
    
    $imported = 0;
    $errors = [];
    
    try {
        foreach ($data as $row) {
            try {
                if ($type === 'products') {
                    $stmt = $pdo->prepare("SELECT id FROM products WHERE code = ?");
                    $stmt->execute([$row['code'] ?? '']);
                    
                    if ($stmt->fetch()) {
                        $stmt = $pdo->prepare("
                            UPDATE products SET name = ?, category = ?, stock = ?, purchase_price = ?, sale_price = ? 
                            WHERE code = ?
                        ");
                        $stmt->execute([
                            $row['name'] ?? '', $row['category'] ?? '', $row['stock'] ?? 0, 
                            $row['purchase_price'] ?? 0, $row['sale_price'] ?? 0, $row['code']
                        ]);
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO products (code, name, category, stock, purchase_price, sale_price, status, entry_date) 
                            VALUES (?, ?, ?, ?, ?, ?, 'Disponible', CURDATE())
                        ");
                        $stmt->execute([
                            $row['code'] ?? '', $row['name'] ?? '', $row['category'] ?? '', 
                            $row['stock'] ?? 0, $row['purchase_price'] ?? 0, $row['sale_price'] ?? 0
                        ]);
                    }
                    $imported++;
                } elseif ($type === 'clients') {
                    $stmt = $pdo->prepare("INSERT INTO clients (name, phone, address, city) VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $row['name'] ?? '', $row['phone'] ?? '', $row['address'] ?? '', $row['city'] ?? ''
                    ]);
                    $imported++;
                } elseif ($type === 'sales') {
                    $stmt = $pdo->prepare("INSERT INTO sales (sale_date, product_id, quantity, sale_price, client_id, payment_method, total_amount, profit) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $row['sale_date'] ?? date('Y-m-d'), $row['product_id'] ?? 0, 
                        $row['quantity'] ?? 1, $row['sale_price'] ?? 0, $row['client_id'] ?? null,
                        $row['payment_method'] ?? 'Efectivo', $row['total_amount'] ?? 0, $row['profit'] ?? 0
                    ]);
                    $imported++;
                } elseif ($type === 'purchases') {
                    $stmt = $pdo->prepare("INSERT INTO purchases (product_id, quantity, purchase_price, supplier, shipping_cost, purchase_date, total_invested) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $row['product_id'] ?? 0, $row['quantity'] ?? 1, $row['purchase_price'] ?? 0,
                        $row['supplier'] ?? '', $row['shipping_cost'] ?? 0, 
                        $row['purchase_date'] ?? date('Y-m-d'), $row['total_invested'] ?? 0
                    ]);
                    $imported++;
                }
            } catch (Exception $e) {
                $errors[] = "Fila: " . json_encode($row) . " - " . $e->getMessage();
            }
        }
    } catch (Exception $e) {
        errorResponse('Error en importacion: ' . $e->getMessage(), 500);
    }
    
    jsonResponse([
        'ok' => true, 
        'imported' => $imported, 
        'errors' => $errors,
        'total' => count($data)
    ]);
}

errorResponse('Método no permitido', 405);
