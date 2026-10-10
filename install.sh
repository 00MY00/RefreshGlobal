#!/usr/bin/env bash
#
# RefreshGlobal — installation en une commande du module « Toutes les boîtes » pour FreeScout.
#
#   Ajout sur un FreeScout existant :
#     curl -fsSL <RAW_URL>/install.sh | sudo bash
#   Installation complète (FreeScout + RefreshGlobal, + Refresh si fourni) :
#     curl -fsSL <RAW_URL>/install.sh | sudo bash -s -- --full [--refresh-zip=/chemin/Refresh.zip]
#
# Lire le script avant de l'exécuter est recommandé : bash install.sh --help
#
# Sources des commandes utilisées (vérifiées dans le code de FreeScout 1.8.245 et son wiki) :
#   - activation d'un module : l'écran Gérer › Modules appelle \App\Module::setActive() puis
#     « php artisan freescout:module-install <alias> » (app/Http/Controllers/ModulesController.php:252-257) ;
#     en ligne de commande, « php artisan module:enable <Nom> » appelle le même \App\Module::setActive()
#     (overrides/nwidart/laravel-modules/src/Module.php:435).
#   - freescout:module-install = migrations du module + lien public/modules/<alias> + vidage des caches
#     (app/Console/Commands/ModuleInstall.php:17,82-86).
#   - freescout:clear-cache (app/Console/Commands/ClearCache.php:14).
#   - désactivation : « php artisan module:disable <Nom> » (vendor/nwidart/laravel-modules/src/Commands/DisableCommand.php:15),
#     suppression des tables : « php artisan module:migrate-rollback <Nom> » (…/MigrateRollbackCommand.php:20).
#   - installation manuelle d'un module : décompresser dans Modules/, activer, vider le cache
#     (wiki : https://github.com/freescout-helpdesk/freescout/wiki/FreeScout-Modules).
#   - installation complète : script officiel tools/install.sh, branche « dist »
#     (wiki : https://github.com/freescout-helpdesk/freescout/wiki/Installation-Guide), pour Ubuntu / Debian.
#   - extensions PHP exigées par FreeScout : config/installer.php:28-46.
#
set -Eeuo pipefail   # -E : le piège d'erreur s'applique aussi dans les fonctions

# ---------------------------------------------------------------------------------------------------------------
# Adresse du dépôt Git : à renseigner à la publication (une seule variable, reprise dans le README).
# Peut aussi être donnée par la variable d'environnement RG_REPO_URL.
# ---------------------------------------------------------------------------------------------------------------
REPO_URL="${RG_REPO_URL:-https://github.com/00MY00/RefreshGlobal}"
# When no release is published: archive of the current main branch (no SHA256SUMS for a branch)
BRANCH_ZIP_URL="${RG_BRANCH_ZIP_URL:-${REPO_URL}/archive/refs/heads/main.zip}"

SCRIPT_VERSION="1.5.2"
MODULE_NAME="RefreshGlobal"
MODULE_ALIAS="refreshglobal"
MODULE_TABLE="refreshglobal_saved_views"
LOG_FILE="${RG_LOG_FILE:-/var/log/refreshglobal-install.log}"
BACKUP_ROOT="${RG_BACKUP_ROOT:-/var/backups/refreshglobal}"
FREESCOUT_INSTALLER_URL="https://raw.githubusercontent.com/freescout-helpdesk/freescout/dist/tools/install.sh"
FREESCOUT_PATHS=(/var/www/html /var/www/freescout /var/www/html/freescout /srv/freescout /opt/freescout /www/html /usr/share/nginx/html)
PHP_MIN="7.1.0"   # composer.json de FreeScout : "php": ">=7.1.0"
PHP_EXTENSIONS=(openssl pdo mbstring tokenizer json xml gd fileinfo zip iconv curl dom libxml)

# --------------------------------------------------------------------------------------------------- options ----
MODE="add"            # add | full | update | uninstall | rollback
FS_PATH=""
WANTED_VERSION=""
SOURCE=""
REFRESH_ZIP=""
DRY_RUN=0
ASSUME_YES=0
FORCE=0
NO_DB_BACKUP=0
AUTO_UPDATE=""        # on | off | vide (ne change rien)
AUTO_ROLLBACK=1
MODULE_CHANGED=0
DROP_TABLES=0
REMOVE_FILES=0
ROLLBACK_ID=""

# ----------------------------------------------------------------------------------------------- état interne ---
STEP=0
TOTAL=7
CURRENT_STEP="démarrage"
WEB_USER=""
TMP_DIR=""
BACKUP_DIR=""
FS_VERSION=""
REFRESH_VERSION=""
INSTALLED_VERSION=""
TARGET_VERSION=""
CHECK_STATE=""
CHECK_CODE=0

# ---------------------------------------------------------------------------------------------- affichage -------
if [ -t 1 ] && [ -z "${NO_COLOR:-}" ]; then
    C_RED=$'\033[31m'; C_GREEN=$'\033[32m'; C_YELLOW=$'\033[33m'; C_BLUE=$'\033[34m'; C_BOLD=$'\033[1m'; C_RESET=$'\033[0m'
else
    C_RED=""; C_GREEN=""; C_YELLOW=""; C_BLUE=""; C_BOLD=""; C_RESET=""
fi

