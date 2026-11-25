#!/usr/bin/env bash
# Internal worker. Start deploy/install-raspberry.sh to capture the support report.
set -Eeuo pipefail
umask 0027
fail() { printf 'PULSE: %s\n' "$*" >&2; exit 1; }
trap 'printf "Installazione interrotta alla riga %s. Controllare il messaggio e la guida prima di riprovare.\n" "$LINENO" >&2' ERR
[[ "$(id -u)" == 0 ]] || fail "Eseguire: sudo bash deploy/install-raspberry.sh"
BRANCH_SETUP=0
case "${1:-}" in
    --branch-setup) [[ $# == 1 ]] || fail "Uso: install-raspberry.sh [--branch-setup]"; BRANCH_SETUP=1 ;;
    "") [[ $# == 0 ]] || fail "Parametro non valido." ;;
    *) fail "Uso: install-raspberry.sh [--branch-setup]" ;;
esac
[[ -r /etc/os-release ]] || fail "Sistema operativo non riconosciuto."
# shellcheck disable=SC1091
. /etc/os-release
[[ "${ID:-}" == debian || "${ID:-}" == raspbian ]] || fail "Serve Raspberry Pi OS Trixie."
[[ "${VERSION_ID:-}" == 13 && "${VERSION_CODENAME:-}" == trixie ]] || fail "Serve Raspberry Pi OS Trixie (Debian 13), Desktop, a 64 bit."
[[ "$(dpkg --print-architecture)" == arm64 ]] || fail "Serve Raspberry Pi OS a 64 bit (arm64), non 32 bit."
[[ -r /proc/device-tree/model ]] && grep -aq 'Raspberry Pi' /proc/device-tree/model || fail "Installer riservato a Raspberry Pi."
[[ -d /usr/share/wayland-sessions || -d /usr/share/xsessions ]] || fail "Installare Raspberry Pi OS con Desktop, non Lite."
[[ -d /run/systemd/system ]] || fail "Avviare Raspberry Pi OS normalmente, con systemd."
SOURCE="$(cd "$(dirname "$0")/.." && pwd)"
RELEASE_VERSION="$(tr -d '\r\n' < "$SOURCE/VERSION")"
[[ "$RELEASE_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || fail "VERSION non valido."
TARGET="/opt/pulse/releases/$RELEASE_VERSION"
for existing in /etc/pulse/updater.json /var/lib/pulse-updater /usr/local/lib/pulse /opt/pulse/current "$TARGET" /var/lib/pulse /usr/local/bin/pulse-kiosk \
    /usr/local/bin/pulse-enable-kiosk /etc/php/8.4/fpm/pool.d/pulse.conf \
    /etc/nginx/sites-available/pulse /etc/nginx/sites-enabled/pulse \
    /etc/systemd/system/pulse-backup@.service /etc/systemd/system/pulse-backup-daily.timer \
    /etc/systemd/system/pulse-backup-weekly.timer /etc/systemd/system/pulse-updates.service \
    /etc/systemd/system/pulse-updates.timer; do
    [[ ! -e "$existing" && ! -L "$existing" ]] || fail "Esiste gia $existing. Usare la procedura di aggiornamento/ripristino."
done
if command -v ss >/dev/null && [[ -n "$(ss -H -ltn 'sport = :8080')" ]]; then
    fail "La porta 8080 e gia occupata. Liberarla prima di installare PULSE."
fi
for required in app/bootstrap.php public/index.php bin/console.php deploy/nginx.conf deploy/php-fpm.conf \
    deploy/kiosk-mint.sh deploy/enable-kiosk-mint.sh deploy/pulse-backup@.service \
    deploy/pulse-backup-daily.timer deploy/pulse-backup-weekly.timer deploy/pulse-updates.service deploy/pulse-updates.timer; do
    [[ -f "$SOURCE/$required" ]] || fail "Pacchetto incompleto: manca $required."
done

printf '\nInstallazione componenti dai repository configurati di Raspberry Pi OS/Debian...\n'
python3 - "$SOURCE" <<'PY'
import sys
from pathlib import Path
sys.dont_write_bytecode = True
sys.path.insert(0, str(Path(sys.argv[1]) / "deploy"))
from updater import validate_tree
validate_tree(Path(sys.argv[1]))
PY
apt-get update
apt-get install -y nginx php8.4-fpm php8.4-cli php8.4-sqlite3 php8.4-gd \
    php8.4-intl php8.4-mbstring php8.4-curl php8.4-zip php8.4-xml php8.4-common \
    php8.4-opcache chromium curl rsync unzip ca-certificates util-linux
/usr/bin/php8.4 -r '
$missing=[];
foreach(["pdo_sqlite","sqlite3","gd","fileinfo","zip","curl","mbstring","intl"] as $ext)
    if(!extension_loaded($ext))$missing[]=$ext;
if(version_compare(PHP_VERSION,"8.4","<")||$missing){
    fwrite(STDERR,"Requisiti PHP mancanti: ".implode(", ",$missing).PHP_EOL);exit(1);
}'
command -v chromium >/dev/null || fail "Chromium non trovato dopo l'installazione."

install -d -o root -g www-data -m 0755 /opt/pulse /opt/pulse/releases
install -d -o root -g www-data -m 0750 "$TARGET"
rsync -a --exclude=github-token --exclude=github-token.* --exclude=.release-private --exclude=__pycache__ --exclude=storage --exclude=.git --exclude=dist --exclude=diagnostica --exclude=config/local.php \
    --exclude=.env --exclude='*.log' "$SOURCE/" "$TARGET/"
chown -R root:www-data "$TARGET"
find "$TARGET" -type d -exec chmod 0750 {} +
find "$TARGET" -type f -exec chmod 0640 {} +
install -o root -g root -m 0755 "$TARGET/deploy/kiosk-mint.sh" /usr/local/bin/pulse-kiosk
install -o root -g root -m 0755 "$TARGET/deploy/enable-kiosk-mint.sh" /usr/local/bin/pulse-enable-kiosk
install -d -o www-data -g www-data -m 0770 /var/lib/pulse
for directory in media backups logs sessions; do
    install -d -o www-data -g www-data -m 0770 "/var/lib/pulse/$directory"
done
sudo -u www-data env PULSE_STORAGE=/var/lib/pulse /usr/bin/php8.4 "$TARGET/bin/console.php" migrate
if [[ "$BRANCH_SETUP" == 1 ]]; then
    sudo -u www-data env PULSE_STORAGE=/var/lib/pulse /usr/bin/php8.4 "$TARGET/bin/console.php" prepare-branch
fi
ln -s "$TARGET" /opt/pulse/current
sed 's/pm.max_children = 2/pm.max_children = 1/' "$TARGET/deploy/php-fpm.conf" > /etc/php/8.4/fpm/pool.d/pulse.conf
chmod 0644 /etc/php/8.4/fpm/pool.d/pulse.conf
install -m 0644 "$TARGET/deploy/nginx.conf" /etc/nginx/sites-available/pulse
ln -s /etc/nginx/sites-available/pulse /etc/nginx/sites-enabled/pulse
for unit in pulse-backup@.service pulse-backup-daily.timer pulse-backup-weekly.timer pulse-updates.service pulse-updates.timer; do
    # Use the same PHP runtime as the web pool, regardless of system alternatives.
    sed 's|ExecStart=/usr/bin/php |ExecStart=/usr/bin/php8.4 |' "$TARGET/deploy/$unit" > "/etc/systemd/system/$unit"
    chmod 0644 "/etc/systemd/system/$unit"
done
nginx -t
/usr/sbin/php-fpm8.4 -t
systemctl daemon-reload
systemctl enable --now php8.4-fpm nginx
systemctl reload php8.4-fpm nginx
systemctl enable --now pulse-backup-daily.timer pulse-backup-weekly.timer
python3 "$TARGET/deploy/updater.py" bootstrap --source "$SOURCE" --channel github

printf '\nPULSE %s installato. Configurazione: http://127.0.0.1:8080/setup\n' "$RELEASE_VERSION"
if [[ "$BRANCH_SETUP" == 1 ]]; then
    printf 'Preparato per la filiale: primo accesso dalla rete interna senza codice.\n'
else
    printf 'Codice di prima installazione:\n'
    sudo -u www-data env PULSE_STORAGE=/var/lib/pulse /usr/bin/php8.4 "$TARGET/bin/console.php" setup-token
fi
printf '\nPer mostrare indirizzo IP e dashboard, dal desktop come utente normale (senza sudo):\n'
printf '  pulse-enable-kiosk\n  pulse-kiosk\n'
printf 'Leggere INSTALLAZIONE_RASPBERRY.txt per accesso automatico, schermo e manutenzione.\n'
