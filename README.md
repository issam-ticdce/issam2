# TICDCE Marketplace

Plateforme d'exposition des startups incubées au **TICDCE** (Centre international de Tunis pour l'économie culturelle numérique) : leurs produits, services, applications, solutions, produits culturels et projets.

Le site est en **français, arabe et anglais**. L'arabe s'affiche de droite à gauche.

## Ce que fait la plateforme

| Qui | Où | Quoi |
|---|---|---|
| Visiteurs (clients, investisseurs, partenaires, grand public) | `/fr`, `/ar`, `/en` | Parcourir les startups et les produits, rechercher, filtrer, **contacter**, **demander un devis**, **manifester un intérêt d'investissement** |
| Startups | `/espace` | Gérer leur fiche, leurs produits et projets (dans les 3 langues), lire les demandes reçues |
| Équipe TICDCE | `/admin` | Valider ou refuser les contenus, inviter les startups, mettre en avant, gérer les secteurs, suivre toutes les demandes |

### Circuit de validation

1. Le TICDCE crée la startup dans `/admin`, puis **invite** ses membres. Ils reçoivent un email pour choisir leur mot de passe.
2. La startup remplit sa fiche et ajoute ses produits dans `/espace`, puis clique sur **« Envoyer pour validation »**.
3. Le TICDCE reçoit un email. Il ouvre l'**aperçu**, puis **approuve** ou **demande des modifications** (avec un motif).
4. La startup est prévenue par email. Tant qu'une modification n'est pas approuvée, **la version publique reste inchangée**.

Il n'y a pas de paiement en ligne : les demandes des visiteurs sont enregistrées et envoyées par email à la startup, avec le TICDCE en copie.

## Technologies

- **Laravel 12** (PHP 8.3) : site public, base de données, emails
- **Filament 4** : les deux espaces privés (`/admin` et `/espace`)
- **MySQL** en production (SQLite possible pour tester)
- Pas de compilation JavaScript : le style du site public est dans `public/css/site.css`

## Démarrage rapide (sur votre ordinateur)

```bash
composer install
composer setup     # configuration + base de données + secteurs
composer demo      # startups et comptes fictifs de test
composer start     # http://localhost:8000
```

Les comptes de démonstration (mot de passe `password`) sont :
- `admin@example.com` pour `/admin`
- `startup@example.com` pour `/espace`

Le guide détaillé, avec l'installation de PHP sous Windows ou macOS, est dans [docs/LOCAL.md](docs/LOCAL.md).

Pour lancer les tests : `php artisan test`

## Documentation

- [Faire tourner en local (Windows, macOS, Linux)](docs/LOCAL.md)
- [Installation sur le serveur Ubuntu 24.04](docs/DEPLOIEMENT.md)
- [Guide d'utilisation (TICDCE et startups)](docs/GUIDE.md)
- [Où modifier quoi dans le code](docs/CODE.md)
