#!/usr/bin/env bash
#
# Bring the server up to date with GitHub. Run on the server, inside the project folder:
#     bash deploy.sh            (the branch "main")
#     bash deploy.sh development
#
# The GitHub workflow in .github/workflows/deploy.yml runs this over SSH after every push
# to main. The .env file, the uploaded files in storage/ and the database are never touched
# by Git; new migrations are run.
set -euo pipefail

cd "$(dirname "$0")"
BRANCH="${1:-main}"

# Hostinger keeps Composer 2 under the name "composer2"
if command -v composer2 >/dev/null 2>&1; then COMPOSER=composer2; else COMPOSER=composer; fi

echo "== Fetching $BRANCH"
git fetch --prune origin "$BRANCH"

if [ -f vendor/autoload.php ] && [ -f .env ]; then php artisan down --retry=30 || true; fi

git reset --hard "origin/$BRANCH"

echo "== Installing packages"
$COMPOSER install --no-dev --optimize-autoloader --no-interaction --prefer-dist

if [ ! -f .env ]; then
    echo "!! There is no .env file yet. Copy .env.example to .env, fill it in, run 'php artisan key:generate', then run this script again."
    exit 1
fi

echo "== Database"
php artisan migrate --force

echo "== Caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan up
echo "== Done: $(git log -1 --format='%h %s')"
