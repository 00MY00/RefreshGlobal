#!/usr/bin/env bash
#
# Tests of install.sh in disposable Docker containers (Linux host with Docker).
#
#   FREESCOUT_SRC=/path/to/freescout REFRESH_SRC=/path/to/Refresh bash tests/installer/run_tests.sh [scenario...]
#
# Without FREESCOUT_SRC / REFRESH_SRC the official repositories are cloned:
#   https://github.com/freescout-helpdesk/freescout  and  https://github.com/altmenorg/freescout-refresh
# Scenarios: existing-refresh existing-norefresh idempotent update rollback dryrun uninstall fail-nophp fail-path full
# The results are written to tests/installer/last-run.md (copied by hand into tests/RESULTS.md).
#
set -Eeuo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WORK="${WORK:-$(mktemp -d)}"
IMAGE="rg-installer-test"
NET="rgtest"
RESULTS="${ROOT}/tests/installer/last-run.md"
SCENARIOS=("$@")
[ "${#SCENARIOS[@]}" -gt 0 ] || SCENARIOS=(existing-refresh existing-norefresh idempotent update rollback dryrun uninstall fail-nophp fail-path)
PASS=0; FAILED=0

log() { printf '%s\n' "$*" >&2; }
record() {
    # record "scenario" "check" PASS|FAIL "details"
    printf '| %s | %s | %s | %s |\n' "$1" "$2" "$3" "${4:-}" >>"$RESULTS"
    if [ "$3" = "PASS" ]; then PASS=$((PASS + 1)); else FAILED=$((FAILED + 1)); fi
    log "  [$3] $1 — $2 ${4:-}"
}

prepare_sources() {
    if [ -z "${FREESCOUT_SRC:-}" ]; then
        FREESCOUT_SRC="$WORK/freescout"
        [ -d "$FREESCOUT_SRC" ] || git clone -q --depth 1 https://github.com/freescout-helpdesk/freescout.git "$FREESCOUT_SRC"
    fi
    if [ -z "${REFRESH_SRC:-}" ]; then
        REFRESH_SRC="$WORK/Refresh"
        [ -d "$REFRESH_SRC" ] || git clone -q --depth 1 https://github.com/altmenorg/freescout-refresh.git "$REFRESH_SRC"
    fi
    docker image inspect "$IMAGE" >/dev/null 2>&1 || docker build -q -t "$IMAGE" "$ROOT/tests/installer" >/dev/null
    docker network inspect "$NET" >/dev/null 2>&1 || docker network create "$NET" >/dev/null
}

build_zip() {
    # build_zip VERSION -> $WORK/RefreshGlobal-VERSION.zip (same layout as the release archive)
    local version=$1 dir="$WORK/zip-$1"
    rm -rf "$dir"; mkdir -p "$dir"
    cp -R "$ROOT/RefreshGlobal" "$dir/RefreshGlobal"
    rm -rf "$dir/RefreshGlobal/Tests"
    sed -i "s/\"version\": \"[^\"]*\"/\"version\": \"${version}\"/" "$dir/RefreshGlobal/module.json"
    rm -f "$WORK/RefreshGlobal-${version}.zip"
    if command -v zip >/dev/null 2>&1; then
        (cd "$dir" && zip -qr "$WORK/RefreshGlobal-${version}.zip" RefreshGlobal)
    else
        (cd "$dir" && python3 -m zipfile -c "$WORK/RefreshGlobal-${version}.zip" RefreshGlobal)
    fi
}