# Journal : jamais de mot de passe (les commandes contenant un secret ne sont pas journalisées en clair).
log() {
    if [ -n "${LOG_READY:-}" ]; then
        printf '%s %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" | sed 's/\x1b\[[0-9;]*m//g' >>"$LOG_FILE" 2>/dev/null || true
    fi
}
say() { printf '%s\n' "$*"; log "$*"; }
info() { printf '%s\n' "${C_BLUE}ℹ${C_RESET} $*"; log "INFO $*"; }
warn() { printf '%s\n' "${C_YELLOW}⚠ $*${C_RESET}" >&2; log "WARN $*"; }
fail_msg() { printf '%s\n' "${C_RED}✖ $*${C_RESET}" >&2; log "ERREUR $*"; }

step() {
    STEP=$((STEP + 1))
    CURRENT_STEP="$1"
    printf '%s' "${C_BOLD}[${STEP}/${TOTAL}]${C_RESET} $1... "
    log "[${STEP}/${TOTAL}] $1"
}
step_ok() { printf '%s\n' "${C_GREEN}${1:-OK}${C_RESET}"; log "  -> ${1:-OK}"; }
step_warn() { printf '%s\n' "${C_YELLOW}${1}${C_RESET}"; log "  -> ${1}"; }

die() {
    printf '\n' >&2
    fail_msg "Échec à l'étape « ${CURRENT_STEP} » : $1"
    if [ -n "${2:-}" ]; then
        printf '%s\n' "  → $2" >&2
        log "  -> $2"
    fi
    if [ -n "$BACKUP_DIR" ] && [ "$DRY_RUN" = 0 ]; then
        printf '%s\n' "  → Retour arrière : sudo bash install.sh --rollback --path=${FS_PATH}" >&2
    fi
    printf '%s\n' "  → Journal complet : ${LOG_FILE}" >&2
    exit 1
}

on_error() {
    local code=$1 line=$2
    trap - ERR
    die "commande en erreur (code ${code}, ligne ${line} du script)" "Voir le journal ${LOG_FILE} pour le détail."
}

cleanup() {
    if [ -n "$TMP_DIR" ] && [ -d "$TMP_DIR" ]; then
        rm -rf "$TMP_DIR"
    fi
}

# Exécute (ou affiche en --dry-run) une commande ; sa sortie va dans le journal.
run() {
    if [ "$DRY_RUN" = 1 ]; then
        printf '%s\n' "    ${C_BLUE}[dry-run]${C_RESET} $*"
        log "[dry-run] $*"
        return 0
    fi
    log "\$ $*"
    local out rc=0
    out="$("$@" 2>&1)" || rc=$?
    if [ -n "$out" ]; then
        printf '%s\n' "$out" >>"$LOG_FILE" 2>/dev/null || true
    fi
    if [ "$rc" -ne 0 ]; then
        printf '\n%s\n' "$out" | tail -n 15 >&2
        return "$rc"
    fi
}

# Commande artisan sous l'utilisateur du serveur web (propriétaire des fichiers FreeScout).
artisan() {
    if [ "$(id -u)" = "0" ] && [ "$WEB_USER" != "root" ] && command -v runuser >/dev/null 2>&1; then
        run runuser -u "$WEB_USER" -- php "$FS_PATH/artisan" "$@"
    else
        run php "$FS_PATH/artisan" "$@"
    fi
}

# Comme artisan, mais la sortie est aussi affichée (rapport de compatibilité).
artisan_show() {
    if [ "$DRY_RUN" = 1 ]; then
        run php "$FS_PATH/artisan" "$@"
        return 0
    fi
    log "\$ php artisan $*"
    local rc=0
    if [ "$(id -u)" = "0" ] && [ "$WEB_USER" != "root" ] && command -v runuser >/dev/null 2>&1; then
        runuser -u "$WEB_USER" -- php "$FS_PATH/artisan" "$@" 2>&1 | tee -a "$LOG_FILE" || rc=${PIPESTATUS[0]}
    else
        php "$FS_PATH/artisan" "$@" 2>&1 | tee -a "$LOG_FILE" || rc=${PIPESTATUS[0]}
    fi
    return "$rc"
}

# Question oui/non. Lit le terminal (/dev/tty) : fonctionne aussi avec « curl … | sudo bash ».
ask() {
    local question=$1 default=${2:-n} answer=""
    if [ "$ASSUME_YES" = 1 ]; then
        log "QUESTION ${question} -> oui (--yes)"
        return 0
    fi
    if ! { true </dev/tty; } 2>/dev/null; then
        die "aucun terminal pour poser la question : « ${question} »" "Relancer avec --yes pour le mode non interactif."
    fi
    local hint="[o/N]"
    [ "$default" = "o" ] && hint="[O/n]"
    printf '%s ' "${C_BOLD}?${C_RESET} ${question} ${hint}" >/dev/tty
    read -r answer </dev/tty || answer=""
    answer=${answer:-$default}
    log "QUESTION ${question} -> ${answer}"
    case "$answer" in
        o|O|y|Y|oui|Oui|yes) return 0 ;;
        *) return 1 ;;
    esac
}

version_ge() {
    # vrai si $1 >= $2
    [ "$(printf '%s\n%s\n' "$2" "$1" | sort -V | head -n 1)" = "$2" ]
}

json_version() {
    # "version" d'un module.json, sans dépendre de jq
    sed -n 's/^[[:space:]]*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$1" 2>/dev/null | head -n 1
}

env_value() {
    # valeur d'une clé du .env de FreeScout (guillemets retirés). Ne jamais afficher DB_PASSWORD.
    local key=$1 file="$FS_PATH/.env" line
    line="$(grep -E "^${key}=" "$file" 2>/dev/null | tail -n 1 || true)"
    line="${line#*=}"
    line="${line%\"}"; line="${line#\"}"
    line="${line%\'}"; line="${line#\'}"
    printf '%s' "$line"
}

usage() {
    cat <<EOF
RefreshGlobal ${SCRIPT_VERSION} — installeur du module « Toutes les boîtes » pour FreeScout

Utilisation :
  sudo bash install.sh [options]

Modes :
  (par défaut)          ajoute RefreshGlobal à un FreeScout existant
  --full                installe FreeScout (script officiel), puis Refresh si --refresh-zip, puis RefreshGlobal
  --update              met à jour RefreshGlobal (les vues enregistrées sont conservées)
  --uninstall           désactive le module ; propose de supprimer ses tables et ses fichiers
  --rollback[=ID]       restaure la dernière sauvegarde (ou la sauvegarde ID, voir ${BACKUP_ROOT})

Options :
  --path=/var/www/html  dossier de FreeScout (sinon détection automatique)
  --version=x.y.z       version à installer (sinon la dernière publiée)
  --source=FICHIER|DOSSIER  archive ZIP ou dossier du module à installer, au lieu du téléchargement
  --refresh-zip=FICHIER archive du module Refresh à installer (mode --full uniquement)
  --no-db-backup        ne pas sauvegarder la base de données (déconseillé)
  --drop-tables         avec --uninstall : supprimer la table du module sans le demander
  --remove-files        avec --uninstall : supprimer les fichiers du module sans le demander
  --force               réinstaller même si la même version est déjà installée
  --auto-update=on|off  activer / désactiver la mise à jour automatique quotidienne du module (avec retour arrière)
  --no-auto-rollback    ne pas revenir automatiquement à l'ancienne version si la nouvelle est bloquante
  --dry-run             afficher toutes les actions sans rien exécuter
  --yes                 mode non interactif (répond oui ; garde les tables à la désinstallation sauf --drop-tables)
  --help                cette aide

Journal : ${LOG_FILE}    Sauvegardes : ${BACKUP_ROOT}
Dépôt   : ${REPO_URL}
EOF
}

