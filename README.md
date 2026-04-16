# Control de Negocio (PHP + MySQL)

## Requisitos
- PHP 7.4 o superior
- MySQL 5.7 o superior / MariaDB 10.3+
- Servidor web: Apache con mod_rewrite o Nginx
- Extensiones PHP: PDO, PDO_MySQL, mbstring

## Instalación

### 1. Configurar la base de datos
1. Crea una base de datos MySQL:
```sql
CREATE DATABASE yume_negocio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Importa el esquema:
```bash
mysql -u root -p yume_negocio < config/schema.sql
```

### 2. Configurar la conexión a la base de datos
Edita `config/database.php` o define las variables de entorno:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'yume_negocio');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 3. Configurar Apache
Asegúrate de que `mod_rewrite` esté habilitado:
```bash
a2enmod rewrite
```

El archivo `.htaccess` ya está configurado para redirigir las solicitudes.

### 4. Permisos de carpetas
Asegúrate de que las carpetas `uploads/` y `backups/` tengan permisos de escritura:
```bash
chmod 755 uploads backups
```

### 5. Ejecutar
1. Inicia tu servidor web (Apache)
2. Abre: `http://localhost/yume-main`

## Estructura del proyecto
```
yume-main/
??? api/                 # Endpoints de la API PHP
?   ??? index.php       # Enrutador principal
?   ??? auth.php        # Autenticación
?   ??? products.php    # Gestión de productos
?   ??? sales.php       # Gestión de ventas
?   ??? purchases.php   # Gestión de compras
?   ??? clients.php     # Gestión de clientes
?   ??? shipments.php   # Gestión de envíos
?   ??? cash-*.php      # Gestión de caja
?   ??? reports.php     # Reportes y estadísticas
?   ??? admin.php       # Administración de usuarios
?   ??? backups.php     # Respaldos de BD
?   ??? export.php      # Exportación/Importación
?   ??? helpers.php     # Funciones auxiliares
??? config/             # Configuración
?   ??? database.php    # Conexión a BD
?   ??? schema.sql      # Esquema de BD
??? public/             # Frontend
?   ??? index.html      # Página principal
?   ??? app.js          # Lógica JavaScript
?   ??? styles.css      # Estilos
??? uploads/            # Fotos de productos
??? backups/            # Respaldos de BD
??? .htaccess           # Configuración Apache
```

## Seguridad
- Al entrar por primera vez, el sistema pedirá crear el usuario administrador inicial
- Luego se debe iniciar sesión para acceder a módulos y APIs
- La sesión dura 24 horas
- Las contraseñas se almacenan con PBKDF2-SHA512

## Backups
- Respaldo manual desde la pestaña **CAJA** con botón "Crear Respaldo Ahora"
- Se guardan en la carpeta `backups/` en formato SQL
- El sistema conserva los 20 respaldos más recientes

## Módulos
- ?? Clientes
- ?? Inventario
- ?? Compras (proveedores)
- ?? Ventas (descuenta stock y calcula ganancia)
- ?? Envíos
- ?? Caja (ingresos, egresos, dinero actual)
- ?? Reportes y gráficas
- ?? Administración (usuarios y roles)

## Base de datos
- MySQL/MariaDB con tablas InnoDB
- Fotos de producto: `uploads/`
- Respaldos: `backups/`

## Migración desde Node.js
Este proyecto fue migrado desde Node.js/SQLite a PHP/MySQL. Si vienes de la versión anterior:
1. Exporta tus datos desde la versión antigua (CSV)
2. Importa los datos usando la función de importación en la nueva versión
3. La base de datos nueva comienza vacía
