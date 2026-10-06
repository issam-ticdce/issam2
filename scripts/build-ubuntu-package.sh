#!/usr/bin/env bash
# Construit l'archive "pret a installer" pour Ubuntu 24.04 : code + bibliotheques + installateur.
# Usage : scripts/build-ubuntu-package.sh [dossier-de-sortie]
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$ROOT/build}"
NAME="ticdce-marketplace-ubuntu"
WORK="$(mktemp -d)"
DIR="$WORK/$NAME"

mkdir -p "$DIR" "$OUT"
git -C "$ROOT" archive HEAD | tar -x -C "$DIR"

cd "$DIR"
cp .env.example .env
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --quiet
rm -f .env bootstrap/cache/*.php

# Allege le paquet : historiques git, tests et docs des bibliotheques (inutiles a l'execution)
find vendor -mindepth 3 -maxdepth 3 -type d \( -name .git -o -name tests -o -name test_files -o -name docs -o -name .github \) -prune -exec rm -rf {} +
find vendor -name .git -type d -prune -exec rm -rf {} +
rm -rf vendor/laravel/framework/bin vendor/nunomaduro/termwind/art
find vendor -type f \( -name 'browser_test_*' -o -name '*.md' -o -name 'CHANGELOG*' -o -name 'phpunit.xml*' -o -name '.editorconfig' \) -delete

cp ubuntu/LISEZMOI.txt LISEZMOI.txt
rm -rf windows tests scripts phpunit.xml serve.php .editorconfig .gitattributes

cd "$WORK"
rm -f "$OUT/$NAME.tar.gz"
tar czf "$OUT/$NAME.tar.gz" "$NAME"
rm -rf "$WORK"
echo "$OUT/$NAME.tar.gz"
