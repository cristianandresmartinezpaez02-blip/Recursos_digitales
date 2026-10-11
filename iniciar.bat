@echo off
chcp 65001 >nul
title CriptoAnalisis - servidor local
cd /d "%~dp0"

rem =====================================================================
rem  Abre CriptoAnalisis SIN XAMPP. Doble clic en este archivo y listo.
rem  - Usa PHP si ya esta instalado; si no, descarga PHP portable (una vez,
rem    unos 34 MB) en la carpeta "php" de este proyecto.
rem  - Los usuarios y su progreso se guardan en data\basedatos.sqlite.
rem  - Para apagar la pagina, cierra esta ventana.
rem =====================================================================

set PUERTO=8000
set PHP=

if exist "php\php.exe" set "PHP=%~dp0php\php.exe"
if not defined PHP (
    where php >nul 2>nul && set "PHP=php"
)
if not defined PHP if exist "C:\xampp\php\php.exe" set "PHP=C:\xampp\php\php.exe"

if not defined PHP (
    echo No se encontro PHP. Descargando PHP portable ^(solo la primera vez, unos 34 MB^)...
    powershell -NoProfile -ExecutionPolicy Bypass -Command ^
        "$ProgressPreference='SilentlyContinue';" ^
        "Invoke-WebRequest 'https://downloads.php.net/~windows/releases/latest/php-8.3-nts-Win32-vs16-x64-latest.zip' -OutFile 'php.zip';" ^
        "Expand-Archive 'php.zip' -DestinationPath 'php' -Force; Remove-Item 'php.zip';" ^
        "$ini = Get-Content 'php\php.ini-production';" ^
        "$ini = $ini -replace '^;\s*extension_dir = \"ext\"', 'extension_dir = \"ext\"';" ^
        "foreach ($e in 'curl','openssl','pdo_sqlite','sqlite3','mbstring','fileinfo','zip') { $ini = $ini -replace ('^;extension=' + $e + '$'), ('extension=' + $e) };" ^
        "$ini = $ini -replace '^;date.timezone =', 'date.timezone = America/Bogota';" ^
        "Set-Content 'php\php.ini' $ini;" ^
        "Invoke-WebRequest 'https://curl.se/ca/cacert.pem' -OutFile 'php\cacert.pem'"
    if not exist "php\php.exe" (
        echo.
        echo No se pudo descargar PHP. Revisa tu conexion a internet e intentalo de nuevo.
        pause
        exit /b 1
    )
    set "PHP=%~dp0php\php.exe"
)

"%PHP%" -v >nul 2>nul
if errorlevel 1 (
    echo.
    echo PHP no arranca. Instala "Microsoft Visual C++ Redistributable" desde:
    echo     https://aka.ms/vs/17/release/vc_redist.x64.exe
    echo y vuelve a abrir este archivo.
    pause
    exit /b 1
)

rem Certificados raiz para las conexiones https (precios, Google): PHP portable no los trae.
set CERTS=
if exist "php\cacert.pem" set CERTS=-d "curl.cainfo=%~dp0php\cacert.pem" -d "openssl.cafile=%~dp0php\cacert.pem"

echo.
echo  ==================================================
echo   CriptoAnalisis esta funcionando en:
echo      http://localhost:%PUERTO%
echo   (deja esta ventana abierta; cierrala para apagar)
echo  ==================================================
echo.
start "" cmd /c "timeout /t 2 >nul & start http://localhost:%PUERTO%/"
"%PHP%" %CERTS% -S localhost:%PUERTO% -t "%~dp0." "%~dp0router.php"
pause
