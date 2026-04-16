<?php
/**
 * Funciones auxiliares y middleware
 */

require_once __DIR__ . '/../config/database.php';

// ============================================
// CORS y headers
// ============================================
function setCorsHeaders() {
    $allowedOrigins = ['http://localhost:3000', 'https://yume.onrender.com'];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (in_array($origin, $allowedOrigins) || empty($origin)) {
        header("Access-Control-Allow-Origin: " . ($origin ?: '*'));
    } else {
        header("Access-Control-Allow-Origin: *");
    }
    
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// ============================================
// JSON responses
// ============================================
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function errorResponse($message, $statusCode = 400) {
    jsonResponse(['error' => $message], $statusCode);
}

function successResponse($data = []) {
    jsonResponse(array_merge(['ok' => true], $data));
}

// ============================================
// Input handling
// ============================================
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?: [];
}

function parsePutInput() {
    $input = file_get_contents('php://input');
    
    // Try JSON
    $json = json_decode($input, true);
    if ($json) {
        return $json;
    }
    
    // Try URL-encoded
    $data = [];
    parse_str($input, $data);
    return $data;
}

// ============================================
// Password hashing
// ============================================
function hashPassword($password, $salt) {
    return hash_pbkdf2('sha512', $password, $salt, 100000, 64);
}

function generateSalt() {
    return bin2hex(random_bytes(16));
}

// ============================================
// Cookie handling
// ============================================
function parseCookies() {
    return $_COOKIE;
}

function getSessionToken() {
    return $_COOKIE['session_token'] ?? null;
}

function setSessionCookie($token) {
    setcookie('session_token', $token, [
        'expires' => time() + 86400,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function clearSessionCookie() {
    setcookie('session_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// ============================================
// Authentication
// ============================================
function requireAuth() {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    // No requerir autenticación para rutas de auth
    if (strpos($path, '/api/auth/') !== false) {
        return;
    }
    
    $token = getSessionToken();
    if (!$token) {
        errorResponse('No autenticado', 401);
    }
    
    $pdo = getPDO();
    $stmt = $pdo->prepare("
        SELECT us.*, u.username, u.id as user_id 
        FROM user_sessions us 
        JOIN users u ON u.id = us.user_id 
        WHERE us.token = ?
    ");
    $stmt->execute([$token]);
    $session = $stmt->fetch();
    
    if (!$session) {
        errorResponse('Sesion invalida', 401);
    }
    
    if (new DateTime($session['expires_at']) < new DateTime()) {
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE token = ?");
        $stmt->execute([$token]);
        errorResponse('Sesion expirada', 401);
    }
    
    return [
        'id' => (int)$session['user_id'],
        'username' => $session['username']
    ];
}

function requireAdmin() {
    $token = getSessionToken();
    if (!$token) {
        errorResponse('No autenticado', 401);
    }
    
    $pdo = getPDO();
    $stmt = $pdo->prepare("
        SELECT u.role_id, r.is_admin, u.id as user_id 
        FROM user_sessions us 
        JOIN users u ON u.id = us.user_id 
        LEFT JOIN roles r ON r.id = u.role_id 
        WHERE us.token = ? AND us.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        errorResponse('Sesion invalida', 401);
    }
    
    if ($user['is_admin'] != 1) {
        errorResponse('Acceso denegado', 403);
    }
    
    return (int)$user['user_id'];
}

// ============================================
// Product helpers
// ============================================
function getProductStatus($stock) {
    return $stock > 0 ? 'Disponible' : 'Agotado';
}

function calcPricing($purchasePrice, $extraCosts, $marginPercent) {
    $totalRealCost = (float)$purchasePrice + (float)$extraCosts;
    $salePrice = $totalRealCost * (1 + (float)$marginPercent / 100);
    return [
        'totalRealCost' => $totalRealCost,
        'salePrice' => $salePrice
    ];
}

// ============================================
// CSV helpers
// ============================================
function escapeCsvVal($val) {
    if ($val === null || $val === '') return '';
    $str = (string)$val;
    if (strpos($str, ',') !== false || strpos($str, '"') !== false || strpos($str, "\n") !== false) {
        return '"' . str_replace('"', '""', $str) . '"';
    }
    return $str;
}

function toCsv($headers, $rows) {
    $lines = [implode(',', array_map('escapeCsvVal', $headers))];
    foreach ($rows as $row) {
        $values = [];
        foreach ($headers as $h) {
            $values[] = escapeCsvVal($row[$h] ?? '');
        }
        $lines[] = implode(',', $values);
    }
    return implode("\n", $lines);
}

// ============================================
// Backup helper
// ============================================
function createBackup($reason = 'manual') {
    $backupDir = __DIR__ . '/../backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $stamp = date('Y-m-d\TH-i-s');
    $name = "negocio-{$reason}-{$stamp}.sql";
    $target = $backupDir . '/' . $name;
    
    $pdo = getPDO();
    
    // Obtener todas las tablas
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    $sql = "-- Backup de Base de Datos\n";
    $sql .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    
    foreach ($tables as $table) {
        // Drop table
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        
        // Create table
        $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch();
        $sql .= $create['Create Table'] . ";\n\n";
        
        // Data
        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $cols = array_keys($rows[0]);
            $sql .= "INSERT INTO `{$table}` (`" . implode('`, `', $cols) . "`) VALUES\n";
            
            $values = [];
            foreach ($rows as $row) {
                $vals = array_map(function($v) use ($pdo) {
                    if ($v === null) return 'NULL';
                    return $pdo->quote($v);
                }, array_values($row));
                $values[] = '(' . implode(', ', $vals) . ')';
            }
            $sql .= implode(",\n", $values) . ";\n\n";
        }
    }
    
    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    
    if (file_put_contents($target, $sql)) {
        return $name;
    }
    
    return null;
}

function cleanOldBackups($maxBackups = 20) {
    $backupDir = __DIR__ . '/../backups';
    $files = glob($backupDir . '/negocio-*.sql');
    
    if (count($files) > $maxBackups) {
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        for ($i = $maxBackups; $i < count($files); $i++) {
            unlink($files[$i]);
        }
    }
}

// ============================================
// Error handling
// ============================================
function handleException($e) {
    $message = $e->getMessage();
    
    if (strpos($message, 'Database') !== false || strpos($message, 'PDO') !== false) {
        jsonResponse([
            'error' => 'Database unavailable',
            'detail' => $message
        ], 503);
    } else {
        jsonResponse([
            'error' => 'Server error',
            'detail' => $message
        ], 500);
    }
}

set_exception_handler(function($e) {
    handleException($e);
});

// ============================================
// Ensure user_permissions table exists
// ============================================
function ensureUserPermissionsTable() {
    $pdo = getPDO();
    try {
        $pdo->query("SELECT 1 FROM user_permissions LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                module_key VARCHAR(50) NOT NULL,
                can_view TINYINT(1) DEFAULT 1,
                can_create TINYINT(1) DEFAULT 1,
                can_edit TINYINT(1) DEFAULT 1,
                can_delete TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_module (user_id, module_key),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}