parse_args() {
    local arg
    for arg in "$@"; do
        case "$arg" in
            --full) MODE="full" ;;
            --update) MODE="update" ;;
            --uninstall) MODE="uninstall" ;;
            --rollback) MODE="rollback" ;;
            --rollback=*) MODE="rollback"; ROLLBACK_ID="${arg#*=}" ;;
            --path=*) FS_PATH="${arg#*=}"; FS_PATH="${FS_PATH%/}" ;;
            --version=*) WANTED_VERSION="${arg#*=}"; WANTED_VERSION="${WANTED_VERSION#v}" ;;
            --source=*) SOURCE="${arg#*=}" ;;
            --refresh-zip=*) REFRESH_ZIP="${arg#*=}" ;;
            --no-db-backup) NO_DB_BACKUP=1 ;;
            --drop-tables) DROP_TABLES=1 ;;
            --remove-files) REMOVE_FILES=1 ;;
            --force) FORCE=1 ;;
            --auto-update=on|--auto-update=off) AUTO_UPDATE="${arg#*=}" ;;
            --no-auto-rollback) AUTO_ROLLBACK=0 ;;
            --dry-run) DRY_RUN=1 ;;
            --yes|-y) ASSUME_YES=1 ;;
            --help|-h) usage; exit 0 ;;
            *) printf '%s\n' "Option inconnue : ${arg}" >&2; usage >&2; exit 2 ;;
        esac
    done
    if [ -n "$WANTED_VERSION" ] && ! printf '%s' "$WANTED_VERSION" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+$'; then
        printf '%s\n' "Version invalide : ${WANTED_VERSION} (format attendu : 1.2.3)" >&2
        exit 2
    fi
    if [ -n "$REFRESH_ZIP" ] && [ "$MODE" != "full" ]; then
        printf '%s\n' "--refresh-zip n'est utilisé qu'avec --full (Refresh s'installe par Gérer › Modules sur un FreeScout existant)." >&2
        exit 2
    fi
}

init_log() {
    if [ "$DRY_RUN" = 1 ]; then
        return 0
    fi
    if mkdir -p "$(dirname "$LOG_FILE")" 2>/dev/null && touch "$LOG_FILE" 2>/dev/null; then
        chmod 600 "$LOG_FILE" 2>/dev/null || true
        LOG_READY=1
        log "===== RefreshGlobal install.sh ${SCRIPT_VERSION} — mode ${MODE} — $(uname -a 2>/dev/null || true)"
    else
        warn "Journal ${LOG_FILE} impossible à créer : la sortie ne sera pas journalisée."
    fi
}

# ------------------------------------------------------------------------------------------------ détection -----
detect_os() {
    OS_ID="inconnu"; OS_VERSION=""
    if [ -r /etc/os-release ]; then
        # shellcheck disable=SC1091
        OS_ID="$(. /etc/os-release && printf '%s' "${ID:-inconnu}")"
        # shellcheck disable=SC1091
        OS_VERSION="$(. /etc/os-release && printf '%s' "${VERSION_ID:-}")"
    fi
    log "OS : ${OS_ID} ${OS_VERSION}"
}

detect_freescout() {
    local p
    if [ -z "$FS_PATH" ]; then
        for p in "${FREESCOUT_PATHS[@]}"; do
            if is_freescout "$p"; then
                FS_PATH="$p"
                break
            fi
        done
    fi
    if [ -z "$FS_PATH" ]; then
        die "FreeScout introuvable dans les emplacements habituels (${FREESCOUT_PATHS[*]})." \
            "Indiquer le dossier : --path=/chemin/vers/freescout (celui qui contient le fichier « artisan »)."
    fi
    if ! is_freescout "$FS_PATH"; then
        die "« ${FS_PATH} » n'est pas une installation FreeScout (fichier artisan ou config/app.php absent)." \
            "Vérifier le chemin donné à --path."
    fi
    FS_VERSION="$(sed -n "s/^[[:space:]]*'version'[[:space:]]*=>[[:space:]]*'\([^']*\)'.*/\1/p" "$FS_PATH/config/app.php" | head -n 1)"
    WEB_USER="$(stat -c '%U' "$FS_PATH/artisan" 2>/dev/null || echo www-data)"
    if [ "$WEB_USER" = "UNKNOWN" ] || [ -z "$WEB_USER" ]; then
        WEB_USER="www-data"
    fi
    if [ -f "$FS_PATH/Modules/Refresh/module.json" ]; then
        REFRESH_VERSION="$(json_version "$FS_PATH/Modules/Refresh/module.json")"
    fi
    if [ -f "$FS_PATH/Modules/${MODULE_NAME}/module.json" ]; then
        INSTALLED_VERSION="$(json_version "$FS_PATH/Modules/${MODULE_NAME}/module.json")"
    fi
    log "FreeScout ${FS_VERSION} dans ${FS_PATH}, utilisateur web ${WEB_USER}, Refresh ${REFRESH_VERSION:-absent}, RefreshGlobal ${INSTALLED_VERSION:-absent}"
}

is_freescout() {
    [ -f "$1/artisan" ] && [ -f "$1/config/app.php" ] && grep -q "freescout" "$1/composer.json" 2>/dev/null
}

check_php() {
    if ! command -v php >/dev/null 2>&1; then
        die "PHP (ligne de commande) est introuvable." \
            "Installer PHP ${PHP_MIN} ou plus récent (paquet php-cli), ou utiliser --full sur un serveur vierge."
    fi
    PHP_VERSION="$(php -r 'echo PHP_VERSION;' 2>/dev/null || true)"
    if [ -z "$PHP_VERSION" ] || ! version_ge "$PHP_VERSION" "$PHP_MIN"; then
        die "PHP ${PHP_VERSION:-?} trouvé, FreeScout demande PHP ${PHP_MIN} ou plus récent." "Mettre PHP à jour."
    fi
    local missing=() ext modules
    modules="$(php -m 2>/dev/null | tr '[:upper:]' '[:lower:]' || true)"
    for ext in "${PHP_EXTENSIONS[@]}"; do
        if ! printf '%s\n' "$modules" | grep -qx "$ext"; then
            missing+=("$ext")
        fi
    done
    if [ "${#missing[@]}" -gt 0 ]; then
        warn "Extensions PHP absentes en ligne de commande : ${missing[*]} (exigées par FreeScout, config/installer.php)."
    fi
    log "PHP ${PHP_VERSION}"
}

