<?php
/**
 * API de Administración (usuarios y roles)
 */

require_once __DIR__ . '/helpers.php';
setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$userId = requireAdmin();
$pdo = getPDO();

// ============================================
// GET /api/admin/roles - Obtener roles
// ============================================
if ($method === 'GET' && $action === 'roles') {
    $stmt = $pdo->query("SELECT * FROM roles ORDER BY id");
    $roles = $stmt->fetchAll();
    jsonResponse($roles);
}

// ============================================
// GET /api/admin/users - Obtener usuarios
// ============================================
if ($method === 'GET' && $action === 'users') {
    $stmt = $pdo->query("
        SELECT u.id, u.username, u.role_id, u.is_active, u.created_at, r.name as role_name 
        FROM users u 
        LEFT JOIN roles r ON r.id = u.role_id 
        ORDER BY u.id DESC
    ");
    $users = $stmt->fetchAll();
    jsonResponse($users);
}

// ============================================
// POST /api/admin/users - Crear usuario
// ============================================
if ($method === 'POST' && $action === 'users') {
    $input = getJsonInput();
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    $roleId = $input['role_id'] ?? 2;
    
    if (!$username || !$password || strlen($password) < 6) {
        errorResponse('Usuario y contraseña (minimo 6) son obligatorios', 400);
    }
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        errorResponse('El usuario ya existe', 400);
    }
    
    $salt = generateSalt();
    $hash = hashPassword($password, $salt);
    
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, salt, role_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $hash, $salt, $roleId]);
    
    $newId = (int)$pdo->lastInsertId();
    jsonResponse(['id' => $newId]);
}

// ============================================
// DELETE /api/admin/users/:id - Eliminar usuario
// ============================================
if ($method === 'DELETE' && $action === 'users') {
    $input = getJsonInput();
    $targetUserId = (int)($input['id'] ?? 0);
    
    if ($targetUserId === 1) {
        errorResponse('No se puede eliminar el usuario administrador principal', 400);
    }
    
    if ($targetUserId === $userId) {
        errorResponse('No puedes eliminarte a ti mismo', 400);
    }
    
    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
    $stmt->execute([$targetUserId]);
    
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$targetUserId]);
    
    successResponse();
}

errorResponse('Ruta no encontrada', 404);
