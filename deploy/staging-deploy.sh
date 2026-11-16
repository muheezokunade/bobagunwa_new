#!/usr/bin/env bash
# Deploy a verified release to the PROTECTED STAGING site only
# (v2-staging.bobagunwa.com). Run over SSH as the cPanel user, never as root.
#
#   deploy/staging-deploy.sh ~/releases/bobagunwa-<rev>.zip
#
# Configuration (no secrets on the command line; nothing secret is printed):
#   BOBA_PHP            PHP 8.3 CLI binary (required), e.g. /opt/cpanel/ea-php83/root/usr/bin/php
#   BOBA_STAGING_ROOT   live staging application directory; cPanel docroot = $BOBA_STAGING_ROOT/public
#                       (default ~/bobagunwa-v2-webcheck)
#   BOBA_CONFIG_DIR     private directory holding staging.env and staging-protection.htaccess
#                       (default ~/bobagunwa-config, mode 700)
#   BOBA_DEPLOY_LOGS    where snapshots and logs go (default ~/bobagunwa-deploy-logs)
#   BOBA_DRY_RUN=1      stop after the preflight and migrations check, without switching
#
# Safety rules enforced here:
#   * refuses root, refuses any target inside public_html or equal to $HOME,
#     refuses unless staging.env says APP_ENV=staging, APP_DEBUG=false and
#     APP_URL=https://v2-staging.bobagunwa.com, and MAIL_MAILER is log/array;
#   * refuses unless a staging protection snippet exists (the 403/IP or password
#     rules), which is placed at the top of public/.htaccess, so the site is
#     never exposed unprotected;
#   * snapshots the existing staging directory (tar) and its .htaccess before
#     any change, and keeps the previous directory for an instant rollback;
#   * aborts on any failed preflight check, migration or ledger verification
#     BEFORE the live directory is switched;
#   * touches nothing outside $BOBA_STAGING_ROOT, ~/releases, the config and log directories.
set -euo pipefail
umask 027

ZIP=${1:?usage: staging-deploy.sh <release.zip>}
PHP=${BOBA_PHP:?set BOBA_PHP to the PHP 8.3 CLI binary}
ROOT=${BOBA_STAGING_ROOT:-$HOME/bobagunwa-v2-webcheck}
CONFIG=${BOBA_CONFIG_DIR:-$HOME/bobagunwa-config}
LOGS=${BOBA_DEPLOY_LOGS:-$HOME/bobagunwa-deploy-logs}
EXPECTED_URL=https://v2-staging.bobagunwa.com
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
RUN="$LOGS/$STAMP"

die() { echo "ABORT: $*" >&2; [ -d "$RUN" ] && echo "ABORT: $*" >> "$RUN/deploy.log"; exit 1; }
log() { echo "$*"; echo "[$(date -u +%H:%M:%S)] $*" >> "$RUN/deploy.log"; }
envval() { grep -E "^$1=" "$CONFIG/staging.env" | tail -1 | cut -d= -f2- | sed -e 's/[[:space:]]*#.*$//' -e 's/^"//' -e 's/"$//'; }

# ---- 0. Guards -------------------------------------------------------------
[ "$(id -u)" -ne 0 ] || die "do not run as root"
ROOT=$(cd "$(dirname "$ROOT")" && pwd)/$(basename "$ROOT")
case "$ROOT" in
  "$HOME"|"$HOME/"|*public_html*|/) die "refusing target $ROOT (must be the dedicated staging directory, never public_html)";;
esac
[ "$(basename "$ROOT")" != "$(basename "$HOME")" ] || die "refusing target $ROOT"
[ -f "$ZIP" ] && [ -f "$ZIP.sha256" ] || die "release zip and its .sha256 are required"
(cd "$(dirname "$ZIP")" && sha256sum -c --quiet "$(basename "$ZIP").sha256") || die "release checksum mismatch"
[ -f "$CONFIG/staging.env" ] || die "missing $CONFIG/staging.env"
[ -f "$CONFIG/staging-protection.htaccess" ] || die "missing $CONFIG/staging-protection.htaccess (staging must stay protected)"
grep -qE '^[[:space:]]*(Require|Deny|Order|AuthType|RewriteRule)' "$CONFIG/staging-protection.htaccess" || die "protection snippet contains no access rules"
[ "$(stat -c %a "$CONFIG/staging.env")" = 600 ] || die "$CONFIG/staging.env must be mode 600"
[ "$(envval APP_ENV)" = staging ] || die "staging.env: APP_ENV must be staging"
[ "$(envval APP_DEBUG)" = false ] || die "staging.env: APP_DEBUG must be false"
[ "$(envval APP_URL)" = "$EXPECTED_URL" ] || die "staging.env: APP_URL must be $EXPECTED_URL"
case "$(envval MAIL_MAILER)" in log|array) ;; *) die "staging.env: MAIL_MAILER must be log or array (no external notifications on staging)";; esac
[ -n "$(envval DB_DATABASE)" ] && [ -n "$(envval DB_USERNAME)" ] || die "staging.env: dedicated DB_DATABASE and DB_USERNAME are required"
"$PHP" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' || die "$PHP is not PHP 8.3+"

mkdir -p "$RUN" "$HOME/releases"; chmod 700 "$LOGS" "$RUN"
log "Staging deployment $STAMP of $(basename "$ZIP") to $ROOT"
log "PHP CLI: $("$PHP" -r 'echo PHP_VERSION;')"

