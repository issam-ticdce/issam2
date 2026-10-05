@echo off
chcp 65001 >nul
setlocal
cd /d "%~dp0"
for /f %%D in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd_HHmm"') do set "D=%%D"
set "DEST=sauvegardes\%D%"
mkdir "%DEST%" 2>nul
copy "database\database.sqlite" "%DEST%\database.sqlite" >nul
copy ".env" "%DEST%\env.txt" >nul
xcopy "storage\app\public" "%DEST%\images\" /e /i /q /y >nul
echo.
echo  Sauvegarde terminee dans : %CD%\%DEST%
echo  Copiez ce dossier sur une cle USB ou un autre ordinateur.
echo.
pause