check_tools() {
    local t missing=()
    for t in "$@"; do
        if ! command -v "$t" >/dev/null 2>&1; then
            missing+=("$t")
        fi
    done
    if [ "${#missing[@]}" -gt 0 ]; then
        die "outils absents : ${missing[*]}" "Les installer (Debian/Ubuntu : apt install ${missing[*]})."
    fi
}

check_root() {
    if [ "$(id -u)" != "0" ] && [ "$DRY_RUN" = 0 ]; then
        die "le script doit être lancé en root." "Relancer avec sudo : curl -fsSL …/install.sh | sudo bash"
    fi
}

check_freescout_ready() {
    if [ ! -f "$FS_PATH/.env" ]; then
        die "FreeScout n'est pas encore configuré (fichier .env absent dans ${FS_PATH})." \
            "Terminer l'installation web de FreeScout (adresse /install), puis relancer ce script."
    fi
    if [ "$DRY_RUN" = 0 ] && ! artisan --version; then
        die "« php artisan » ne fonctionne pas dans ${FS_PATH}." "Vérifier les droits des fichiers et la configuration de FreeScout."
    fi
}

module_active() {
    # « Enabled » dans php artisan module:list (statut lu dans la table modules, app/Module.php:44)
    local out
    if [ "$(id -u)" = "0" ] && [ "$WEB_USER" != "root" ] && command -v runuser >/dev/null 2>&1; then
        out="$(runuser -u "$WEB_USER" -- php "$FS_PATH/artisan" module:list 2>/dev/null || true)"
    else
        out="$(php "$FS_PATH/artisan" module:list 2>/dev/null || true)"
    fi
    printf '%s\n' "$out" | grep -E "\|[[:space:]]*$1[[:space:]]*\|" | grep -q "Enabled"
}

# -------------------------------------------------------------------------------------------- sauvegarde --------
db_cli() {
    # $1 = dump | dump_table | query, la suite = arguments. Mot de passe transmis par variable d'environnement,
    # jamais en argument (ni visible dans « ps », ni écrit dans le journal).
    local action=$1; shift
    local conn host port name user pass
    conn="$(env_value DB_CONNECTION)"; host="$(env_value DB_HOST)"; port="$(env_value DB_PORT)"
    name="$(env_value DB_DATABASE)"; user="$(env_value DB_USERNAME)"; pass="$(env_value DB_PASSWORD)"
    host=${host:-127.0.0.1}
    case "${conn:-mysql}" in
        mysql)
            port=${port:-3306}
            case "$action" in
                dump) MYSQL_PWD="$pass" mysqldump --single-transaction --quick --no-tablespaces -h "$host" -P "$port" -u "$user" "$name" ;;
                dump_table) MYSQL_PWD="$pass" mysqldump --single-transaction --no-tablespaces -h "$host" -P "$port" -u "$user" "$name" "$1" ;;
                query) MYSQL_PWD="$pass" mysql -N -B -h "$host" -P "$port" -u "$user" "$name" -e "$1" ;;
                restore) MYSQL_PWD="$pass" mysql -h "$host" -P "$port" -u "$user" "$name" ;;
            esac
            ;;
        pgsql)
            port=${port:-5432}
            case "$action" in
                dump) PGPASSWORD="$pass" pg_dump -h "$host" -p "$port" -U "$user" "$name" ;;
                dump_table) PGPASSWORD="$pass" pg_dump -h "$host" -p "$port" -U "$user" -t "$1" --clean --if-exists "$name" ;;
                query) PGPASSWORD="$pass" psql -At -h "$host" -p "$port" -U "$user" "$name" -c "$1" ;;
                restore) PGPASSWORD="$pass" psql -q -h "$host" -p "$port" -U "$user" "$name" ;;
            esac
            ;;
        *) return 3 ;;
    esac
}

db_tool_available() {
    case "$(env_value DB_CONNECTION)" in
        pgsql) command -v pg_dump >/dev/null 2>&1 && command -v psql >/dev/null 2>&1 ;;
        *) command -v mysqldump >/dev/null 2>&1 && command -v mysql >/dev/null 2>&1 ;;
    esac
}

module_table_exists() {
    local prefix table
    prefix="$(env_value DB_PREFIX)"
    table="${prefix}${MODULE_TABLE}"
    case "$(env_value DB_CONNECTION)" in
        pgsql) [ "$(db_cli query "SELECT to_regclass('${table}') IS NOT NULL" 2>/dev/null || true)" = "t" ] ;;
        *) [ -n "$(db_cli query "SHOW TABLES LIKE '${table}'" 2>/dev/null || true)" ] ;;
    esac
}

make_backup() {
    local ts
    ts="$(date +%Y%m%d-%H%M%S)"
    BACKUP_DIR="${BACKUP_ROOT}/${ts}"
    if [ "$DRY_RUN" = 1 ]; then
        run mkdir -p "$BACKUP_DIR"
        run tar -czf "$BACKUP_DIR/module.tar.gz" -C "$FS_PATH/Modules" "$MODULE_NAME"
        run echo "sauvegarde de la base dans $BACKUP_DIR/database.sql.gz"
        return 0
    fi
    mkdir -p "$BACKUP_DIR"
    chmod 700 "$BACKUP_ROOT" "$BACKUP_DIR"
    {
        printf 'FS_PATH=%s\n' "$FS_PATH"
        printf 'VERSION_BEFORE=%s\n' "${INSTALLED_VERSION}"
        if [ -n "$INSTALLED_VERSION" ] && module_active "$MODULE_NAME"; then
            printf 'WAS_ACTIVE=1\n'
        else
            printf 'WAS_ACTIVE=0\n'
        fi
        printf 'DATE=%s\n' "$ts"
    } >"$BACKUP_DIR/meta.env"

    if [ -d "$FS_PATH/Modules/${MODULE_NAME}" ]; then
        run tar -czf "$BACKUP_DIR/module.tar.gz" -C "$FS_PATH/Modules" "$MODULE_NAME"
    else
        : >"$BACKUP_DIR/module-absent"
    fi

    if [ "$NO_DB_BACKUP" = 1 ]; then
        warn "Sauvegarde de la base désactivée (--no-db-backup)."
    elif ! db_tool_available; then
        die "outil de sauvegarde de la base absent (mysqldump / pg_dump)." \
            "L'installer (apt install mariadb-client ou postgresql-client), ou relancer avec --no-db-backup (déconseillé)."
    else
        log "\$ dump de la base (mot de passe non journalisé) -> ${BACKUP_DIR}/database.sql.gz"
        if ! db_cli dump 2>>"$LOG_FILE" | gzip >"$BACKUP_DIR/database.sql.gz"; then
            die "la sauvegarde de la base a échoué." "Vérifier les accès à la base (voir le journal), ou relancer avec --no-db-backup."
        fi
        if module_table_exists; then
            db_cli dump_table "$(env_value DB_PREFIX)${MODULE_TABLE}" >"$BACKUP_DIR/module-table.sql" 2>>"$LOG_FILE"
        else
            : >"$BACKUP_DIR/module-table-absent"
        fi
    fi
    chmod -R go-rwx "$BACKUP_DIR"
    ln -sfn "$BACKUP_DIR" "${BACKUP_ROOT}/latest"
}

