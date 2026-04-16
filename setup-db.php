<?php
/**
 * Instalador simple de base de datos
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'yume_negocio';

echo "<!DOCTYPE html>\n<html><head><meta charset='UTF-8'><title>Instalador BD</title>";
echo "<style>
body { font-family: Arial, sans-serif; max-width: 700px; margin: 40px auto; padding: 20px; background: #667eea; }
.box { background: white; padding: 20px; border-radius: 8px; margin: 10px 0; }
.ok { background: #d4edda; color: #155724; padding: 10px; margin: 5px 0; border-radius: 4px; }
.err { background: #f8d7da; color: #721c24; padding: 10px; margin: 5px 0; border-radius: 4px; }
.btn { display: inline-block; background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin-top: 15px; }
</style></head><body>";

echo "<h1>Instalador de Base de Datos</h1>";

// Paso 1: Conectar a MySQL
echo "<div class='box'><h3>Paso 1: Conexión a MySQL</h3>";
try {
    $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<div class='ok'>✅ Conectado a MySQL</div>";
} catch (Exception $e) {
    echo "<div class='err'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<p>Edita este archivo y verifica usuario/contraseña</p>";
    echo "</div></body></html>";
    exit;
}

// Paso 2: Crear BD
echo "<h3>Paso 2: Crear base de datos</h3>";
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<div class='ok'>✅ Base de datos '$db_name' creada</div>";
} catch (Exception $e) {
    echo "<div class='err'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "</div></body></html>";
    exit;
}

// Paso 3: Conectar a la BD
echo "<h3>Paso 3: Conectar a $db_name</h3>";
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<div class='ok'>✅ Conectado a '$db_name'</div>";
} catch (Exception $e) {
    echo "<div class='err'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "</div></body></html>";
    exit;
}

// Paso 4: Leer SQL
echo "<h3>Paso 4: Importar esquema</h3>";
$sqlFile = __DIR__ . '/config/schema.sql';
if (!file_exists($sqlFile)) {
    echo "<div class='err'>❌ No existe config/schema.sql</div>";
    echo "</div></body></html>";
    exit;
}

$sql = file_get_contents($sqlFile);

// Ejecutar sentencias
$lines = array_filter(array_map('trim', explode(';', $sql)));
$ok = 0;
$err = 0;

foreach ($lines as $line) {
    if (empty($line) || $line[0] === '-' || strpos($line, 'SET') === 0) {
        continue;
    }
    try {
        $pdo->exec($line);
        $ok++;
    } catch (Exception $e) {
        $err++;
        echo "<div class='err'>⚠️ " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

echo "<div class='ok'>✅ $ok sentencias ejecutadas, $err errores</div>";

// Paso 5: Verificar
echo "<h3>Paso 5: Verificar tablas</h3>";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "<p>Tablas creadas: " . count($tables) . "</p>";
foreach ($tables as $t) {
    echo "<div class='ok'>✅ $t</div>";
}

echo "<div class='box'><h2>¡Instalación Completa!</h2>";
echo "<a href='http://localhost/yume-main' class='btn'>Ir al Sistema</a>";
echo "<p>Después de usar, elimina setup-db.php por seguridad.</p>";
echo "</div></body></html>";
