<?php
/**
 * API de Administración (usuarios y roles)
 */

require_once __DIR__ . '/helpers.php';
setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);
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
// GET /api/admin/users/:id - Obtener un usuario
// ============================================
if ($method === 'GET' && $action === 'users') {
    if ($id > 0) {
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.role_id, u.is_active, u.created_at, r.name as role_name 
            FROM users u 
            LEFT JOIN roles r ON r.id = u.role_id 
            WHERE u.id = ?
        ");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) {
            errorResponse('Usuario no encontrado', 404);
        }
        jsonResponse($user);
    } else {
        $stmt = $pdo->query("
            SELECT u.id, u.username, u.role_id, u.is_active, u.created_at, r.name as role_name 
            FROM users u 
            LEFT JOIN roles r ON r.id = u.role_id 
            ORDER BY u.id DESC
        ");
        $users = $stmt->fetchAll();
        jsonResponse($users);
    }
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
// PUT /api/admin/users/:id - Actualizar usuario
// ============================================
if ($method === 'PUT' && $action === 'users') {
    $input = getJsonInput();
    $targetUserId = $id > 0 ? $id : (int)($input['id'] ?? 0);
    
    if ($targetUserId === 0) {
        errorResponse('ID de usuario requerido', 400);
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$targetUserId]);
    if (!$stmt->fetch()) {
        errorResponse('Usuario no encontrado', 404);
    }
    
    $username = $input['username'] ?? null;
    $roleId = $input['role_id'] ?? null;
    $isActive = isset($input['is_active']) ? (bool)$input['is_active'] : null;
    
    $updates = [];
    $params = [];
    
    if ($username !== null) {
        $updates[] = 'username = ?';
        $params[] = $username;
    }
    if ($roleId !== null) {
        $updates[] = 'role_id = ?';
        $params[] = $roleId;
    }
    if ($isActive !== null) {
        $updates[] = 'is_active = ?';
        $params[] = $isActive ? 1 : 0;
    }
    
    if (!empty($input['password']) && strlen($input['password']) >= 6) {
        $salt = generateSalt();
        $hash = hashPassword($input['password'], $salt);
        $updates[] = 'password_hash = ?, salt = ?';
        $params[] = $hash;
        $params[] = $salt;
    }
    
    if (!empty($updates)) {
        $params[] = $targetUserId;
        $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($params);
    }
    
    successResponse();
}

// ============================================
// DELETE /api/admin/users/:id - Eliminar usuario
// ============================================
if ($method === 'DELETE' && $action === 'users') {
    $targetUserId = $id > 0 ? $id : (int)(getJsonInput()['id'] ?? 0);
    
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

// ============================================
// GET /api/admin/permissions/:userId - Obtener permisos
// ============================================
if ($method === 'GET' && $action === 'permissions') {
    if ($id === 0) {
        errorResponse('ID de usuario requerido', 400);
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        errorResponse('Usuario no encontrado', 404);
    }
    
    // Módulos disponibles
    $modules = [
        ['key' => 'clientes', 'name' => 'Clientes', 'icon' => '👥'],
        ['key' => 'inventario', 'name' => 'Inventario', 'icon' => '📦'],
        ['key' => 'compras', 'name' => 'Compras', 'icon' => '🛒'],
        ['key' => 'ventas', 'name' => 'Ventas', 'icon' => '💰'],
        ['key' => 'envios', 'name' => 'Envíos', 'icon' => '🚚'],
        ['key' => 'caja', 'name' => 'Caja', 'icon' => '💼'],
        ['key' => 'reportes', 'name' => 'Reportes', 'icon' => '📊'],
    ];
    
    // Obtener permisos del usuario (si la tabla existe)
    $perms = [];
    try {
        $stmt = $pdo->prepare("SELECT module_key, can_view, can_create, can_edit, can_delete FROM user_permissions WHERE user_id = ?");
        $stmt->execute([$id]);
        while ($row = $stmt->fetch()) {
            $perms[$row['module_key']] = $row;
        }
    } catch (Exception $e) {
        // Tabla no existe, usar permisos por defecto
    }
    
    // Combinar módulos con permisos
    $result = [];
    foreach ($modules as $mod) {
        $p = $perms[$mod['key']] ?? null;
        $result[] = [
            'key' => $mod['key'],
            'name' => $mod['name'],
            'icon' => $mod['icon'],
            'can_view' => $p ? (bool)$p['can_view'] : true,
            'can_create' => $p ? (bool)$p['can_create'] : true,
            'can_edit' => $p ? (bool)$p['can_edit'] : true,
            'can_delete' => $p ? (bool)$p['can_delete'] : true,
        ];
    }
    
    jsonResponse($result);
}

// ============================================
// PUT /api/admin/permissions/:userId - Guardar permisos
// ============================================
if ($method === 'PUT' && $action === 'permissions') {
    if ($id === 0) {
        errorResponse('ID de usuario requerido', 400);
    }
    
    $input = getJsonInput();
    $permissions = $input['permissions'] ?? [];
    
    try {
        // Eliminar permisos existentes
        $stmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $stmt->execute([$id]);
        
        // Insertar nuevos permisos
        foreach ($permissions as $perm) {
            $stmt = $pdo->prepare("
                INSERT INTO user_permissions (user_id, module_key, can_view, can_create, can_edit, can_delete) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $id,
                $perm['module_key'],
                $perm['can_view'] ? 1 : 0,
                $perm['can_create'] ? 1 : 0,
                $perm['can_edit'] ? 1 : 0,
                $perm['can_delete'] ? 1 : 0
            ]);
        }
    } catch (Exception $e) {
        // Ignorar si la tabla no existe
    }
    
    successResponse();
}

errorResponse('Ruta no encontrada', 404);
