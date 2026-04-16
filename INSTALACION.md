# Guía de Instalación Rápida - Control de Negocio PHP/MySQL

## Paso 1: Requisitos
Asegúrate de tener instalado:
- **WAMP** (Windows), **XAMPP** o **LAMP** (Linux) con:
  - PHP 7.4 o superior
  - MySQL 5.7 o superior / MariaDB 10.3+
  - Apache con mod_rewrite habilitado

## Paso 2: Copiar el proyecto
El proyecto ya está en: `c:\wamp64\www\yume-main`

## Paso 3: Crear la base de datos

### Opción A: Desde phpMyAdmin
1. Abre phpMyAdmin: `http://localhost/phpmyadmin`
2. Crea una nueva base de datos llamada: `yume_negocio`
3. Collation: `utf8mb4_unicode_ci`
4. Ve a la pestaña "Importar"
5. Selecciona el archivo: `config/schema.sql`
6. Haz clic en "Continuar"

### Opción B: Desde línea de comandos
```bash
cd c:\wamp64\www\yume-main
mysql -u root -p
```

En MySQL:
```sql
CREATE DATABASE yume_negocio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE yume_negocio;
SOURCE config/schema.sql;
EXIT;
```

## Paso 4: Configurar la conexión
Edita el archivo `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'yume_negocio');
define('DB_USER', 'root');      // Tu usuario de MySQL
define('DB_PASS', '');           // Tu contraseña de MySQL
```

**Nota:** En WAMP, generalmente el usuario es `root` y la contraseña está vacía.

## Paso 5: Configurar Apache

### Habilitar mod_rewrite
1. Abre `c:\wamp64\bin\apache\apacheX.X.XX\conf\httpd.conf`
2. Busca: `#LoadModule rewrite_module modules/mod_rewrite.so`
3. Quita el `#` del inicio
4. Busca `AllowOverride None` y cámbialo a `AllowOverride All`

### Reiniciar Apache
Haz clic en el ícono de WAMP → "Restart All Services"

## Paso 6: Permisos de carpetas
Asegúrate de que estas carpetas tengan permisos de escritura:
- `uploads/`
- `backups/`

En Windows generalmente no hay problema. En Linux:
```bash
chmod 755 uploads backups
chown www-data:www-data uploads backups
```

## Paso 7: Acceder al sistema
Abre tu navegador y ve a:
```
http://localhost/yume-main
```

## Paso 8: Crear usuario administrador
1. Al entrar por primera vez, verás un formulario de registro
2. Crea tu usuario administrador con una contraseña (mínimo 6 caracteres)
3. ¡Listo! Ya puedes usar el sistema

## Solución de Problemas

### Error: "Database unavailable"
- Verifica que la base de datos esté creada
- Revisa las credenciales en `config/database.php`
- Asegúrate de que MySQL esté ejecutándose

### Error 404 en las APIs
- Verifica que mod_rewrite esté habilitado en Apache
- Revisa que el archivo `.htaccess` esté en la raíz del proyecto
- Reinicia Apache

### Las imágenes no se suben
- Verifica que la carpeta `uploads/` exista y tenga permisos de escritura
- En Windows, generalmente no hay problema de permisos

### Error de CORS o autenticación
- Limpia las cookies del navegador
- Reinicia el navegador e intenta de nuevo

## Backups
Para crear respaldos manuales:
1. Ve a la pestaña **CAJA**
2. Haz clic en "Crear Respaldo Ahora"
3. El respaldo se guarda en `backups/` en formato SQL

Para restaurar:
```bash
mysql -u root -p yume_negocio < backups/nombredelarchivo.sql
```

## Funcionalidades
✅ Clientes (CRUD completo)
✅ Inventario de productos con fotos
✅ Compras a proveedores
✅ Ventas con cálculo de ganancias
✅ Envíos y transporte
✅ Caja y movimientos de dinero
✅ Reportes y gráficas
✅ Administración de usuarios y roles
✅ Exportación a CSV
✅ Importación de datos
✅ Backups de base de datos
✅ Autenticación segura con sesiones

## Estructura de URLs
```
http://localhost/yume-main/                    → Frontend
http://localhost/yume-main/api/products        → API Productos
http://localhost/yume-main/api/clients         → API Clientes
http://localhost/yume-main/api/sales           → API Ventas
http://localhost/yume-main/api/backups         → API Backups
```

## Soporte
Si tienes problemas, revisa:
1. Los logs de Apache en `c:\wamp64\logs\`
2. Los logs de MySQL en phpMyAdmin
3. La consola del navegador (F12) para errores JavaScript

---
**¡Listo! Tu sistema de control de negocio está funcionando con PHP y MySQL.**
