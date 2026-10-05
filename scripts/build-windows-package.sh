#!/usr/bin/env bash
# Construit le ZIP "pret a l'emploi" pour Windows : code + bibliotheques + scripts .bat.
# Usage : scripts/build-windows-package.sh [dossier-de-sortie]
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$ROOT/build}"
NAME="ticdce-marketplace-windows"
WORK="$(mktemp -d)"
DIR="$WORK/$NAME"

mkdir -p "$DIR" "$OUT"
git -C "$ROOT" archive HEAD | tar -x -C "$DIR"

cd "$DIR"
cp windows/windows-env.txt .env
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --quiet

# Allege le paquet : historiques git, tests et docs des bibliotheques (inutiles a l'execution)
find vendor -mindepth 3 -maxdepth 3 -type d \( -name .git -o -name tests -o -name test_files -o -name docs -o -name .github \) -prune -exec rm -rf {} +
find vendor -name .git -type d -prune -exec rm -rf {} +
rm -f .env bootstrap/cache/*.php

# Scripts Windows a la racine, avec fins de ligne Windows (CRLF)
for f in demarrer.bat creer-admin.bat sauvegarder.bat autoriser-reseau.bat LISEZMOI.txt windows-env.txt; do
    sed 's/\r\?$/\r/' "windows/$f" > "$f"
done
rm -rf windows tests scripts phpunit.xml .github .editorconfig .gitattributes

cd "$WORK"
rm -f "$OUT/$NAME.zip"
zip -qr9 "$OUT/$NAME.zip" "$NAME"
rm -rf "$WORK"
echo "$OUT/$NAME.zip"