new_instance() {
    # new_instance NAME with_refresh(0|1)
    local name=$1 with_refresh=$2
    docker rm -f "$name-db" "$name-app" >/dev/null 2>&1 || true
    docker run -d --name "$name-db" --network "$NET" -e MARIADB_ROOT_PASSWORD=root-test -e MARIADB_DATABASE=freescout \
        -e MARIADB_USER=freescout -e MARIADB_PASSWORD=db-test-password mariadb:10.11 >/dev/null
    docker run -d --name "$name-app" --network "$NET" "$IMAGE" >/dev/null
    docker exec "$name-app" rm -rf /var/www/html
    docker cp "$FREESCOUT_SRC" "$name-app:/var/www/html"
    docker exec "$name-app" bash -c "rm -rf /var/www/html/.git; cat > /var/www/html/.env <<EOF
APP_URL=http://localhost
APP_ENV=production
APP_DEBUG=false
APP_KEY=
DB_CONNECTION=mysql
DB_HOST=$name-db
DB_PORT=3306
DB_DATABASE=freescout
DB_USERNAME=freescout
DB_PASSWORD=db-test-password
EOF
chown -R www-data:www-data /var/www/html"
    local i
    for i in $(seq 1 60); do
        docker exec "$name-db" mariadb -ufreescout -pdb-test-password -e 'select 1' freescout >/dev/null 2>&1 && break
        sleep 2
    done
    docker exec "$name-app" bash -c "cd /var/www/html && sudo -u www-data php artisan key:generate --force >/dev/null \
        && sudo -u www-data php artisan migrate --force >/dev/null \
        && sudo -u www-data php artisan freescout:create-user --role=admin --firstName=Test --lastName=Admin \
           --email=admin@example.test --password=Admin-test-1 --no-interaction >/dev/null"
    if [ "$with_refresh" = 1 ]; then
        docker cp "$REFRESH_SRC" "$name-app:/var/www/html/Modules/Refresh"
        docker exec "$name-app" bash -c "cd /var/www/html && rm -rf Modules/Refresh/.git && chown -R www-data:www-data Modules \
            && sudo -u www-data php artisan cache:clear >/dev/null && sudo -u www-data php artisan module:enable Refresh >/dev/null \
            && sudo -u www-data php artisan freescout:module-install refresh >/dev/null"
    fi
    docker exec "$name-app" bash -c "cd /var/www/html && sudo -u www-data php artisan freescout:clear-cache >/dev/null"
}

installer() {
    # installer NAME args... -> output in $WORK/out.txt, exit code in $RC
    local name=$1; shift
    docker cp "$ROOT/install.sh" "$name-app:/tmp/install.sh"
    docker cp "$WORK/RefreshGlobal-1.0.0.zip" "$name-app:/tmp/RefreshGlobal-1.0.0.zip"
    docker cp "$WORK/RefreshGlobal-1.0.1.zip" "$name-app:/tmp/RefreshGlobal-1.0.1.zip"
    RC=0
    docker exec -e NO_COLOR=1 "$name-app" bash /tmp/install.sh "$@" >"$WORK/out.txt" 2>&1 || RC=$?
    log "    install.sh $* -> exit $RC"
}

out_has() { grep -qF -- "$1" "$WORK/out.txt"; }

page() {
    # page NAME PATH -> HTTP status of PATH for the admin (login through the form)
    docker exec "$1" bash -c "rm -f /tmp/cj; t=\$(curl -s -c /tmp/cj -b /tmp/cj http://localhost/login | grep -o 'name=\"_token\" value=\"[^\"]*\"' | head -1 | sed 's/.*value=\"//;s/\"//'); \
        curl -s -c /tmp/cj -b /tmp/cj -o /dev/null --data-urlencode \"_token=\$t\" --data-urlencode email=admin@example.test --data-urlencode password=Admin-test-1 http://localhost/login; \
        curl -s -b /tmp/cj -o /tmp/page.html -w '%{http_code}' http://localhost$2"
}

db() { docker exec "$1-db" mariadb -ufreescout -pdb-test-password -N -B freescout -e "$2"; }

