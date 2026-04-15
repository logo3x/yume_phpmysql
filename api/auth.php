<?php
/**
 * API de Autenticación
 */

require_once __DIR__ . '/helpers.php';
setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ============================================
// GET /api/auth/status - Verificar estado de sesión
// ============================================
if ($method === 'GET' && $action === 'status') {
    $pdo = getPDO();
    
    // Verificar si hay usuarios
    $stmt = $pdo->query("SELECT COUNT(*) as c FROM users");
    $hasUsers = (int)$stmt->fetch()['c'] > 0;
    
    $token = getSessionToken();
    if (!$token) {
        jsonResponse(['authenticated' => false, 'hasUsers' => $hasUsers]);
    }
    
    $stmt = $pdo->prepare("
        SELECT us.user_id, us.expires_at, u.username, u.role_id, r.name as role_name, r.is_admin 
        FROM user_sessions us 
        JOIN users u ON u.id = us.user_id 
        LEFT JOIN roles r ON r.id = u.role_id 
        WHERE us.token = ?
    ");
    $stmt->execute([$token]);
    $session = $stmt->fetch();
    
    if (!$session || new DateTime($session['expires_at']) < new DateTime()) {
        jsonResponse(['authenticated' => false, 'hasUsers' => $hasUsers]);
    }
    
    jsonResponse([
        'authenticated' => true,
        'hasUsers' => $hasUsers,
        'username' => $session['username'],
        'userId' => (int)$session['user_id'],
        'role' => $session['role_name'],
        'isAdmin' => (int)$session['is_admin'] === 1
    ]);
}

// ============================================
// POST /api/auth/bootstrap - Crear primer usuario admin
// ============================================
if ($method === 'POST' && $action === 'bootstrap') {
    $pdo = getPDO();
    
    $stmt = $pdo->query("SELECT COUNT(*) as c FROM users");
    if ((int)$stmt->fetch()['c'] > 0) {
        errorResponse('Ya existe un usuario', 400);
    }
    
    $input = getJsonInput();
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    
    if (!$username || !$password || strlen($password) < 6) {
        errorResponse('Usuario y contraseña (minimo 6) son obligatorios', 400);
    }
    
    $salt = generateSalt();
    $hash = hashPassword($password, $salt);
    
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, salt, role_id) VALUES (?, ?, ?, 1)");
    $stmt->execute([$username, $hash, $salt]);
    
    successResponse();
}

// ============================================
// POST /api/auth/login - Iniciar sesión
// ============================================
if ($method === 'POST' && $action === 'login') {
    $pdo = getPDO();
    $input = getJsonInput();
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        errorResponse('Credenciales invalidas', 401);
    }
    
    $hash = hashPassword($password, $user['salt']);
    if ($hash !== $user['password_hash']) {
        errorResponse('Credenciales invalidas', 401);
    }
    
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 24 * 60 * 60);
    
    $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$user['id'], $token, $expiresAt]);
    
    setSessionCookie($token);
    
    jsonResponse(['ok' => true, 'username' => $user['username']]);
}

// ============================================
// POST /api/auth/logout - Cerrar sesión
// ============================================
if ($method === 'POST' && $action === 'logout') {
    $pdo = getPDO();
    $token = getSessionToken();
    
    if ($token) {
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE token = ?");
        $stmt->execute([$token]);
    }
    
    clearSessionCookie();
    
    successResponse();
}

errorResponse('Ruta no encontrada', 404);
