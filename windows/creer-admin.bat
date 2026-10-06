@echo off
chcp 65001 >nul
cd /d "%~dp0"
echo.
echo  Creation (ou mise a jour) d'un compte administrateur TICDCE
echo.
set "PHP="
for /f "usebackq delims=" %%B in (`php -r "echo PHP_BINARY;"`) do set "PHP=%%B"
if not defined PHP set "PHP=php"
"%PHP%" artisan ticdce:admin
echo.
pause