# ----------------------------------------------------------------------------------------- téléchargement -------
fetch() {
    # fetch URL FICHIER
    if command -v curl >/dev/null 2>&1; then
        curl -fsSL --retry 2 -o "$2" "$1"
    else
        wget -q -O "$2" "$1"
    fi
}

get_module() {
    TMP_DIR="$(mktemp -d)"
    local zip="$TMP_DIR/module.zip" base url
    if [ -n "$SOURCE" ]; then
        if [ -d "$SOURCE" ]; then
            [ -f "$SOURCE/module.json" ] || die "le dossier ${SOURCE} ne contient pas de module.json."
            mkdir -p "$TMP_DIR/x" && cp -R "$SOURCE" "$TMP_DIR/x/${MODULE_NAME}"
        elif [ -f "$SOURCE" ]; then
            cp "$SOURCE" "$zip"
        else
            die "source introuvable : ${SOURCE}"
        fi
    else
        if [ -n "$WANTED_VERSION" ]; then
            base="${REPO_URL}/releases/download/v${WANTED_VERSION}"
        else
            base="${REPO_URL}/releases/latest/download"
        fi
        url="${base}/RefreshGlobal.zip"
        if [ "$DRY_RUN" = 1 ]; then
            run fetch "$url" "$zip"
            TARGET_VERSION="${WANTED_VERSION:-dernière}"
            return 0
        fi
        log "Téléchargement ${url}"
        if ! fetch "$url" "$zip" 2>/dev/null; then
            # aucune release publiée : version actuelle de la branche main (sauf si une version précise est demandée)
            [ -z "$WANTED_VERSION" ] || die "téléchargement impossible : ${url}" \
                "Vérifier la version demandée et l'accès à Internet, ou utiliser --source=/chemin/RefreshGlobal.zip."
            warn "Aucune release publiée : installation de la version actuelle de la branche main (${BRANCH_ZIP_URL}), sans fichier d'empreinte SHA-256."
            fetch "$BRANCH_ZIP_URL" "$zip" || die "téléchargement impossible : ${url} ni ${BRANCH_ZIP_URL}" \
                "Vérifier l'accès à Internet, ou utiliser --source=/chemin/RefreshGlobal.zip."
            log "SHA-256 de l'archive téléchargée : $(sha256sum "$zip" | awk '{print $1}')"
        elif fetch "${base}/SHA256SUMS" "$TMP_DIR/SHA256SUMS" 2>/dev/null; then
            local expected actual
            expected="$(grep 'RefreshGlobal.zip' "$TMP_DIR/SHA256SUMS" 2>/dev/null | awk '{print $1}' | head -n 1 || true)"
            actual="$(sha256sum "$zip" | awk '{print $1}')"
            if [ -n "$expected" ] && [ "$expected" != "$actual" ]; then
                die "l'archive téléchargée ne correspond pas à son empreinte SHA-256." "Réessayer plus tard ; ne pas installer cette archive."
            fi
            log "Empreinte SHA-256 vérifiée"
        else
            warn "Fichier SHA256SUMS absent de la publication : empreinte non vérifiée."
        fi
    fi
    if [ -f "$zip" ]; then
        mkdir -p "$TMP_DIR/x"
        unzip -q "$zip" -d "$TMP_DIR/x" || die "archive ZIP illisible."
    fi
    local manifest
    manifest="$(find "$TMP_DIR/x" -maxdepth 3 -name module.json -path "*${MODULE_NAME}/module.json" 2>/dev/null | head -n 1 || true)"
    [ -n "$manifest" ] || die "l'archive ne contient pas le dossier ${MODULE_NAME}/module.json."
    grep -q "\"alias\"[[:space:]]*:[[:space:]]*\"${MODULE_ALIAS}\"" "$manifest" || die "le module.json trouvé n'est pas celui de RefreshGlobal."
    MODULE_SRC="$(dirname "$manifest")"
    TARGET_VERSION="$(json_version "$manifest")"
    [ -n "$TARGET_VERSION" ] || die "version introuvable dans module.json."
}

# ------------------------------------------------------------------------------------- installation ------------
copy_module() {
    local dest="$FS_PATH/Modules/${MODULE_NAME}"
    if [ "$DRY_RUN" = 1 ]; then
        run rm -rf "$dest"
        run cp -R "<archive>/${MODULE_NAME}" "$dest"
        run chown -R "${WEB_USER}:" "$dest"
        return 0
    fi
    rm -rf "${dest}.rg-new"
    cp -R "$MODULE_SRC" "${dest}.rg-new"
    rm -rf "$dest"
    mv "${dest}.rg-new" "$dest"
    # mêmes droits que le script officiel (tools/install.sh:120-121)
    find "$dest" -type d -exec chmod 775 {} +
    find "$dest" -type f -exec chmod 664 {} +
    chown -R "${WEB_USER}:$(id -gn "$WEB_USER" 2>/dev/null || echo "$WEB_USER")" "$dest"
}

activate_module() {
    # Comme Gérer › Modules › Activer (ModulesController.php:252-257) :
    artisan cache:clear
    artisan module:enable "$MODULE_NAME"
    artisan freescout:module-install "$MODULE_ALIAS"
    if [ "$DRY_RUN" = 0 ] && [ ! -e "$FS_PATH/public/modules/${MODULE_ALIAS}" ]; then
        die "le lien public/modules/${MODULE_ALIAS} n'a pas été créé." "Vérifier les droits du dossier ${FS_PATH}/public/modules."
    fi
}