# ------------------------------------------------------------------------------------------------ scenarios ----
s_existing_refresh() {
    local s="Ajout sur FreeScout existant avec Refresh"
    new_instance rgi1 1
    installer rgi1 --source=/tmp/RefreshGlobal-1.0.0.zip --yes
    [ "$RC" = 0 ] && record "$s" "code de sortie 0" PASS || record "$s" "code de sortie 0" FAIL "exit $RC"
    out_has "[7/7] Résumé" && record "$s" "étapes numérotées jusqu'à [7/7]" PASS || record "$s" "étapes numérotées" FAIL
    out_has "State: OK" && record "$s" "refreshglobal:check : OK" PASS || record "$s" "refreshglobal:check : OK" FAIL
    [ "$(page rgi1-app /refresh-global/tickets)" = 200 ] && record "$s" "page /refresh-global/tickets = 200" PASS || record "$s" "page 200" FAIL
    docker exec rgi1-app grep -q 'data-rg-skin="refresh"' /tmp/page.html && record "$s" "style Refresh utilisé" PASS || record "$s" "style Refresh" FAIL
    docker exec rgi1-app test -L /var/www/html/public/modules/refreshglobal && record "$s" "lien public créé" PASS || record "$s" "lien public" FAIL
    [ "$(docker exec rgi1-app stat -c %U /var/www/html/Modules/RefreshGlobal/module.json)" = "www-data" ] && record "$s" "propriétaire www-data" PASS || record "$s" "propriétaire" FAIL
    [ -n "$(docker exec rgi1-app sh -c 'ls /var/backups/refreshglobal/latest/database.sql.gz' 2>/dev/null)" ] && record "$s" "sauvegarde de la base créée" PASS || record "$s" "sauvegarde base" FAIL
    docker exec rgi1-app sh -c '! grep -q "db-test-password" /var/log/refreshglobal-install.log' && record "$s" "aucun mot de passe dans le journal" PASS || record "$s" "mot de passe absent du journal" FAIL
}

s_existing_norefresh() {
    local s="Ajout sur FreeScout existant sans Refresh"
    new_instance rgi2 0
    installer rgi2 --source=/tmp/RefreshGlobal-1.0.0.zip --yes
    [ "$RC" = 0 ] && record "$s" "code de sortie 0" PASS || record "$s" "code de sortie 0" FAIL "exit $RC"
    out_has "Refresh absent" && record "$s" "avertissement « Refresh absent » affiché" PASS || record "$s" "avertissement Refresh absent" FAIL
    out_has "[RG-REF-01]" && record "$s" "rapport : RG-REF-01 en échec" PASS || record "$s" "RG-REF-01" FAIL
    out_has "DÉGRADÉ" && record "$s" "état dégradé annoncé dans le résumé" PASS || record "$s" "état dégradé" FAIL
    [ "$(page rgi2-app /refresh-global/tickets)" = 200 ] && record "$s" "page = 200" PASS || record "$s" "page 200" FAIL
    docker exec rgi2-app grep -q 'data-rg-skin="native"' /tmp/page.html && record "$s" "style FreeScout standard" PASS || record "$s" "style standard" FAIL
}

s_idempotent() {
    local s="Relance du script (idempotence)"
    local before after
    before="$(docker exec rgi1-app sh -c 'ls /var/backups/refreshglobal | wc -l')"
    installer rgi1 --source=/tmp/RefreshGlobal-1.0.0.zip --yes
    after="$(docker exec rgi1-app sh -c 'ls /var/backups/refreshglobal | wc -l')"
    [ "$RC" = 0 ] && record "$s" "code de sortie 0" PASS || record "$s" "code de sortie 0" FAIL "exit $RC"
    out_has "déjà installée et active" && record "$s" "détecte la version déjà installée" PASS || record "$s" "détection" FAIL
    [ "$before" = "$after" ] && record "$s" "aucune sauvegarde ni copie en double" PASS || record "$s" "pas de doublon" FAIL "$before -> $after"
    [ "$(db rgi1 "select count(*) from modules where alias='refreshglobal'")" = 1 ] && record "$s" "une seule ligne dans la table modules" PASS || record "$s" "table modules" FAIL
    [ "$(db rgi1 "select count(*) from migrations where migration like '%refreshglobal%'")" = 1 ] && record "$s" "migration enregistrée une seule fois" PASS || record "$s" "migration unique" FAIL
}