# ---- 1. Snapshot the existing staging resources ---------------------------
if [ -d "$ROOT" ]; then
  ls -la "$ROOT" "$ROOT/public" > "$RUN/previous-listing.txt" 2>&1 || true
  [ -f "$ROOT/public/.htaccess" ] && cp "$ROOT/public/.htaccess" "$RUN/previous-public.htaccess"
  tar --exclude='./storage/framework/sessions/*' --exclude='./storage/framework/cache/*' --exclude='./.env' \
      -czf "$RUN/previous-staging.tgz" -C "$ROOT" . || die "could not snapshot $ROOT"
  log "Snapshot: $RUN/previous-staging.tgz ($(du -h "$RUN/previous-staging.tgz" | cut -f1)); .env excluded from the snapshot"
else
  log "No existing staging directory at $ROOT (first install)"
fi

# ---- 2. Unpack and verify --------------------------------------------------
REL="$HOME/releases/$(basename "$ZIP" .zip)-$STAMP"
mkdir -p "$REL" && unzip -q "$ZIP" -d "$REL"
(cd "$REL" && sha256sum -c --quiet MANIFEST.sha256) || die "unpacked files do not match MANIFEST.sha256"
log "Unpacked to $REL; per-file manifest verified; revision $("$PHP" -r 'echo json_decode(file_get_contents($argv[1]),true)["commit"] ?? "?";' "$REL/RELEASE.json")"

# ---- 3. Configuration (copied, never printed) -----------------------------
cp "$CONFIG/staging.env" "$REL/.env" && chmod 600 "$REL/.env"
if [ -z "$(envval APP_KEY)" ]; then
  KEY=$(cd "$REL" && "$PHP" artisan key:generate --show --no-ansi)
  sed -i "s|^APP_KEY=.*|APP_KEY=$KEY|" "$CONFIG/staging.env" "$REL/.env"; unset KEY
  log "Generated a new staging APP_KEY (stored only in $CONFIG/staging.env)"
fi
BACKUPS=$(envval BOBA_BACKUP_PATH); [ -n "$BACKUPS" ] && mkdir -p "$BACKUPS" && chmod 700 "$BACKUPS"
chmod -R u+rwX,g+rX,o-rwx "$REL/storage" "$REL/bootstrap/cache"

# ---- 4. Host preflight (abort on FAIL) -------------------------------------
cd "$REL"
"$PHP" artisan boba:preflight --json > "$RUN/preflight.json" 2>&1 || true
"$PHP" artisan boba:preflight --no-ansi > "$RUN/preflight.txt" 2>&1 || true
if ! "$PHP" -r '$r=json_decode(file_get_contents($argv[1]),true); if(!is_array($r)) exit(2); $f=0; foreach($r as $c){ if(empty($c["ok"]) && !empty($c["required"])) { fwrite(STDERR,"FAIL ".$c["check"].": ".$c["detail"]."\n"); $f=1; } } exit($f);' "$RUN/preflight.json"; then
  die "preflight failed — see $RUN/preflight.txt (nothing was switched)"
fi
WARNS=$("$PHP" -r '$n=0; foreach(json_decode(file_get_contents($argv[1]),true) as $c){ if(empty($c["ok"]) && empty($c["required"])) $n++; } echo $n;' "$RUN/preflight.json")
log "Preflight: no required check failed ($WARNS advisory warning(s)) — $RUN/preflight.txt"

# ---- 5. Database ------------------------------------------------------------
"$PHP" artisan migrate --force --no-ansi >> "$RUN/deploy.log" 2>&1 || die "migration failed (nothing was switched)"
"$PHP" artisan boba:ledger:verify --trigger=manual --no-ansi >> "$RUN/deploy.log" 2>&1 || die "ledger verification failed (nothing was switched)"
"$PHP" artisan config:cache --no-ansi >/dev/null && "$PHP" artisan route:cache --no-ansi >/dev/null && "$PHP" artisan view:cache --no-ansi >/dev/null
log "Migrated; integrity triggers and ledger verified; caches built"

# ---- 6. Keep staging protected --------------------------------------------
{ echo "# --- staging protection (from $CONFIG/staging-protection.htaccess, $STAMP) ---"
  cat "$CONFIG/staging-protection.htaccess"
  echo "# --- end staging protection ---"; echo
  cat "$REL/public/.htaccess"; } > "$REL/public/.htaccess.new" && mv "$REL/public/.htaccess.new" "$REL/public/.htaccess"
log "Protection rules placed at the top of public/.htaccess"

if [ "${BOBA_DRY_RUN:-0}" = 1 ]; then
  log "DRY RUN: release prepared at $REL; live directory not switched"; exit 0
fi

# ---- 7. Switch (atomic rename within the home filesystem) -----------------
PREV=""
if [ -d "$ROOT" ]; then PREV="$ROOT.previous-$STAMP"; mv "$ROOT" "$PREV"; fi
mv "$REL" "$ROOT"
echo "$PREV" > "$RUN/previous-path"
log "Switched: $ROOT now serves the new release${PREV:+; previous kept at $PREV}"
log "Rollback: deploy/staging-rollback.sh $STAMP"
log "Next: verify https://v2-staging.bobagunwa.com from an allowed address (valid certificate required), install cron, run the acceptance checks."
