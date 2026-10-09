#!/usr/bin/env bash
#
# Full installation test on a blank Ubuntu container: install.sh --full runs FreeScout's official installer
# (answers given through a pseudo-terminal), then the web setup is emulated (.env + migrations + admin), then
# install.sh is run again to add RefreshGlobal (with Refresh from --refresh-zip).
#
#   REFRESH_ZIP=/path/Refresh.zip MODULE_ZIP=/path/RefreshGlobal.zip bash tests/installer/full_install_test.sh
#
# Limits of a container: no systemd (services are started with "service"), no HTTPS (answered "n").
set -Eeuo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WORK="${WORK:-$(mktemp -d)}"
RESULTS="${RESULTS:-${ROOT}/tests/installer/last-run.md}"
C=rgi-full
OS_IMAGE="${OS_IMAGE:-ubuntu:24.04}"
MODULE_ZIP="${MODULE_ZIP:-$WORK/RefreshGlobal-1.0.0.zip}"
REFRESH_ZIP="${REFRESH_ZIP:-$WORK/Refresh.zip}"
S="Installation complète (${OS_IMAGE} vierge, --full)"

record() {
    printf '| %s | %s | %s | %s |\n' "$S" "$1" "$2" "${3:-}" >>"$RESULTS"
    printf '  [%s] %s %s\n' "$2" "$1" "${3:-}" >&2
}

if [ ! -f "$REFRESH_ZIP" ] && [ -n "${REFRESH_SRC:-}" ]; then
    (cd "$(dirname "$REFRESH_SRC")" && python3 - "$REFRESH_SRC" "$REFRESH_ZIP" <<'PY'
import os, sys, zipfile
src, out = sys.argv[1], sys.argv[2]
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
    for base, dirs, files in os.walk(src):
        dirs[:] = [d for d in dirs if d != '.git']
        for f in files:
            p = os.path.join(base, f)
            z.write(p, os.path.join('Refresh', os.path.relpath(p, src)))
PY
    )
fi

docker rm -f "$C" >/dev/null 2>&1 || true
# --init: a real init reaps finished processes (the mysql-server package waits for its temporary mysqld to exit)
docker run -d --init --name "$C" "$OS_IMAGE" sleep 7200 >/dev/null
# a standard server has sudo; expect answers the official installer's questions by their text (answers.exp)
docker exec "$C" bash -c 'apt-get update -qq >/dev/null && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq sudo expect >/dev/null'
docker cp "$ROOT/tests/installer/answers.exp" "$C:/root/answers.exp"
docker cp "$ROOT/install.sh" "$C:/root/install.sh"
docker cp "$MODULE_ZIP" "$C:/root/RefreshGlobal.zip"
docker cp "$REFRESH_ZIP" "$C:/root/Refresh.zip"

# Official installer questions (tools/install.sh): answered by expect from their text
RC=0
docker exec -e NO_COLOR=1 "$C" expect /root/answers.exp helpdesk.example.test bash /root/install.sh --full --yes --path=/var/www/html --source=/root/RefreshGlobal.zip --refresh-zip=/root/Refresh.zip \
    >"$WORK/full1.txt" 2>&1 || RC=$?
[ "$RC" = 0 ] && record "1er passage : code de sortie 0" PASS || record "1er passage : code de sortie 0" FAIL "exit $RC"
docker exec "$C" test -f /var/www/html/artisan && record "FreeScout installé par le script officiel" PASS || record "FreeScout installé" FAIL
grep -q "Terminer l'installation dans le navigateur" "$WORK/full1.txt" && record "arrêt propre en attente de l'installation web (--yes)" PASS || record "attente installation web" FAIL
docker exec "$C" sh -c '! grep -q "Database Password" /var/log/refreshglobal-install.log' && record "mot de passe du script officiel absent du journal" PASS || record "mot de passe absent du journal" FAIL

# Emulates FreeScout's web setup (/install). Without systemd the MySQL server may not have been running during the
# official installer: it is started here and the database / user it should have created are ensured.
PASS_DB="test-$(date +%s)"
docker exec "$C" bash -c "service mysql start >/dev/null 2>&1 || service mariadb start >/dev/null 2>&1 || true"
# systemd creates /run/mysqld with mode 0755 (mysql.service: RuntimeDirectory=mysqld); in a container the package
# leaves it 0700, so PHP (www-data) could not reach the socket. Same state as a real server:
docker exec "$C" chmod 755 /var/run/mysqld
if docker exec "$C" mysql -u root -N -e "SHOW DATABASES LIKE 'freescout'" 2>/dev/null | grep -q freescout; then
    record "base « freescout » créée par le script officiel" PASS