s_update() {
    local s="--update (1.0.0 -> 1.0.1)"
    db rgi1 "insert into refreshglobal_saved_views (user_id,name,filters,is_default,created_at,updated_at) values (1,'Vue de test','{\"mailboxes\":[]}',0,now(),now())"
    installer rgi1 --update --source=/tmp/RefreshGlobal-1.0.1.zip --yes
    [ "$RC" = 0 ] && record "$s" "code de sortie 0" PASS || record "$s" "code de sortie 0" FAIL "exit $RC"
    [ "$(docker exec rgi1-app grep -c '"version": "1.0.1"' /var/www/html/Modules/RefreshGlobal/module.json)" = 1 ] && record "$s" "version 1.0.1 installée" PASS || record "$s" "version 1.0.1" FAIL
    [ "$(db rgi1 "select count(*) from refreshglobal_saved_views where name='Vue de test'")" = 1 ] && record "$s" "vues enregistrées conservées" PASS || record "$s" "vues conservées" FAIL
    [ "$(page rgi1-app /refresh-global/tickets)" = 200 ] && record "$s" "page = 200" PASS || record "$s" "page 200" FAIL
}

s_rollback() {
    local s="--rollback (retour à 1.0.0)"
    installer rgi1 --rollback --yes
    [ "$RC" = 0 ] && record "$s" "code de sortie 0" PASS || record "$s" "code de sortie 0" FAIL "exit $RC"
    [ "$(docker exec rgi1-app grep -c '"version": "1.0.0"' /var/www/html/Modules/RefreshGlobal/module.json)" = 1 ] && record "$s" "version 1.0.0 restaurée" PASS || record "$s" "version restaurée" FAIL
    [ "$(db rgi1 "select count(*) from refreshglobal_saved_views where name='Vue de test'")" = 1 ] && record "$s" "table du module restaurée" PASS || record "$s" "table restaurée" FAIL
    [ "$(page rgi1-app /refresh-global/tickets)" = 200 ] && record "$s" "page = 200" PASS || record "$s" "page 200" FAIL
}

s_dryrun() {
    local s="--dry-run"
    local before after
    new_instance rgi3 0
    before="$(docker exec rgi3-app sh -c 'ls /var/www/html/Modules; ls /var/backups 2>/dev/null; true' | md5sum)"
    installer rgi3 --dry-run --source=/tmp/RefreshGlobal-1.0.0.zip
    after="$(docker exec rgi3-app sh -c 'ls /var/www/html/Modules; ls /var/backups 2>/dev/null; true' | md5sum)"
    [ "$RC" = 0 ] && record "$s" "code de sortie 0" PASS || record "$s" "code de sortie 0" FAIL "exit $RC"
    out_has "[dry-run]" && out_has "module:enable RefreshGlobal" && record "$s" "actions affichées" PASS || record "$s" "actions affichées" FAIL
    [ "$before" = "$after" ] && record "$s" "rien n'est modifié (Modules, sauvegardes)" PASS || record "$s" "rien modifié" FAIL
    [ "$(db rgi3 "select count(*) from modules where alias='refreshglobal'")" = 0 ] && record "$s" "module non activé" PASS || record "$s" "module non activé" FAIL
    docker exec rgi3-app test ! -e /var/log/refreshglobal-install.log && record "$s" "aucun journal écrit" PASS || record "$s" "pas de journal" FAIL
}

s_uninstall() {
    local s="--uninstall"
    installer rgi2 --uninstall --yes
    [ "$RC" = 0 ] && record "$s" "--yes : code de sortie 0" PASS || record "$s" "code de sortie 0" FAIL "exit $RC"
    [ "$(db rgi2 "select active from modules where alias='refreshglobal'")" = 0 ] && record "$s" "module désactivé" PASS || record "$s" "module désactivé" FAIL
    [ "$(db rgi2 "show tables like 'refreshglobal_saved_views'")" = refreshglobal_saved_views ] && record "$s" "--yes sans --drop-tables : table conservée" PASS || record "$s" "table conservée" FAIL
    [ "$(page rgi2-app /refresh-global/tickets)" = 404 ] && record "$s" "page retirée (404)" PASS || record "$s" "page 404" FAIL
    [ "$(page rgi2-app /)" = 200 ] && record "$s" "FreeScout fonctionne toujours (tableau de bord 200)" PASS || record "$s" "FreeScout OK" FAIL
    installer rgi2 --uninstall --yes --drop-tables --remove-files
    [ "$RC" = 0 ] && record "$s" "--drop-tables --remove-files : code 0" PASS || record "$s" "code 0" FAIL "exit $RC"
    [ -z "$(db rgi2 "show tables like 'refreshglobal_saved_views'")" ] && record "$s" "table supprimée (migration réversible)" PASS || record "$s" "table supprimée" FAIL
    docker exec rgi2-app test ! -e /var/www/html/Modules/RefreshGlobal && record "$s" "fichiers supprimés" PASS || record "$s" "fichiers supprimés" FAIL
    docker exec rgi2-app test ! -e /var/www/html/public/modules/refreshglobal && record "$s" "lien public supprimé" PASS || record "$s" "lien public supprimé" FAIL
}

