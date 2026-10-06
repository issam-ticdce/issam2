#!/usr/bin/env bash
# =====================================================================
#  TICDCE Marketplace - installation / mise a jour sur Ubuntu 24.04
#
#  Usage (depuis le dossier de l'application) :
#     sudo bash ubuntu/installer.sh
#
#  Le script peut etre relance sans risque : il met a jour l'application
#  et conserve la base de donnees, les images et la configuration.
# =====================================================================
set -euo pipefail

APP_DIR="/var/www/ticdce-marketplace"
DB_NAME="ticdce_marketplace"
DB_USER="ticdce"
CREDS="/root/ticdce-marketplace-identifiants.txt"
PHP="php8.3"
SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

bleu()  { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
ok()    { printf '\033[1;32m    %s\033[0m\n' "$*"; }
erreur(){ printf '\n\033[1;31mERREUR : %s\033[0m\n' "$*" >&2; exit 1; }
service_cmd() { # demarre / recharge un service, avec ou sans systemd
    if pidof systemd >/dev/null 2>&1; then systemctl "$1" "$2"; else service "$2" "$1"; fi
}

[[ $EUID -eq 0 ]] || erreur "lancez le script avec sudo :  sudo bash ubuntu/installer.sh"
[[ -f "$SRC/artisan" ]] || erreur "lancez le script depuis le dossier de l'application."
grep -q 'VERSION_ID="24.04"' /etc/os-release || echo "Attention : script prevu pour Ubuntu 24.04."

PREMIERE_INSTALL=1
[[ -f "$APP_DIR/.env" ]] && PREMIERE_INSTALL=0

# ---------------------------------------------------------------------
# 1. Questions (seulement a la premiere installation)
# ---------------------------------------------------------------------
if [[ $PREMIERE_INSTALL -eq 1 ]]; then
    IP_DEFAUT="$(hostname -I 2>/dev/null | awk '{print $1}')"
    echo
    echo "Installation de TICDCE Marketplace"
    echo "-----------------------------------"
    read -rp "Nom de domaine (ex. marketplace.ticdce.tn) ou laissez vide pour utiliser l'IP [$IP_DEFAUT] : " DOMAINE
    read -rp "Email qui recoit les demandes de validation et les copies des messages : " EMAIL_ADMIN
    [[ -n "$EMAIL_ADMIN" ]] || erreur "l'email est obligatoire."
    HTTPS="n"
    if [[ -n "$DOMAINE" ]]; then
        read -rp "Activer le HTTPS gratuit (Let's Encrypt) ? Le domaine doit deja pointer vers ce serveur [o/N] : " HTTPS
    fi
    HOTE="${DOMAINE:-$IP_DEFAUT}"
fi

# ---------------------------------------------------------------------
# 2. Logiciels
# ---------------------------------------------------------------------
bleu "Installation des logiciels (Nginx, PHP 8.3, MySQL)…"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq || true
apt-get install -y -qq nginx mysql-server rsync unzip curl openssl \
    $PHP-fpm $PHP-cli $PHP-mysql $PHP-mbstring $PHP-xml $PHP-curl $PHP-zip \
    $PHP-intl $PHP-gd $PHP-bcmath $PHP-sqlite3 >/dev/null
ok "Logiciels installes."

INI="/etc/php/8.3/fpm/php.ini"
sed -i 's/^upload_max_filesize.*/upload_max_filesize = 10M/; s/^post_max_size.*/post_max_size = 20M/; s/^memory_limit.*/memory_limit = 256M/' "$INI"

service_cmd start mysql
service_cmd restart $PHP-fpm

# ---------------------------------------------------------------------
# 3. Fichiers de l'application
# ---------------------------------------------------------------------
bleu "Copie de l'application dans $APP_DIR…"
mkdir -p "$APP_DIR"
if [[ "$SRC" != "$APP_DIR" ]]; then
    rsync -a --delete \
        --exclude='.env' --exclude='.git' --exclude='/storage/app/' --exclude='/storage/logs/' \
        --exclude='/storage/framework/sessions/' --exclude='/public/storage' \
        --exclude='/database/*.sqlite' --exclude='/sauvegardes/' \
        "$SRC/" "$APP_DIR/"
fi
mkdir -p "$APP_DIR"/storage/{app/public,logs,framework/{cache/data,sessions,views}} "$APP_DIR/bootstrap/cache"
cd "$APP_DIR"

if [[ ! -f vendor/autoload.php ]]; then
    bleu "Telechargement des bibliotheques PHP (composer)…"
    command -v composer >/dev/null || apt-get install -y -qq composer >/dev/null
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --quiet
fi

# ---------------------------------------------------------------------
# 4. Base de donnees et configuration (premiere installation)
# ---------------------------------------------------------------------
if [[ $PREMIERE_INSTALL -eq 1 ]]; then
    bleu "Creation de la base de donnees MySQL…"
    DB_PASS="$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c 24)"
    mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
    ok "Base '$DB_NAME' prete."

    if [[ "${HTTPS,,}" == o* ]]; then SCHEMA="https"; else SCHEMA="http"; fi
    cp .env.example .env
    set_env() { # cle valeur
        if grep -q "^$1=" .env; then sed -i "s|^$1=.*|$1=$2|" .env; else echo "$1=$2" >> .env; fi
    }
    set_env APP_ENV production
    set_env APP_DEBUG false
    set_env APP_URL "$SCHEMA://$HOTE"
    set_env TICDCE_ADMIN_EMAIL "$EMAIL_ADMIN"
    set_env TICDCE_CONTACT_EMAIL "$EMAIL_ADMIN"
    set_env DB_CONNECTION mysql
    sed -i 's/^# *DB_HOST=.*/DB_HOST=127.0.0.1/; s/^# *DB_PORT=.*/DB_PORT=3306/; s/^# *DB_DATABASE=.*/DB_DATABASE=x/; s/^# *DB_USERNAME=.*/DB_USERNAME=x/; s/^# *DB_PASSWORD=.*/DB_PASSWORD=x/' .env
    set_env DB_DATABASE "$DB_NAME"
    set_env DB_USERNAME "$DB_USER"
    set_env DB_PASSWORD "$DB_PASS"
    set_env LOG_LEVEL warning
    set_env SESSION_SECURE_COOKIE "$([[ $SCHEMA == https ]] && echo true || echo false)"
    $PHP artisan key:generate --force --no-interaction >/dev/null

    umask 077
    cat > "$CREDS" <<TXT
TICDCE Marketplace - informations d'installation ($(date '+%d/%m/%Y'))
Adresse du site  : $SCHEMA://$HOTE
Dossier          : $APP_DIR
Configuration    : $APP_DIR/.env
Base MySQL       : $DB_NAME
Utilisateur MySQL: $DB_USER
Mot de passe     : $DB_PASS
TXT
    umask 022
fi

# ---------------------------------------------------------------------
# 5. Mise en place de l'application
# ---------------------------------------------------------------------
bleu "Preparation de l'application…"
$PHP artisan migrate --force --no-interaction
if [[ "$($PHP artisan tinker --execute 'echo App\Models\Sector::count();' 2>/dev/null | tail -1)" == "0" ]]; then
    $PHP artisan db:seed --force --no-interaction >/dev/null
    ok "Secteurs crees."
fi
rm -f public/storage
$PHP artisan storage:link --no-interaction >/dev/null
$PHP artisan optimize >/dev/null
$PHP artisan filament:optimize >/dev/null 2>&1 || true

chown -R root:root "$APP_DIR"
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod 640 "$APP_DIR/.env" && chgrp www-data "$APP_DIR/.env"
ok "Application prete."

# ---------------------------------------------------------------------
# 6. Serveur web Nginx
# ---------------------------------------------------------------------
if [[ $PREMIERE_INSTALL -eq 1 ]]; then
    bleu "Configuration de Nginx…"
    cat > /etc/nginx/sites-available/ticdce-marketplace <<NGINX
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name ${DOMAINE:-_};
    root $APP_DIR/public;
    index index.php;
    client_max_body_size 20M;
    charset utf-8;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/$PHP-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~* \.(css|js|png|jpg|jpeg|gif|webp|svg|ico|woff2?)\$ {
        expires 7d;
        try_files \$uri /index.php?\$query_string;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX
    ln -sf /etc/nginx/sites-available/ticdce-marketplace /etc/nginx/sites-enabled/ticdce-marketplace
    rm -f /etc/nginx/sites-enabled/default
fi
nginx -t -q
if pgrep -x nginx >/dev/null; then service_cmd reload nginx; else service_cmd start nginx; fi
ok "Nginx configure."

if command -v ufw >/dev/null && ufw status | grep -q "Status: active"; then
    ufw allow 'Nginx Full' >/dev/null && ok "Pare-feu : ports 80 et 443 ouverts."
fi

if [[ $PREMIERE_INSTALL -eq 1 && "${HTTPS,,}" == o* ]]; then
    bleu "Activation du HTTPS…"
    apt-get install -y -qq certbot python3-certbot-nginx >/dev/null
    certbot --nginx -d "$DOMAINE" --non-interactive --agree-tos -m "$EMAIL_ADMIN" --redirect \
        || echo "  HTTPS non active (le domaine pointe-t-il vers ce serveur ?). Relancez plus tard : sudo certbot --nginx -d $DOMAINE"
fi

# ---------------------------------------------------------------------
# 7. Sauvegarde automatique quotidienne (base + images, 14 jours)
# ---------------------------------------------------------------------
cat > /usr/local/bin/ticdce-sauvegarde <<'SAVE'
#!/usr/bin/env bash
set -e
DEST=/var/backups/ticdce-marketplace
mkdir -p "$DEST"
D=$(date +%F)
mysqldump --single-transaction ticdce_marketplace | gzip > "$DEST/base-$D.sql.gz"
tar czf "$DEST/images-$D.tar.gz" -C /var/www/ticdce-marketplace/storage/app public
find "$DEST" -type f -mtime +14 -delete
SAVE
chmod 700 /usr/local/bin/ticdce-sauvegarde
echo "30 2 * * * root /usr/local/bin/ticdce-sauvegarde" > /etc/cron.d/ticdce-marketplace
ok "Sauvegarde automatique chaque nuit dans /var/backups/ticdce-marketplace"

# ---------------------------------------------------------------------
# 8. Compte administrateur
# ---------------------------------------------------------------------
if [[ $PREMIERE_INSTALL -eq 1 ]]; then
    bleu "Creation de votre compte administrateur TICDCE"
    $PHP artisan ticdce:admin
fi

chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

URL="$(grep '^APP_URL=' .env | cut -d= -f2-)"
echo
echo "====================================================================="
echo "  TICDCE Marketplace est en ligne"
echo
echo "  Site public        : $URL"
echo "  Administration     : $URL/admin"
echo "  Espace startups    : $URL/espace"
echo
echo "  Informations d'installation (mot de passe MySQL) : $CREDS"
echo "  Pour mettre a jour plus tard : relancez ce meme script."
echo "  Pour envoyer de vrais emails : renseignez les lignes MAIL_* dans"
echo "  $APP_DIR/.env puis lancez : sudo php8.3 $APP_DIR/artisan optimize"
echo "====================================================================="