run_check() {
    CHECK_CODE=0
    if [ "$DRY_RUN" = 1 ]; then
        artisan_show refreshglobal:check
        CHECK_STATE="non vérifiée (simulation)"
        return 0
    fi
    say ""
    artisan_show refreshglobal:check || CHECK_CODE=$?
    case "$CHECK_CODE" in
        0) CHECK_STATE="OK" ;;
        1) CHECK_STATE="DÉGRADÉ (la page fonctionne avec le style FreeScout standard)" ;;
        2) CHECK_STATE="BLOQUANT (la liste n'est pas affichée)" ;;
        *) CHECK_STATE="inconnu (code ${CHECK_CODE})" ;;
    esac
}

summary() {
    local url
    url="$(env_value APP_URL)"
    url="${url%/}"
    say ""
    say "${C_BOLD}Résumé${C_RESET}"
    say "  Version installée   : RefreshGlobal ${TARGET_VERSION:-$INSTALLED_VERSION}"
    say "  FreeScout           : ${FS_VERSION} (${FS_PATH})"
    say "  Refresh             : ${REFRESH_VERSION:-absent → style FreeScout standard}"
    say "  Page                : ${url:-<adresse de FreeScout>}/refresh-global/tickets"
    say "  Diagnostic (admin)  : ${url:-<adresse de FreeScout>}/refresh-global/diagnostic"
    say "  Compatibilité       : ${CHECK_STATE:-non vérifiée}"
    if [ -n "$BACKUP_DIR" ]; then
        say "  Sauvegarde          : ${BACKUP_DIR}"
        say "  Retour arrière      : sudo bash install.sh --rollback --path=${FS_PATH}"
    fi
    say "  Journal             : ${LOG_FILE}"
}

# ----------------------------------------------------------------------------------------------- mode complet ---
install_base_tools() {
    # Serveur vierge : outils nécessaires au script lui-même (le script officiel installe PHP, nginx et MySQL).
    local t missing=()
    for t in curl unzip tar gzip; do
        command -v "$t" >/dev/null 2>&1 || missing+=("$t")
    done
    if [ "${#missing[@]}" -eq 0 ]; then
        return 0
    fi
    command -v apt-get >/dev/null 2>&1 || die "outils absents : ${missing[*]}" "Les installer, puis relancer le script."
    run apt-get update -q
    DEBIAN_FRONTEND=noninteractive run apt-get install -y -q "${missing[@]}"
}

install_freescout_official() {
    step "Installation de FreeScout (script officiel)"
    if [ -z "$FS_PATH" ]; then
        FS_PATH="/var/www/html"
    fi
    if is_freescout "$FS_PATH"; then
        step_ok "déjà installé dans ${FS_PATH}"
        return 0
    fi
    case "$OS_ID" in
        ubuntu|debian) ;;
        *) die "le script officiel de FreeScout est prévu pour Ubuntu / Debian (système détecté : ${OS_ID})." \
               "Installer FreeScout à la main (wiki Installation-Guide), puis lancer ce script sans --full." ;;
    esac
    if [ "$DRY_RUN" = 1 ]; then
        printf '\n'
        run fetch "$FREESCOUT_INSTALLER_URL" "/tmp/freescout-install.sh"
        run bash /tmp/freescout-install.sh
        return 0
    fi
    if ! { true </dev/tty; } 2>/dev/null; then
        die "le script officiel de FreeScout pose des questions (domaine, dossier, HTTPS) : un terminal est nécessaire." \
            "Lancer la commande depuis un terminal SSH interactif."
    fi
    TMP_DIR="${TMP_DIR:-$(mktemp -d)}"
    fetch "$FREESCOUT_INSTALLER_URL" "$TMP_DIR/freescout-install.sh" || die "téléchargement impossible : ${FREESCOUT_INSTALLER_URL}"
    printf '\n'
    info "Le script officiel de FreeScout va poser ses questions. Choisir le dossier : ${FS_PATH}"
    info "Sur un serveur neuf, ce dossier ne contient que la page par défaut de nginx : répondre Y à « All files … will be removed »."
    info "Sa sortie n'est pas copiée dans le journal (elle affiche le mot de passe de la base)."
    log "Lancement du script officiel ${FREESCOUT_INSTALLER_URL} (sortie non journalisée)"
    local rc=0
    bash "$TMP_DIR/freescout-install.sh" </dev/tty || rc=$?
    log "Script officiel terminé, code ${rc}"
    is_freescout "$FS_PATH" || die "FreeScout n'a pas été installé dans ${FS_PATH} (code ${rc})." \
        "Relancer en répondant Y à la question « All files … will be removed » (serveur neuf), ou avec --path=<dossier choisi> si un autre dossier a été choisi."
    printf '%s' "${C_BOLD}[${STEP}/${TOTAL}]${C_RESET} Installation de FreeScout... "
    step_ok
    wait_web_setup
}

wait_web_setup() {
    while [ ! -f "$FS_PATH/.env" ]; do
        if [ "$ASSUME_YES" = 1 ] || ! { true </dev/tty; } 2>/dev/null; then
            say ""
            info "Terminer l'installation dans le navigateur (adresse /install de FreeScout), puis relancer :"
            say "    sudo bash install.sh --path=${FS_PATH}"
            exit 0
        fi
        printf '%s' "${C_BOLD}?${C_RESET} Ouvrir l'adresse /install de FreeScout dans le navigateur, terminer l'installation, puis appuyer sur Entrée " >/dev/tty
        read -r _ </dev/tty || true
    done
}

install_refresh_zip() {
    step "Installation de Refresh depuis ${REFRESH_ZIP}"
    if [ -z "$REFRESH_ZIP" ]; then
        step_warn "ignoré (pas de --refresh-zip : RefreshGlobal utilisera le style FreeScout standard)"
        return 0
    fi
    [ -f "$REFRESH_ZIP" ] || die "archive Refresh introuvable : ${REFRESH_ZIP}"
    if [ "$DRY_RUN" = 1 ]; then
        printf '\n'
        run unzip -q "$REFRESH_ZIP" -d "<temp>"
        run cp -R "<temp>/Refresh" "$FS_PATH/Modules/Refresh"
        artisan module:enable Refresh
        artisan freescout:module-install refresh
        return 0
    fi
    local dir manifest
    dir="$(mktemp -d)"
    unzip -q "$REFRESH_ZIP" -d "$dir" || die "archive Refresh illisible."
    manifest="$(find "$dir" -maxdepth 3 -name module.json -exec grep -l '"alias"[[:space:]]*:[[:space:]]*"refresh"' {} + 2>/dev/null | head -n 1 || true)"
    [ -n "$manifest" ] || die "l'archive ne contient pas le module Refresh (module.json avec l'alias « refresh »)."
    if [ -d "$FS_PATH/Modules/Refresh" ]; then
        step_ok "déjà présent (non modifié)"
        rm -rf "$dir"
    else
        # le dossier doit s'appeler Refresh (README de Refresh, « Installation »)
        cp -R "$(dirname "$manifest")" "$FS_PATH/Modules/Refresh"
        rm -rf "$dir"
        chown -R "${WEB_USER}:" "$FS_PATH/Modules/Refresh"
    fi
    artisan cache:clear
    artisan module:enable Refresh
    artisan freescout:module-install refresh
    REFRESH_VERSION="$(json_version "$FS_PATH/Modules/Refresh/module.json")"
    step_ok "Refresh ${REFRESH_VERSION}"
}

