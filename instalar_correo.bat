@echo off
cd /d "%~dp0"
echo Instalando PHPMailer...
composer install --no-dev
echo.
echo Si no reconoce composer, instala Composer para Windows y vuelve a ejecutar este archivo.
pause
