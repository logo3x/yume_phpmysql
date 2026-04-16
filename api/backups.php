<?php
/**
 * API de Backups
 */

require_once __DIR__ . '/helpers.php';
setCorsHeaders();
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getPDO();

// ============================================
// GET /api/backups - Listar backups
// GET /api/backups/download - Descargar un backup
// ============================================
if ($method === 'GET') {
    // Descargar backup
    if (isset($_GET['file'])) {
        $filename = basename($_GET['file']);
        $backupDir = __DIR__ . '/../backups';
        $filepath = $backupDir . '/' . $filename;
        
        if (!file_exists($filepath)) {
            errorResponse('Archivo no encontrado', 404);
        }
        
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
    
    // Listar backups
    $backupDir = __DIR__ . '/../backups';
    
    if (!is_dir($backupDir)) {
        jsonResponse([]);
    }
    
    $files = glob($backupDir . '/negocio-*.sql');
    $backups = [];
    
    foreach ($files as $file) {
        $stat = stat($file);
        $backups[] = [
            'file' => basename($file),
            'size' => $stat['size'],
            'updated_at' => date('c', $stat['mtime'])
        ];
    }
    
    usort($backups, function($a, $b) {
        return strtotime($b['updated_at']) - strtotime($a['updated_at']);
    });
    
    jsonResponse($backups);
}

// ============================================
// POST /api/backups - Crear backup
// ============================================
if ($method === 'POST') {
    $input = getJsonInput();
    $reason = $input['reason'] ?? 'manual';
    
    $backupName = createBackup($reason);
    
    if ($backupName) {
        cleanOldBackups(20);
        jsonResponse(['ok' => true, 'file' => $backupName]);
    } else {
        errorResponse('Error al crear el respaldo', 500);
    }
}

errorResponse('Método no permitido', 405);
