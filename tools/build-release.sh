#!/usr/bin/env bash
#
# Builds the release archive: dist/RefreshGlobal.zip (folder RefreshGlobal/ ready to drop into FreeScout's Modules/)
# with dist/version.txt, dist/module.json, dist/install.sh and dist/SHA256SUMS. The module's tests are left out;
# CHANGELOG, COMPATIBILITY and LICENSE are included. version.txt and RefreshGlobal.zip are the addresses of module.json
# (latestVersionUrl / latestVersionZipUrl) read by FreeScout's Modules page and by the UpdateGuard module, which
# checks the archive against SHA256SUMS.
#
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="${ROOT}/dist"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

version="$(sed -n 's/^[[:space:]]*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$ROOT/RefreshGlobal/module.json" | head -n 1)"
script_version="$(sed -n 's/^SCRIPT_VERSION="\([^"]*\)"/\1/p' "$ROOT/install.sh")"
if [ "$version" != "$script_version" ]; then
    printf 'module.json (%s) et install.sh (%s) n'"'"'ont pas la même version.\n' "$version" "$script_version" >&2
    exit 1
fi
if ! grep -q "^## \[${version}\]" "$ROOT/CHANGELOG.md"; then
    printf 'CHANGELOG.md : entrée [%s] manquante.\n' "$version" >&2
    exit 1
fi

cp -R "$ROOT/RefreshGlobal" "$STAGE/RefreshGlobal"
rm -rf "$STAGE/RefreshGlobal/Tests"
cp "$ROOT/CHANGELOG.md" "$ROOT/COMPATIBILITY.md" "$ROOT/LICENSE" "$STAGE/RefreshGlobal/"
find "$STAGE" -name '.DS_Store' -delete

mkdir -p "$DIST"
rm -f "$DIST/RefreshGlobal.zip" "$DIST/SHA256SUMS" "$DIST/module.json" "$DIST/version.txt"
if command -v zip >/dev/null 2>&1; then
    (cd "$STAGE" && zip -qrX "$DIST/RefreshGlobal.zip" RefreshGlobal)
else
    (cd "$STAGE" && python3 -m zipfile -c "$DIST/RefreshGlobal.zip" RefreshGlobal)
fi
cp "$ROOT/install.sh" "$DIST/install.sh"
# latest version as plain text (latestVersionUrl), and the manifest (required FreeScout version)
printf '%s\n' "$version" >"$DIST/version.txt"
cp "$ROOT/RefreshGlobal/module.json" "$DIST/module.json"
(cd "$DIST" && sha256sum RefreshGlobal.zip install.sh module.json version.txt >SHA256SUMS)
printf 'RefreshGlobal %s : %s\n' "$version" "$DIST/RefreshGlobal.zip"
cat "$DIST/SHA256SUMS"
