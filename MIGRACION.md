# Migración Completada: Node.js/PostgreSQL → PHP/MySQL

## Resumen de la Migración

**Fecha:** Abril 2026
**Estado:** ✅ COMPLETADA

---

## ¿Qué se hizo?

Se migró completamente el sistema de control de negocio desde **Node.js con PostgreSQL** a **PHP puro con MySQL**, manteniendo:
- ✅ El mismo frontend (HTML, CSS, JavaScript)
- ✅ Todas las funcionalidades originales
- ✅ El mismo diseño y experiencia de usuario
- ✅ Base de datos completamente vacía (lista para usar)

---

## Cambios Realizados

### 1. Backend
| Antes (Node.js) | Ahora (PHP) |
|----------------|------------|
| Express.js | PHP puro con PDO |
| PostgreSQL | MySQL 5.7+ / MariaDB |
| server.js | api/index.php (enrutador) |
| Middleware de Express | Funciones helper en PHP |
| Multer (uploads) | $_FILES de PHP |
| Crypto (passwords) | hash_pbkdf2() nativo |

### 2. Base de Datos
| Antes (PostgreSQL) | Ahora (MySQL) |
|-------------------|--------------|
| SERIAL | AUTO_INCREMENT |
| TIMESTAMP DEFAULT NOW() | TIMESTAMP DEFAULT CURRENT_TIMESTAMP |
| INTERVAL '6 days' | INTERVAL 6 DAY |
| DATE_TRUNC() | DATE_FORMAT() |
| CURDATE() | CURDATE() |
| COALESCE() | COALESCE() (igual) |
| CHECK constraints | CHECK constraints (MySQL 8.0+) |

### 3. Estructura del Proyecto

**Antes:**
```
yume-main/
├── server.js
├── package.json
├── negocio.db
├── public/
│   ├── index.html
│   ├── app.js
│   └── styles.css
└── backups/
```

**Ahora:**
```
yume-main/
├── api/                    # ✅ NUEVO
│   ├── index.php          # Enrutador principal
│   ├── auth.php           # Autenticación
│   ├── products.php       # Productos
│   ├── sales.php          # Ventas
│   ├── purchases.php      # Compras
│   ├── clients.php        # Clientes
│   ├── shipments.php      # Envíos
│   ├── cash-*.php         # Caja
│   ├── reports.php        # Reportes
│   ├── admin.php          # Administración
│   ├── backups.php        # Backups
│   ├── export.php         # Export/Import
│   └── helpers.php        # Funciones auxiliares
├── config/                 # ✅ NUEVO
│   ├── database.php       # Configuración BD
│   └── schema.sql         # Esquema MySQL
├── public/                 # ✅ IGUAL
│   ├── index.html
│   ├── app.js
│   └── styles.css
├── uploads/               # Fotos
├── backups/               # Respaldos SQL
├── .htaccess              # ✅ NUEVO (Apache)
├── index.php              # ✅ NUEVO (entry point)
├── instalar.bat           # ✅ NUEVO (Windows)
├── README.md              # ✅ ACTUALIZADO
└── INSTALACION.md         # ✅ NUEVO
```

### 4. APIs Creadas

Todas las APIs mantienen las mismas rutas que el frontend espera:

| Endpoint | Funcionalidad | Método |
|----------|--------------|--------|
| `/api/auth/status` | Verificar sesión | GET |
| `/api/auth/bootstrap` | Crear primer usuario | POST |
| `/api/auth/login` | Iniciar sesión | POST |
| `/api/auth/logout` | Cerrar sesión | POST |
| `/api/products` | CRUD productos | GET/POST/PUT/DELETE |
| `/api/clients` | CRUD clientes | GET/POST/PUT/DELETE |
| `/api/purchases` | CRUD compras | GET/POST |
| `/api/sales` | CRUD ventas | GET/POST/DELETE |
| `/api/shipments` | CRUD envíos | GET/POST/PUT/DELETE |
| `/api/cash-movements` | Movimientos caja | GET/POST |
| `/api/cash/summary` | Resumen caja | GET |
| `/api/settings` | Configuración | GET/PUT |
| `/api/admin/users` | Usuarios | GET/POST/DELETE |
| `/api/admin/roles` | Roles | GET |
| `/api/reports/summary` | Resumen general | GET |
| `/api/reports/charts` | Datos gráficas | GET |
| `/api/reports/filtered` | Reporte filtrado | GET |
| `/api/backups` | Respaldos | GET/POST |
| `/api/export/:type` | Exportar CSV | GET |
| `/api/import/:type` | Importar datos | POST |
| `/api/health` | Health check | GET |

