#!/usr/bin/env bash
# Prove that a release package corresponds to a committed revision.
# Usage: deploy/verify-release.sh release/bobagunwa-<rev>.zip
# Checks: package hash file, per-file MANIFEST, RELEASE.json commit exists in
# this repository, every packaged file that is tracked in Git is byte-identical
# to that commit, every tracked file missing from the package is on the
# documented exclusion list, and no secrets or development artefacts are present.
set -euo pipefail
cd "$(dirname "$0")/.."
ZIP=${1:?usage: deploy/verify-release.sh <release.zip>}
[ -f "$ZIP.sha256" ] && sha256sum -c --quiet "$ZIP.sha256" && echo "PASS package hash ($ZIP.sha256)"
WORK=$(mktemp -d); trap 'rm -rf "$WORK" "$WORK.secrets"' EXIT
unzip -q "$ZIP" -d "$WORK"
( cd "$WORK" && sha256sum -c --quiet MANIFEST.sha256 ) && echo "PASS per-file manifest ($(wc -l < "$WORK/MANIFEST.sha256") files)"
COMMIT=$(php -r 'echo json_decode(file_get_contents($argv[1]), true)["commit"] ?? "";' "$WORK/RELEASE.json")
[ -n "$COMMIT" ] && git cat-file -e "$COMMIT^{commit}" || { echo "FAIL RELEASE.json commit not found in this repository" >&2; exit 1; }
echo "PASS commit $COMMIT exists"
EXCLUDED='^(tests/|phpunit\.xml$|\.github/|\.editorconfig$|\.gitattributes$|\.styleci\.yml$|package\.json$|package-lock\.json$|vite\.config\.js$|resources/css/|resources/js/|docs/evidence/screens/)'
mismatch=0; missing=0
while IFS=$'\t' read -r meta path; do
  blob=$(echo "$meta" | awk '{print $3}')
  if [ -f "$WORK/$path" ]; then
    [ "$(git hash-object "$WORK/$path")" = "$blob" ] || { echo "DIFF $path"; mismatch=$((mismatch+1)); }
  elif ! [[ "$path" =~ $EXCLUDED ]]; then
    echo "MISSING $path"; missing=$((missing+1))
  fi
done < <(git ls-tree -r "$COMMIT")
[ $mismatch -eq 0 ] && [ $missing -eq 0 ] || { echo "FAIL $mismatch changed, $missing unexpectedly missing" >&2; exit 1; }
echo "PASS every tracked file in the package is identical to $COMMIT; only documented exclusions are absent"
bad=$(cd "$WORK" && find . \( -name .env -o -name '*.bobabk' -o -name '*.sql' -o -name '*.key' -o -name '*.pem' -o -name id_rsa -o -name '*.log' -o -path ./node_modules -o -path ./tests -o -name .git \) -print | head -5)
[ -z "$bad" ] || { echo "FAIL forbidden files: $bad" >&2; exit 1; }
if grep -rIlE -e '-----BEGIN [A-Z ]*PRIVATE KEY-----' -e 'ghp_[A-Za-z0-9]{30,}' -e 'AKIA[0-9A-Z]{16}' "$WORK" > "$WORK.secrets" 2>/dev/null; then cat "$WORK.secrets" >&2; echo "FAIL secret-like content found" >&2; exit 1; fi
grep -q '^APP_KEY=$' "$WORK/.env.example" && echo "PASS no .env, keys, dumps, logs, tests or node_modules; .env.example carries no key"
echo "OK $ZIP corresponds to $COMMIT"
