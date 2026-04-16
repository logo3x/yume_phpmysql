# Checklist de Verificación Post-Migración

## ✅ Instalación

- [ ] WAMP/XAMPP instalado y ejecutándose
- [ ] Apache con mod_rewrite habilitado
- [ ] MySQL/MariaDB ejecutándose
- [ ] Base de datos `yume_negocio` creada
- [ ] Esquema importado correctamente (todas las tablas existen)
- [ ] Archivo `config/database.php` configurado con credenciales correctas

## ✅ Verificación de Archivos

### Estructura de carpetas:
- [ ] Carpeta `api/` existe con todos los archivos PHP
- [ ] Carpeta `config/` existe con `database.php` y `schema.sql`
- [ ] Carpeta `public/` existe con `index.html`, `app.js`, `styles.css`
- [ ] Carpeta `uploads/` existe y está vacía (excepto .gitkeep)
- [ ] Carpeta `backups/` existe y está vacía (excepto .gitkeep)
- [ ] Archivo `.htaccess` en la raíz
- [ ] Archivo `index.php` en la raíz
- [ ] Archivo `instalar.bat` (opcional)

### Archivos de la API (todos deben existir):
- [ ] `api/index.php` - Enrutador principal
- [ ] `api/auth.php` - Autenticación
- [ ] `api/products.php` - Productos
- [ ] `api/clients.php` - Clientes
- [ ] `api/purchases.php` - Compras
- [ ] `api/sales.php` - Ventas
- [ ] `api/shipments.php` - Envíos
- [ ] `api/cash-movements.php` - Movimientos de caja
- [ ] `api/cash-summary.php` - Resumen de caja
- [ ] `api/reports.php` - Reportes
- [ ] `api/settings.php` - Configuración
- [ ] `api/admin.php` - Administración
- [ ] `api/backups.php` - Backups
- [ ] `api/export.php` - Export/Import
- [ ] `api/helpers.php` - Funciones auxiliares
- [ ] `api/health.php` - Health check

## ✅ Verificación de Base de Datos

Ejecuta en phpMyAdmin o MySQL:

```sql
USE yume_negocio;
SHOW TABLES;
```

Debes ver estas tablas:
- [ ] settings
- [ ] modules
- [ ] roles
- [ ] users
- [ ] user_sessions
- [ ] clients
- [ ] products
- [ ] purchases
- [ ] sales
- [ ] shipments
- [ ] cash_movements

Verifica que la tabla `settings` tenga un registro:
```sql
SELECT * FROM settings;
```

Debes ver: id=1, default_margin_percent=30.00, initial_investment=0.00

Verifica que la tabla `roles` tenga 3 registros:
```sql
SELECT * FROM roles;
```

Debes ver: Administrador, Gerente, Vendedor

## ✅ Verificación de Apache

- [ ] mod_rewrite habilitado en httpd.conf
- [ ] AllowOverride All configurado en el directorio
- [ ] Apache reiniciado después de los cambios

## ✅ Pruebas de Funcionalidad

### 1. Acceder al sistema
- [ ] Abre el navegador y ve a: `http://localhost/yume-main`
- [ ] Debes ver el formulario de bootstrap (crear primer usuario)

### 2. Crear usuario administrador
- [ ] Ingresa un nombre de usuario
- [ ] Ingresa una contraseña (mínimo 6 caracteres)
- [ ] Haz clic en "Crear Cuenta"
- [ ] Debes ver mensaje de éxito

### 3. Iniciar sesión
- [ ] Haz clic en "Cerrar sesión" (si estás logueado)
- [ ] Vuelve a entrar
- [ ] Debes ver el formulario de login
- [ ] Ingresa tus credenciales
- [ ] Debes entrar al sistema

### 4. Probar módulos
- [ ] Ve a la pestaña "Clientes"
- [ ] Crea un cliente de prueba
- [ ] Ve a la pestaña "Inventario"
- [ ] Crea un producto de prueba
- [ ] Ve a la pestaña "Compras"
- [ ] Registra una compra de prueba
- [ ] Ve a la pestaña "Ventas"
- [ ] Registra una venta de prueba
- [ ] Verifica que el stock se actualizó
- [ ] Ve a la pestaña "Caja"
- [ ] Verifica los movimientos de caja
- [ ] Ve a la pestaña "Reportes"
- [ ] Verifica que las gráficas muestran datos

### 5. Probar uploads
- [ ] Crea un producto con foto
- [ ] Verifica que la foto se guardó en `uploads/`
- [ ] Verifica que la foto se muestra en el producto

### 6. Probar backups
- [ ] Ve a la pestaña "Caja"
- [ ] Haz clic en "Crear Respaldo Ahora"
- [ ] Verifica que se creó un archivo en `backups/`

### 7. Probar exportación
- [ ] Ve a cualquier módulo con datos
- [ ] Haz clic en "Exportar CSV"
- [ ] Verifica que se descargó el archivo

### 8. Probar responsive
- [ ] Reduce el tamaño del navegador
- [ ] Verifica que el diseño se adapta correctamente

## ✅ Verificación de APIs

Puedes probar las APIs directamente:

### Health check:
```
GET http://localhost/yume-main/api/health
```
Debes ver: `{"status":"ok","db":"connected","time":"..."}`

### Verificar productos (vacío):
```
GET http://localhost/yume-main/api/products
```
Debes ver: `[]`

### Verificar configuración:
```
GET http://localhost/yume-main/api/settings
```
Debes ver: `{"id":1,"default_margin_percent":30,"initial_investment":0}`

## ✅ Errores Comunes a Verificar

### Si ves un error 500:
- [ ] Revisa los logs de Apache en `c:\wamp64\logs\error.log`
- [ ] Activa display_errors en php.ini temporalmente
- [ ] Verifica que todas las tablas existen en MySQL

### Si ves un error 404:
- [ ] Verifica que mod_rewrite está habilitado
- [ ] Verifica que `.htaccess` está en la raíz del proyecto
- [ ] Reinicia Apache

### Si las APIs no responden:
- [ ] Abre la consola del navegador (F12)
- [ ] Revisa las solicitudes de red (Network tab)
- [ ] Verifica que las URLs comiencen con `/api/`

### Si las imágenes no se suben:
- [ ] Verifica que la carpeta `uploads/` tenga permisos de escritura
- [ ] Verifica que `php.ini` permita uploads (`file_uploads = On`)
- [ ] Verifica `upload_max_filesize` y `post_max_size`

## ✅ Rendimiento

- [ ] Las consultas son rápidas (menos de 1 segundo)
- [ ] Las gráficas cargan correctamente
- [ ] No hay errores en la consola del navegador
- [ ] Las sesiones duran 24 horas

## 🎉 Si todo está marcado...

¡FELICIDADES! Tu migración de Node.js/PostgreSQL a PHP/MySQL fue exitosa.

El sistema está listo para usar con todas las funcionalidades originales.

---

## Notas Adicionales

- **Base de datos:** Comienza completamente vacía (solo con configuración y roles)
- **Primer usuario:** Debes crearlo al entrar por primera vez
- **Seguridad:** Las contraseñas usan PBKDF2-SHA512 con salt aleatorio
- **Sesiones:** Duran 24 horas y se almacenan en la base de datos
- **Backups:** Se guardan en formato SQL en la carpeta `backups/`

---

**Documentación creada:** Abril 2026