### 5. Seguridad

| Característica | Implementación |
|---------------|---------------|
| Hash de contraseñas | PBKDF2-SHA512 (100,000 iteraciones) |
| Salt aleatorio | 32 caracteres hex (16 bytes) |
| Sesiones | Tokens de 64 caracteres con expiración |
| Duración de sesión | 24 horas |
| Prepared statements | PDO para prevenir SQL injection |
| CORS | Configurado para localhost y producción |
| XSS protection | Headers de seguridad |

### 6. Archivos Eliminados
- ❌ server.js
- ❌ server.js.backup
- ❌ package.json
- ❌ package-lock.json
- ❌ negocio.db
- ❌ reset-data.js
- ❌ check-db.js
- ❌ test-shipment.js
- ❌ test-shipment.zip
- ❌ backups.7z
- ❌ render.yaml
- ❌ node_modules/ (todo el directorio)

---

## Cómo Usar el Nuevo Sistema

### Instalación Rápida (Windows)
```bash
cd c:\wamp64\www\yume-main
instalar.bat
```

### Instalación Manual
1. Crea base de datos MySQL: `yume_negocio`
2. Importa esquema: `mysql -u root -p yume_negocio < config/schema.sql`
3. Configura conexión en `config/database.php`
4. Accede: `http://localhost/yume-main`
5. Crea tu primer usuario administrador

### Configuración de Apache
Asegúrate de tener `mod_rewrite` habilitado:
```apache
LoadModule rewrite_module modules/mod_rewrite.so
```

Y en tu VirtualHost o httpd.conf:
```apache
AllowOverride All
```

---

## Ventajas de la Migración

✅ **Más fácil de desplegar:** Solo necesitas PHP + MySQL (WAMP/XAMPP)
✅ **Sin dependencias:** No necesitas npm, node_modules, ni instalar paquetes
✅ **Base de datos limpia:** Comienza desde cero, sin datos antiguos
✅ **Más portable:** Funciona en cualquier hosting compartido con PHP
✅ **Mismo diseño:** El frontend es exactamente igual
✅ **Código organizado:** APIs separadas por funcionalidad
✅ **Documentación completa:** README.md e INSTALACION.md incluidos

---

## Funcionalidades Mantenidas

✅ Autenticación con bootstrap (primer usuario)
✅ Gestión de clientes (CRUD completo)
✅ Inventario de productos con fotos
✅ Compras a proveedores
✅ Ventas con cálculo automático de ganancias
✅ Stock automático (descuenta al vender)
✅ Envíos y transporte
✅ Caja con ingresos y egresos
✅ Reportes y estadísticas
✅ Gráficas con Chart.js
✅ Administración de usuarios y roles
✅ Exportación a CSV
✅ Importación de datos
✅ Backups de base de datos
✅ Sesiones de 24 horas
✅ Diseño responsive

---

## Notas Importantes

1. **Base de datos vacía:** El sistema comienza sin datos. Debes crear tu primer usuario administrador al entrar.

2. **Rutas de archivos:** Si estás en Linux, asegúrate de que las rutas en el código usen `/` en lugar de `\`.

3. **Permisos:** En Linux/Mac, las carpetas `uploads/` y `backups/` necesitan permisos de escritura.

4. **MySQL 8.0+:** El esquema usa CHECK constraints que requieren MySQL 8.0+ o MariaDB 10.3+.

5. **mod_rewrite:** Es esencial para que funcionen las rutas de la API. Sin él, tendrás errores 404.

6. **Fotos de productos:** Se guardan en `uploads/` con nombres aleatorios para evitar colisiones.

7. **Backups:** Se guardan en formato SQL en `backups/`. Puedes restaurarlos con:
   ```bash
   mysql -u root -p yume_negocio < backups/archivo.sql
   ```

---

## Soporte

Si encuentras problemas:
1. Revisa `INSTALACION.md` para la guía completa
2. Verifica los logs de Apache en `c:\wamp64\logs\`
3. Revisa la consola del navegador (F12) para errores
4. Comprueba que MySQL esté ejecutándose

---

## Créditos

- **Diseño original:** Node.js + PostgreSQL
- **Migración:** PHP + MySQL
- **Frontend:** HTML + CSS + JavaScript (sin cambios)
- **Base de datos:** MySQL con tablas InnoDB

---

**¡Migración exitosa! El sistema está listo para usar.** 🎀
