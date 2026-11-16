#!/usr/bin/env bash
# Build a self-contained release package for Namecheap shared hosting
# (no Composer or Node required on the server).
# Usage: deploy/build-release.sh            -> release/bobagunwa-<rev>.zip
set -euo pipefail
cd "$(dirname "$0")/.."

if [ -n "$(git status --porcelain)" ]; then
  echo "Refusing to build: commit or stash your changes first (releases must match a revision)." >&2
  exit 1
fi
REV=$(git rev-parse --short=12 HEAD)
OUT="release"
STAGE=$(mktemp -d)
trap 'rm -rf "$STAGE"' EXIT
mkdir -p "$OUT"

git archive HEAD | tar -x -C "$STAGE"
cd "$STAGE"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --optimize-autoloader --classmap-authoritative --no-interaction --no-progress --no-scripts >/dev/null 2>&1 \
  || COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-source --optimize-autoloader --classmap-authoritative --no-interaction --no-progress --no-scripts >/dev/null
find vendor -name .git -type d -prune -exec rm -rf {} +
npm ci --no-audit --no-fund >/dev/null
npm run build >/dev/null
rm -rf node_modules tests phpunit.xml .github .editorconfig .gitattributes .styleci.yml package.json package-lock.json vite.config.js resources/css resources/js docs/evidence/screens
mkdir -p storage/app/private/backups storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
rm -f .env
cat > RELEASE.json <<JSON
{
  "revision": "$REV",
  "built_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "php_required": ">=8.3",
  "database_required": "MariaDB 11.4 (InnoDB)",
  "composer_lock_sha256": "$(sha256sum composer.lock | cut -d' ' -f1)"
}
JSON
cd - >/dev/null
( cd "$STAGE" && zip -qr -X "$OLDPWD/$OUT/bobagunwa-$REV.zip" . )
sha256sum "$OUT/bobagunwa-$REV.zip" | tee "$OUT/bobagunwa-$REV.zip.sha256"
echo "Release package: $OUT/bobagunwa-$REV.zip"
