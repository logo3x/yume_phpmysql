@echo off
REM ====================================================
REM Script de instalación automática para Windows
REM ====================================================

echo.
echo ============================================
echo  INSTALACION - Control de Negocio PHP/MySQL
echo ============================================
echo.

REM Verificar si mysql esta disponible
where mysql >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo ERROR: No se encontro el comando 'mysql'.
    echo.
    echo Asegurate de que WAMP/XAMPP este instalado y ejecutandose.
    echo Agrega MySQL a tu PATH o usa la ruta completa.
    echo.
    echo Ejemplo: C:\wamp64\bin\mysql\mysql8.0.31\bin\mysql.exe
    echo.
    pause
    exit /b 1
)

echo Paso 1: Crear base de datos...
echo.

REM Pedir datos de conexion
set /p DB_USER="Usuario de MySQL (default root): "
if "%DB_USER%"=="" set DB_USER=root

set /p DB_PASS="Contraseña de MySQL (dejar vacio si no hay): "

set /p DB_NAME="Nombre de la base de datos (default yume_negocio): "
if "%DB_NAME%"=="" set DB_NAME=yume_negocio

echo.
echo Creando base de datos: %DB_NAME%
echo.

REM Crear la base de datos
mysql -u %DB_USER% -p%DB_PASS% -e "CREATE DATABASE IF NOT EXISTS %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

if %ERRORLEVEL% neq 0 (
    echo ERROR: No se pudo crear la base de datos.
    echo Verifica tus credenciales.
    pause
    exit /b 1
)

echo.
echo Paso 2: Importar esquema...
echo.

REM Importar el esquema
mysql -u %DB_USER% -p%DB_PASS% %DB_NAME% < config\schema.sql

if %ERRORLEVEL% neq 0 (
    echo ERROR: No se pudo importar el esquema.
    pause
    exit /b 1
)

echo.
echo ============================================
echo  INSTALACION COMPLETADA EXITOSAMENTE!
echo ============================================
echo.
echo Base de datos: %DB_NAME%
echo.
echo Ahora necesitas configurar la conexion en:
echo config\database.php
echo.
echo Cambia estas lineas:
echo   define('DB_USER', '%DB_USER%');
echo   define('DB_PASS', '%DB_PASS%');
echo   define('DB_NAME', '%DB_NAME%');
echo.
echo Luego accede a: http://localhost/yume-main
echo.
pause