# --------------------------------------------------------------------------------------------------- modes ------
mode_add() {
    step "Contrôles préalables"
    check_root
    detect_os
    if [ "$MODE" = "full" ]; then
        TOTAL=10
        install_base_tools
        printf '%s\n' "OK"
        install_freescout_official
        check_php
        detect_freescout
        check_freescout_ready
        install_refresh_zip
        step "Contrôles de l'installation FreeScout"
    else
        check_tools tar gzip unzip find
        command -v curl >/dev/null 2>&1 || command -v wget >/dev/null 2>&1 || die "ni curl ni wget n'est installé." "apt install curl"
        check_php
        detect_freescout
        check_freescout_ready
    fi
    if [ "$MODE" = "update" ] && [ -z "$INSTALLED_VERSION" ]; then
        die "RefreshGlobal n'est pas installé dans ${FS_PATH} : rien à mettre à jour." "Lancer le script sans --update pour l'installer."
    fi
    if [ -z "$REFRESH_VERSION" ]; then
        step_warn "OK — FreeScout ${FS_VERSION}, Refresh absent : la page utilisera le style FreeScout standard"
    else
        step_ok "OK — FreeScout ${FS_VERSION}, Refresh ${REFRESH_VERSION}, utilisateur web ${WEB_USER}"
    fi

    step "Téléchargement de RefreshGlobal ${WANTED_VERSION:-(dernière version)}"
    get_module
    step_ok "version ${TARGET_VERSION}"

    if [ "$FORCE" = 0 ] && [ -n "$INSTALLED_VERSION" ] && [ "$INSTALLED_VERSION" = "$TARGET_VERSION" ] && [ "$DRY_RUN" = 0 ] && module_active "$MODULE_NAME"; then
        step "Sauvegarde"; step_ok "inutile : la version ${TARGET_VERSION} est déjà installée et active"
        step "Copie dans Modules/${MODULE_NAME}"; step_ok "rien à faire"
        step "Activation du module"; step_ok "déjà actif"
    else
        if [ "$MODE" != "update" ] && [ -n "$INSTALLED_VERSION" ] && [ "$INSTALLED_VERSION" != "$TARGET_VERSION" ]; then
            info "RefreshGlobal ${INSTALLED_VERSION} est installé : passage à ${TARGET_VERSION} (vues enregistrées conservées)."
        fi
        if [ -n "$INSTALLED_VERSION" ] && [ "$TARGET_VERSION" != "$INSTALLED_VERSION" ] && ! version_ge "$TARGET_VERSION" "$INSTALLED_VERSION"; then
            ask "La version ${TARGET_VERSION} est plus ancienne que la version installée (${INSTALLED_VERSION}). Continuer ?" n \
                || die "installation annulée."
        fi
        step "Sauvegarde (base de données et module)"
        make_backup
        step_ok "${BACKUP_DIR}"

        step "Copie dans Modules/${MODULE_NAME}"
        copy_module
        step_ok

        step "Activation du module (migrations, lien public, caches)"
        activate_module
        MODULE_CHANGED=1
        step_ok
    fi
    apply_auto_update

    step "Vérification de compatibilité (php artisan refreshglobal:check)"
    run_check
    run_selftest
    printf '%s' "${C_BOLD}[${STEP}/${TOTAL}]${C_RESET} Vérification de compatibilité... "
    case "$CHECK_CODE" in
        0) step_ok "OK" ;;
        1) step_warn "$CHECK_STATE" ;;
        *) step_warn "$CHECK_STATE" ;;
    esac

    step "Résumé"
    step_ok
    summary
    if [ "$CHECK_CODE" = 2 ]; then
        if [ "$MODULE_CHANGED" = 1 ] && [ -n "$INSTALLED_VERSION" ] && [ -n "$BACKUP_DIR" ] && [ "$AUTO_ROLLBACK" = 1 ] && [ "$DRY_RUN" = 0 ]; then
            auto_rollback
        fi
        fail_msg "Le module est installé mais l'état est BLOQUANT : lire le rapport ci-dessus ; retour arrière possible avec --rollback."
        exit 4
    fi
}

# La nouvelle version ne fonctionne pas : retour automatique à la version sauvegardée juste avant.
auto_rollback() {
    say ""
    warn "La nouvelle version (${TARGET_VERSION}) ne fonctionne pas : retour automatique à la version ${INSTALLED_VERSION}."
    ROLLBACK_ID="${BACKUP_DIR##*/}"
    STEP=0
    mode_rollback
    fail_msg "Mise à jour annulée : RefreshGlobal ${INSTALLED_VERSION} a été restauré. Détails : ${LOG_FILE}"
    exit 4
}

# --auto-update=on|off : réglage enregistré par le module (php artisan refreshglobal:update --enable / --disable).
apply_auto_update() {
    [ -n "$AUTO_UPDATE" ] || return 0
    if [ "$AUTO_UPDATE" = on ]; then
        artisan refreshglobal:update --enable
        info "Mise à jour automatique quotidienne activée (retour arrière automatique si la nouvelle version ne fonctionne pas)."
    else
        artisan refreshglobal:update --disable
        info "Mise à jour automatique désactivée."
    fi
}

# Page rendue pour un administrateur (commande présente à partir de RefreshGlobal 1.1.0).
run_selftest() {
    if [ "$DRY_RUN" = 1 ] || [ "$CHECK_CODE" = 2 ] || [ ! -f "$FS_PATH/Modules/${MODULE_NAME}/Console/SelfTestCommand.php" ]; then
        return 0
    fi
    if ! artisan refreshglobal:selftest; then
        CHECK_CODE=2
        CHECK_STATE="BLOQUANT (la page ne s'affiche pas : php artisan refreshglobal:selftest)"
    fi
}

