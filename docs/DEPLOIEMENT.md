# Installation sur le serveur (Ubuntu 24.04)

Ce guide installe la marketplace sur un serveur Ubuntu 24.04 neuf, avec Nginx, PHP 8.3 et MySQL.
Remplacez `marketplace.ticdce.tn` par votre vrai nom de domaine.

Toutes les commandes se lancent dans un terminal sur le serveur (connexion SSH).

## 1. Installer les logiciels

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server git unzip composer \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-zip php8.3-intl php8.3-gd php8.3-bcmath php8.3-sqlite3
```

Autorisez les images jusqu'à 10 Mo :

```bash
sudo sed -i 's/^upload_max_filesize.*/upload_max_filesize = 10M/; s/^post_max_size.*/post_max_size = 20M/' /etc/php/8.3/fpm/php.ini
sudo systemctl restart php8.3-fpm
```

## 2. Créer la base de données

```bash
sudo mysql
```

Puis, dans MySQL (choisissez un vrai mot de passe) :

```sql
CREATE DATABASE marketplace CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'marketplace'@'localhost' IDENTIFIED BY 'UN_MOT_DE_PASSE_SOLIDE';
GRANT ALL PRIVILEGES ON marketplace.* TO 'marketplace'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 3. Récupérer le code

```bash
sudo mkdir -p /var/www && cd /var/www
sudo git clone https://github.com/issam-ticdce/issam2.git marketplace
sudo chown -R $USER:www-data marketplace
cd marketplace
composer install --no-dev --optimize-autoloader
```

## 4. Configurer

```bash
cp .env.example .env
php artisan key:generate
nano .env
```

Modifiez au minimum ces lignes :

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://marketplace.ticdce.tn

TICDCE_ADMIN_EMAIL=adresse-qui-recoit-les-validations@...
TICDCE_CONTACT_EMAIL=contact@...
TICDCE_CONTACT_PHONE="+216 .. ... ..."

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=marketplace
DB_USERNAME=marketplace
DB_PASSWORD=UN_MOT_DE_PASSE_SOLIDE

# Envoi des emails (serveur SMTP de votre messagerie)
MAIL_MAILER=smtp
MAIL_HOST=smtp.votre-fournisseur.tn
MAIL_PORT=587
MAIL_USERNAME=no-reply@...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="no-reply@..."
```

> Sans configuration SMTP, rien n'est perdu : les demandes des visiteurs sont enregistrées et visibles dans les espaces. Seuls les emails ne partent pas.

Puis :

```bash
php artisan migrate --force
php artisan db:seed --force          # crée les secteurs (modifiables ensuite)
php artisan storage:link
php artisan ticdce:admin              # crée votre compte administrateur
php artisan optimize
php artisan filament:optimize

sudo chown -R $USER:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

⚠️ Ne lancez **pas** `DemoSeeder` en production : il crée des startups fictives et des comptes avec le mot de passe `password`.

## 5. Configurer Nginx

```bash
sudo nano /etc/nginx/sites-available/marketplace
```

```nginx
server {
    listen 80;
    server_name marketplace.ticdce.tn;
    root /var/www/marketplace/public;
    index index.php;
    client_max_body_size 20M;
    charset utf-8;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/marketplace /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

## 6. Activer HTTPS (certificat gratuit)

Le nom de domaine doit déjà pointer vers l'adresse IP du serveur.

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d marketplace.ticdce.tn
```

Le renouvellement est automatique.

## 7. Sauvegardes (recommandé)

Sauvegarde quotidienne de la base et des images, conservée 14 jours :

```bash
sudo mkdir -p /var/backups/marketplace
sudo crontab -e
```

Ajoutez la ligne suivante (remplacez le mot de passe) :

```cron
30 2 * * * mysqldump -u marketplace -p'UN_MOT_DE_PASSE_SOLIDE' marketplace | gzip > /var/backups/marketplace/db-$(date +\%F).sql.gz && tar czf /var/backups/marketplace/images-$(date +\%F).tar.gz -C /var/www/marketplace/storage/app public && find /var/backups/marketplace -mtime +14 -delete
```

Copiez régulièrement ce dossier sur un autre support (disque externe, autre serveur).

## Mettre à jour la plateforme

Quand le code évolue sur GitHub :

```bash
cd /var/www/marketplace
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan filament:optimize
php artisan up
```

## En cas de problème

- **Page blanche ou « Server Error »** : lisez la fin du journal avec `tail -50 storage/logs/laravel.log`.
- **Les images ne s'affichent pas** : vérifiez `APP_URL` dans `.env`, puis relancez `php artisan storage:link` et `php artisan optimize`.
- **Les emails ne partent pas** : vérifiez les lignes `MAIL_*` et cherchez « Envoi email » dans `storage/logs/laravel.log`.
- Après toute modification de `.env`, relancez `php artisan optimize`.