s_fail_nophp() {
    local s="Échec volontaire : PHP absent"
    docker rm -f rgi-nophp >/dev/null 2>&1 || true
    docker run -d --name rgi-nophp debian:bookworm-slim sleep 600 >/dev/null
    docker exec rgi-nophp sh -c 'apt-get update -qq >/dev/null && apt-get install -y -qq unzip curl >/dev/null'
    docker cp "$ROOT/install.sh" rgi-nophp:/tmp/install.sh
    RC=0; docker exec -e NO_COLOR=1 rgi-nophp bash /tmp/install.sh --yes >"$WORK/out.txt" 2>&1 || RC=$?
    [ "$RC" = 1 ] && record "$s" "arrêt avec code 1" PASS || record "$s" "code 1" FAIL "exit $RC"
    out_has "Échec à l'étape « Contrôles préalables » : PHP (ligne de commande) est introuvable." && record "$s" "message : étape + cause" PASS || record "$s" "message" FAIL "$(head -c 300 "$WORK/out.txt")"
    out_has "Installer PHP" && record "$s" "message : action proposée" PASS || record "$s" "action proposée" FAIL
    docker rm -f rgi-nophp >/dev/null
}

s_fail_path() {
    local s="Échec volontaire : mauvais chemin"
    docker inspect rgi3-app >/dev/null 2>&1 || new_instance rgi3 0
    installer rgi3 --path=/var/www/nope --source=/tmp/RefreshGlobal-1.0.0.zip --yes
    [ "$RC" = 1 ] && record "$s" "arrêt avec code 1" PASS || record "$s" "code 1" FAIL "exit $RC"
    out_has "« /var/www/nope » n'est pas une installation FreeScout" && record "$s" "message explicite" PASS || record "$s" "message" FAIL "$(head -c 300 "$WORK/out.txt")"
    out_has "Vérifier le chemin donné à --path." && record "$s" "action proposée" PASS || record "$s" "action" FAIL
}

main() {
    printf '# Résultats install.sh — %s\n\n| Scénario | Vérification | Résultat | Détail |\n|---|---|---|---|\n' "$(date '+%Y-%m-%d %H:%M')" >"$RESULTS"
    prepare_sources
    build_zip 1.0.0
    build_zip 1.0.1
    local sc
    for sc in "${SCENARIOS[@]}"; do
        log "== $sc"
        case "$sc" in
            existing-refresh) s_existing_refresh ;;
            existing-norefresh) s_existing_norefresh ;;
            idempotent) s_idempotent ;;
            update) s_update ;;
            rollback) s_rollback ;;
            dryrun) s_dryrun ;;
            uninstall) s_uninstall ;;
            fail-nophp) s_fail_nophp ;;
            fail-path) s_fail_path ;;
            full) bash "$ROOT/tests/installer/full_install_test.sh" ;;
            *) log "scénario inconnu : $sc" ;;
        esac
    done
    printf '\n**%s vérifications réussies, %s en échec.**\n' "$PASS" "$FAILED" >>"$RESULTS"
    log "== $PASS PASS / $FAILED FAIL — $RESULTS"
    if [ -z "${KEEP:-}" ]; then
        docker rm -f rgi1-db rgi1-app rgi2-db rgi2-app rgi3-db rgi3-app >/dev/null 2>&1 || true
    fi
    [ "$FAILED" = 0 ]
}

main
