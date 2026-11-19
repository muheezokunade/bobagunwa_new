#!/usr/bin/env bash
# Roll the protected staging site back to the directory kept by a deployment.
#   deploy/staging-rollback.sh <STAMP>        (STAMP printed by staging-deploy.sh)
# The failed release is kept (renamed .failed-<now>) for diagnosis; the staging
# database is not changed. To also restore the database, restore an encrypted
# backup into a fresh database (docs/10-backup-and-recovery.md).
set -euo pipefail
STAMP=${1:?usage: staging-rollback.sh <STAMP>}
ROOT=${BOBA_STAGING_ROOT:-$HOME/bobagunwa-v2-webcheck}
LOGS=${BOBA_DEPLOY_LOGS:-$HOME/bobagunwa-deploy-logs}
RUN="$LOGS/$STAMP"
[ "$(id -u)" -ne 0 ] || { echo "do not run as root" >&2; exit 1; }
[ -f "$RUN/previous-path" ] || { echo "no deployment record $RUN/previous-path" >&2; exit 1; }
PREV=$(cat "$RUN/previous-path")
NOW=$(date -u +%Y%m%dT%H%M%SZ)
if [ -n "$PREV" ] && [ -d "$PREV" ]; then
  mv "$ROOT" "$ROOT.failed-$NOW"
  mv "$PREV" "$ROOT"
  echo "Rolled back: $ROOT restored from $PREV; failed release kept at $ROOT.failed-$NOW" | tee -a "$RUN/deploy.log"
elif [ -f "$RUN/previous-staging.tgz" ]; then
  mv "$ROOT" "$ROOT.failed-$NOW"
  mkdir -p "$ROOT" && tar -xzf "$RUN/previous-staging.tgz" -C "$ROOT"
  echo "Rolled back from snapshot $RUN/previous-staging.tgz (its .env was not in the snapshot: restore it from the config directory if the previous site needs one)" | tee -a "$RUN/deploy.log"
else
  echo "First install had no previous site: moving the release aside leaves the staging docroot empty." >&2
  mv "$ROOT" "$ROOT.failed-$NOW"; mkdir -p "$ROOT/public"
  cp "${BOBA_CONFIG_DIR:-$HOME/bobagunwa-config}/staging-protection.htaccess" "$ROOT/public/.htaccess"
  chmod 711 "$ROOT"; chmod 755 "$ROOT/public"; chmod 644 "$ROOT/public/.htaccess"
  echo "Staging now serves only the protection rules; failed release at $ROOT.failed-$NOW" | tee -a "$RUN/deploy.log"
fi
