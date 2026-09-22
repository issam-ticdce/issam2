# Où modifier quoi

| Je veux modifier… | Fichier |
|---|---|
| Les textes du site public | `lang/fr/site.php`, `lang/ar/site.php`, `lang/en/site.php` |
| Les libellés des espaces privés | `lang/*/space.php` |
| Le texte des emails | `lang/*/mail.php` et `app/Notifications/` |
| Les couleurs et la mise en page du site | `public/css/site.css` (couleurs en haut du fichier) |
| Le logo | `public/images/logo-ticdce.png`, `public/images/logo-mark.png`, `public/favicon.png` |
| Les pages du site public | `resources/views/site/` et `resources/views/layouts/site.blade.php` |
| Les listes (types de produits, stades, besoins…) | `app/Enums/` + leurs traductions dans `lang/*/site.php` |
| Les champs des formulaires | `app/Filament/Shared/StartupFields.php` et `ProductFields.php` |
| L'email de contact, l'adresse… | `.env` (lignes `TICDCE_*`) ou `config/ticdce.php` |
| Les adresses des pages | `routes/web.php` |

## Organisation

- `app/Models/` : Startup, Product, Sector, Inquiry (demande d'un visiteur), User.
- `app/Models/Concerns/Moderated.php` : le circuit de validation (brouillon → en attente → publié).
- `app/Http/Controllers/` : pages du site public et formulaire de contact.
- `app/Filament/Admin/` : l'administration du TICDCE (`/admin`).
- `app/Filament/Startup/` : l'espace des startups (`/espace`).
- `app/Filament/Shared/` : formulaires et éléments communs aux deux espaces.
- `tests/Feature/MarketplaceTest.php` : tests automatiques (`php artisan test`).

Après une modification sur le serveur, lancez `php artisan optimize`.
