# Faire tourner la marketplace sur votre ordinateur

Cette version locale sert à **tester** : elle utilise une petite base de données dans un fichier (SQLite), sans MySQL, et les emails ne sont pas envoyés (ils sont écrits dans `storage/logs/laravel.log`).

## 1. Installer PHP et Composer (une seule fois)

### Windows ou macOS : Laravel Herd (le plus simple)

1. Téléchargez **Herd** (gratuit) sur https://herd.laravel.com et installez-le.
2. Herd installe PHP et Composer, avec toutes les extensions nécessaires.
3. Ouvrez un **nouveau** terminal (Windows : « Terminal » ou « PowerShell » ; macOS : « Terminal ») et vérifiez :
   ```bash
   php -v
   composer -V
   ```
   Les deux commandes doivent afficher un numéro de version (PHP 8.3 ou plus récent).

### Ubuntu / Linux

```bash
sudo apt install -y git unzip composer php8.3-cli php8.3-sqlite3 php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-intl php8.3-gd php8.3-bcmath
```

## 2. Récupérer le code

**Avec Git** :
```bash
git clone -b claude/ticdce-startup-marketplace-9k5bwi https://github.com/issam-ticdce/issam2.git ticdce-marketplace
cd ticdce-marketplace
```

**Sans Git** : téléchargez le ZIP
https://github.com/issam-ticdce/issam2/archive/refs/heads/claude/ticdce-startup-marketplace-9k5bwi.zip,
décompressez-le, puis ouvrez un terminal dans le dossier décompressé.

## 3. Installer (une seule fois)

```bash
composer install
composer setup
composer demo
```

- `composer install` télécharge les bibliothèques (quelques minutes la première fois).
- `composer setup` crée la configuration, la base de données et les secteurs.
- `composer demo` ajoute 3 startups **fictives** et 2 comptes de test.

## 4. Lancer

```bash
composer start
```

Ouvrez **http://localhost:8000** dans votre navigateur. Pour arrêter : `Ctrl + C` dans le terminal.
Pour relancer plus tard, il suffit de refaire `composer start` depuis le même dossier.

| Page | Adresse | Compte de test |
|---|---|---|
| Site public | http://localhost:8000 | — |
| Administration TICDCE | http://localhost:8000/admin | `admin@example.com` / `password` |
| Espace startup | http://localhost:8000/espace | `startup@example.com` / `password` |

## Voir les emails

Les emails (invitations, validations, messages des visiteurs) ne partent pas en local. Ils sont écrits à la fin du fichier `storage/logs/laravel.log`. Pour tester une invitation, copiez le lien `http://localhost:8000/espace/password-reset/...` depuis ce fichier dans votre navigateur.

## Repartir de zéro

Pour effacer toutes les données de test et recommencer :

```bash
php artisan migrate:fresh --seed
composer demo
```

## Problèmes fréquents

- **`php` ou `composer` introuvable** : fermez et rouvrez le terminal après l'installation de Herd.
- **Port 8000 déjà utilisé** (« Failed to listen » / « Address already in use ») : lancez `php -S 127.0.0.1:8001 -t public serve.php`, puis ouvrez http://localhost:8001 (et remplacez `8000` par `8001` dans la ligne `APP_URL` du fichier `.env`).
- **Les images ajoutées ne s'affichent pas** : vérifiez que la ligne `APP_URL` du fichier `.env` correspond exactement à l'adresse ouverte dans le navigateur.
- **Extension manquante (intl, zip, sqlite…)** lors de `composer install` : avec Herd, elles sont incluses. Sous Linux, installez le paquet `php8.3-…` correspondant.
