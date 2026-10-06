@echo off
chcp 65001 >nul
setlocal
cd /d "%~dp0"
title TICDCE Marketplace

rem ---------------------------------------------------------------
rem  TICDCE Marketplace - demarrage sur un PC Windows (reseau interne)
rem ---------------------------------------------------------------

where php >nul 2>nul
if errorlevel 1 (
    echo.
    echo  PHP est introuvable sur cet ordinateur.
    echo  Installez Laravel Herd : https://herd.laravel.com/windows
    echo  puis relancez ce fichier.
    echo.
    pause
    exit /b 1
)

rem Herd fournit "php" sous forme de php.bat : on retrouve le vrai php.exe
rem (sinon ce script s'arreterait apres la premiere commande php).
set "PHP="
for /f "usebackq delims=" %%B in (`php -r "echo PHP_BINARY;"`) do set "PHP=%%B"
if not defined PHP set "PHP=php"

if not exist ".env" copy "windows-env.txt" ".env" >nul

findstr /b /c:"APP_KEY=base64:" ".env" >nul
if errorlevel 1 "%PHP%" artisan key:generate --force --no-interaction >nul

if not exist "database\database.sqlite" type nul > "database\database.sqlite"

echo  Preparation de la base de donnees...
"%PHP%" artisan migrate --force --no-interaction >nul
if errorlevel 1 (
    echo  Erreur pendant la preparation de la base. Details dans storage\logs\laravel.log
    pause
    exit /b 1
)

if not exist "storage\installation-terminee.txt" (
    "%PHP%" artisan db:seed --force --no-interaction >nul
    echo.
    echo  PREMIER LANCEMENT
    echo.
    choice /c ON /m " Charger les donnees de DEMONSTRATION (startups fictives, mot de passe 'password') "
    if errorlevel 2 (
        echo.
        echo  Creation du compte administrateur TICDCE :
        "%PHP%" artisan ticdce:admin
    ) else (
        "%PHP%" artisan db:seed --class=DemoSeeder --force --no-interaction >nul
        echo  Donnees de demonstration chargees.
        echo  Comptes : admin@example.com et startup@example.com - mot de passe : password
    )
    echo ok> "storage\installation-terminee.txt"
)

rem Recherche d'un port libre (Windows en reserve certains, ex. 8000-8010)
set "PORT="
for %%P in (8888 8890 9000 9090 7777 3000 5000 5555) do (
    if not defined PORT "%PHP%" -r "exit(@stream_socket_server('tcp://0.0.0.0:%%P') ? 0 : 1);" && set "PORT=%%P"
)
if not defined PORT (
    echo  Aucun port libre trouve. Voir LISEZMOI.txt
    pause
    exit /b 1
)

rem Adresse IP de ce PC sur le reseau (carte utilisee pour sortir vers Internet)
for /f "usebackq delims=" %%I in (`php -r "$s=@stream_socket_client('udp://8.8.8.8:53'); $n=$s?stream_socket_get_name($s,false):''; echo $n?explode(':',$n)[0]:gethostbyname(gethostname());"`) do set "IP=%%I"
set "APP_URL=http://%IP%:%PORT%"

cls
echo.
echo  =============================================================
echo    TICDCE Marketplace est lance
echo  =============================================================
echo.
echo    Sur ce poste          : http://localhost:%PORT%
echo    Depuis le reseau      : http://%IP%:%PORT%
echo.
echo    Administration TICDCE : http://localhost:%PORT%/admin
echo    Espace startup        : http://localhost:%PORT%/espace
echo.
echo    Laissez cette fenetre OUVERTE. Pour arreter : fermez-la.
echo.
echo    Si Windows demande l'autorisation du pare-feu pour php.exe,
echo    cochez "Reseaux prives" puis cliquez sur "Autoriser".
echo  =============================================================
echo.

start "" cmd /c "timeout /t 2 /nobreak >nul & start http://localhost:%PORT%"
"%PHP%" -S 0.0.0.0:%PORT% -t public serve.php 2>nul
echo.
echo  Le site s'est arrete.
pause