else
    record "base « freescout » créée par le script officiel" FAIL "MySQL non démarré pendant le script (conteneur sans systemd) : créée par le test"
fi
docker exec "$C" mysql -u root -e "CREATE DATABASE IF NOT EXISTS freescout CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS 'freescout'@'localhost' IDENTIFIED BY '${PASS_DB}'; ALTER USER 'freescout'@'localhost' IDENTIFIED BY '${PASS_DB}'; GRANT ALL ON freescout.* TO 'freescout'@'localhost'; FLUSH PRIVILEGES;"
docker exec "$C" bash -c "cat > /var/www/html/.env <<EOF
APP_URL=http://helpdesk.example.test
APP_ENV=production
APP_DEBUG=false
APP_KEY=
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=freescout
DB_USERNAME=freescout
DB_PASSWORD=${PASS_DB}
EOF
cd /var/www/html && chown www-data:www-data .env && sudo -u www-data php artisan key:generate --force >/dev/null \
 && sudo -u www-data php artisan migrate --force >/dev/null \
 && sudo -u www-data php artisan freescout:create-user --role=admin --firstName=Test --lastName=Admin --email=admin@example.test --password=Admin-test-1 --no-interaction >/dev/null" \
    && record "installation web émulée (.env, migrations, administrateur)" PASS || record "installation web émulée" FAIL

# Second run: same command, FreeScout is now configured
RC=0
docker exec -e NO_COLOR=1 "$C" expect /root/answers.exp helpdesk.example.test bash /root/install.sh --full --yes --path=/var/www/html --source=/root/RefreshGlobal.zip --refresh-zip=/root/Refresh.zip \
    >"$WORK/full2.txt" 2>&1 || RC=$?
[ "$RC" = 0 ] && record "2e passage : code de sortie 0" PASS || record "2e passage : code de sortie 0" FAIL "exit $RC — $(tail -n 5 "$WORK/full2.txt" | tr '\n' ' ')"
grep -q "\[10/10\] Résumé" "$WORK/full2.txt" && record "étapes [1/10] à [10/10]" PASS || record "étapes [1/10] à [10/10]" FAIL
grep -q "Refresh 1\." "$WORK/full2.txt" && record "Refresh installé depuis --refresh-zip" PASS || record "Refresh installé" FAIL
grep -q "State: OK" "$WORK/full2.txt" && record "refreshglobal:check : OK" PASS || record "refreshglobal:check : OK" FAIL

# The page through nginx + php-fpm configured by the official installer
docker exec "$C" bash -c 'for s in $(ls /etc/init.d | grep -E "php.*fpm|nginx"); do service "$s" start >/dev/null 2>&1 || true; done'
CODE="$(docker exec "$C" bash -c "rm -f /tmp/cj; t=\$(curl -s -H 'Host: helpdesk.example.test' -c /tmp/cj -b /tmp/cj http://127.0.0.1/login | grep -o 'name=\"_token\" value=\"[^\"]*\"' | head -1 | sed 's/.*value=\"//;s/\"//'); \
  curl -s -H 'Host: helpdesk.example.test' -c /tmp/cj -b /tmp/cj -o /dev/null --data-urlencode \"_token=\$t\" --data-urlencode email=admin@example.test --data-urlencode password=Admin-test-1 http://127.0.0.1/login; \
  curl -s -H 'Host: helpdesk.example.test' -b /tmp/cj -o /tmp/page.html -w '%{http_code}' http://127.0.0.1/refresh-global/tickets" || true)"
[ "$CODE" = 200 ] && record "page « Toutes les boîtes » servie par nginx (200)" PASS || record "page servie par nginx" FAIL "HTTP $CODE"
# full1.txt / full2.txt stay in $WORK: they contain the official installer's output (test database password)
printf '  outputs: %s/full1.txt %s/full2.txt\n' "$WORK" "$WORK" >&2
[ -n "${KEEP:-}" ] || docker rm -f "$C" >/dev/null
