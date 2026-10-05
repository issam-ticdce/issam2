@echo off
chcp 65001 >nul
cd /d "%~dp0"
echo.
echo  Creation (ou mise a jour) d'un compte administrateur TICDCE
echo.
php artisan ticdce:admin
echo.
pause