mode_uninstall() {
    TOTAL=5
    step "Contrôles préalables"
    check_root
    detect_os
    check_tools tar gzip
    check_php
    detect_freescout
    check_freescout_ready
    [ -d "$FS_PATH/Modules/${MODULE_NAME}" ] || die "RefreshGlobal n'est pas installé dans ${FS_PATH}."
    step_ok

    step "Sauvegarde (base de données et module)"
    make_backup
    step_ok "${BACKUP_DIR}"

    step "Désactivation du module"
    artisan module:disable "$MODULE_NAME"
    artisan freescout:clear-cache
    step_ok

    step "Suppression des tables du module (${MODULE_TABLE}, refreshglobal_mail_deletions)"
    local drop=0
    if [ "$DROP_TABLES" = 1 ]; then
        drop=1
    elif [ "$ASSUME_YES" = 0 ] && ask "Supprimer les tables du module (vues enregistrées des utilisateurs, e-mails en attente pour la corbeille du serveur) ?" n; then
        drop=1
    fi
    if [ "$drop" = 1 ]; then
        artisan module:migrate-rollback "$MODULE_NAME" --force
        step_ok "supprimée"
    else
        step_ok "conservée"
    fi

    step "Fichiers du module"
    local remove=0
    if [ "$REMOVE_FILES" = 1 ]; then
        remove=1
    elif [ "$ASSUME_YES" = 0 ] && ask "Supprimer aussi les fichiers du module (Modules/${MODULE_NAME}) ?" n; then
        remove=1
    fi
    if [ "$remove" = 1 ]; then
        run rm -rf "$FS_PATH/Modules/${MODULE_NAME}"
        if [ -L "$FS_PATH/public/modules/${MODULE_ALIAS}" ]; then
            run rm -f "$FS_PATH/public/modules/${MODULE_ALIAS}"
        fi
        artisan freescout:clear-cache
        step_ok "supprimés"
    else
        step_ok "conservés (module simplement désactivé)"
    fi
    say ""
    say "RefreshGlobal est désactivé. Rien d'autre n'a été modifié (FreeScout, Refresh et les tickets sont intacts)."
    say "Sauvegarde : ${BACKUP_DIR} — retour arrière : sudo bash install.sh --rollback --path=${FS_PATH}"
}

mode_rollback() {
    TOTAL=5
    step "Contrôles préalables"
    check_root
    check_tools tar gzip
    check_php
    detect_freescout
    check_freescout_ready
    local src
    if [ -n "$ROLLBACK_ID" ]; then
        src="${BACKUP_ROOT}/${ROLLBACK_ID}"
    else
        src="$(readlink -f "${BACKUP_ROOT}/latest" 2>/dev/null || true)"
    fi
    [ -n "$src" ] && [ -f "$src/meta.env" ] || die "aucune sauvegarde trouvée (${src:-${BACKUP_ROOT}/latest})." \
        "Les sauvegardes sont dans ${BACKUP_ROOT} ; choisir avec --rollback=AAAAMMJJ-HHMMSS."
    local was_active version_before
    was_active="$(sed -n 's/^WAS_ACTIVE=//p' "$src/meta.env")"
    version_before="$(sed -n 's/^VERSION_BEFORE=//p' "$src/meta.env")"
    step_ok "sauvegarde ${src##*/} (version précédente : ${version_before:-aucune})"

    step "Restauration des fichiers du module"
    if [ -f "$src/module.tar.gz" ]; then
        run rm -rf "$FS_PATH/Modules/${MODULE_NAME}"
        run tar -xzf "$src/module.tar.gz" -C "$FS_PATH/Modules"
        run chown -R "${WEB_USER}:" "$FS_PATH/Modules/${MODULE_NAME}"
        step_ok "version ${version_before}"
    else
        if [ -d "$FS_PATH/Modules/${MODULE_NAME}" ]; then
            artisan module:disable "$MODULE_NAME" || true
        fi
        step_ok "le module n'existait pas avant : il sera retiré"
    fi

    step "Restauration de la table du module"
    if [ -f "$src/module-table.sql" ]; then
        if [ "$DRY_RUN" = 1 ]; then
            run echo "import de $src/module-table.sql"
        else
            log "\$ import de ${src}/module-table.sql (mot de passe non journalisé)"
            db_cli restore <"$src/module-table.sql" 2>>"$LOG_FILE" || die "restauration de la table impossible (voir le journal)."
        fi
        step_ok
    elif [ -f "$src/module-table-absent" ]; then
        if [ -d "$FS_PATH/Modules/${MODULE_NAME}" ] && [ ! -f "$src/module.tar.gz" ]; then
            artisan module:migrate-rollback "$MODULE_NAME" --force || true
        fi
        step_ok "la table n'existait pas avant"
    else
        step_warn "pas de sauvegarde de table (installation faite avec --no-db-backup) : table laissée telle quelle"
    fi

    step "Réactivation et caches"
    if [ ! -f "$src/module.tar.gz" ]; then
        run rm -rf "$FS_PATH/Modules/${MODULE_NAME}"
        if [ -L "$FS_PATH/public/modules/${MODULE_ALIAS}" ]; then
            run rm -f "$FS_PATH/public/modules/${MODULE_ALIAS}"
        fi
        artisan freescout:clear-cache
        step_ok "module retiré"
    elif [ "$was_active" = "1" ]; then
        artisan cache:clear
        artisan module:enable "$MODULE_NAME"
        artisan freescout:module-install "$MODULE_ALIAS"
        step_ok "module réactivé"
        INSTALLED_VERSION="$version_before"
        run_check
    else
        artisan module:disable "$MODULE_NAME"
        artisan freescout:clear-cache
        step_ok "module laissé désactivé (comme avant)"
    fi
    say ""
    say "Retour arrière terminé (sauvegarde ${src##*/}). La sauvegarde complète de la base reste disponible :"
    say "  ${src}/database.sql.gz (à restaurer à la main seulement si nécessaire)."
}

main() {
    parse_args "$@"
    init_log
    trap 'on_error $? $LINENO' ERR
    trap cleanup EXIT
    say "${C_BOLD}RefreshGlobal — installeur ${SCRIPT_VERSION}${C_RESET} (mode : ${MODE}$([ "$DRY_RUN" = 1 ] && printf ' — simulation, rien ne sera modifié'))"
    case "$MODE" in
        add|full|update) mode_add ;;
        uninstall) mode_uninstall ;;
        rollback) mode_rollback ;;
    esac
}

main "$@"
