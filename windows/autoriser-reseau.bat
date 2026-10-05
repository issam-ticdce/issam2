@echo off
chcp 65001 >nul
rem A lancer avec clic droit > "Executer en tant qu'administrateur"
net session >nul 2>&1
if errorlevel 1 (
    echo.
    echo  Ce fichier doit etre lance en administrateur :
    echo  clic droit sur autoriser-reseau.bat ^> "Executer en tant qu'administrateur"
    echo.
    pause
    exit /b 1
)
netsh advfirewall firewall delete rule name="TICDCE Marketplace" >nul 2>&1
netsh advfirewall firewall add rule name="TICDCE Marketplace" dir=in action=allow protocol=TCP localport=8888,8890,9000,9090,7777,3000,5000,5555 profile=private,domain
echo.
echo  Les autres postes du reseau peuvent maintenant ouvrir le site.
echo.
pause
