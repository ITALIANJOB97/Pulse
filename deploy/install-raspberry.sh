#!/usr/bin/env bash
# Run once on Raspberry Pi OS 64-bit with the packages from the installation guide.
set -euo pipefail
[ "$(id -u)" = "0" ] || { echo "Run with sudo."; exit 1; }
SOURCE="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="$(tr -d '\r\n' < "$SOURCE/VERSION")"
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "Invalid VERSION"; exit 1; }
php -r 'exit(version_compare(PHP_VERSION,"8.4",">=")?0:1);' || { echo "PHP >= 8.4 required."; exit 1; }
PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
[ -d "/etc/php/$PHP_VERSION/fpm/pool.d" ] || { echo "Install matching PHP-FPM first."; exit 1; }
[ ! -e /opt/pulse/current ] || { echo "PULSE is already installed. Follow the update instructions."; exit 1; }
TARGET="/opt/pulse/releases/$VERSION"
[ ! -e "$TARGET" ] || { echo "Release directory already exists; inspect it before continuing."; exit 1; }
install -d -o root -g www-data -m 0755 /opt/pulse /opt/pulse/releases
install -d -o root -g www-data -m 0750 "$TARGET"
rsync -a --exclude=github-token --exclude=github-token.* --exclude=.release-private --exclude=__pycache__ --exclude=storage --exclude=.git --exclude=dist --exclude=config/local.php "$SOURCE/" "$TARGET/"
chown -R root:www-data "$TARGET"
find "$TARGET" -type d -exec chmod 0750 {} +
find "$TARGET" -type f -exec chmod 0640 {} +
install -m 0755 "$TARGET/deploy/kiosk.sh" /usr/local/bin/pulse-kiosk
install -d -o www-data -g www-data -m 0770 /var/lib/pulse
for dir in media backups logs sessions; do
    install -d -o www-data -g www-data -m 0770 "/var/lib/pulse/$dir"
done
ln -s "$TARGET" /opt/pulse/current
install -m 0644 "$TARGET/deploy/php-fpm.conf" "/etc/php/$PHP_VERSION/fpm/pool.d/pulse.conf"
install -m 0644 "$TARGET/deploy/nginx.conf" /etc/nginx/sites-available/pulse
ln -sfn /etc/nginx/sites-available/pulse /etc/nginx/sites-enabled/pulse
for unit in pulse-backup@.service pulse-backup-daily.timer pulse-backup-weekly.timer pulse-updates.service pulse-updates.timer; do
    install -m 0644 "$TARGET/deploy/$unit" "/etc/systemd/system/$unit"
done
install -m 0644 "$TARGET/deploy/pulse-notify.service" /etc/systemd/system/pulse-updates.service
sudo -u www-data env PULSE_STORAGE=/var/lib/pulse php "$TARGET/bin/console.php" migrate
nginx -t
"/usr/sbin/php-fpm$PHP_VERSION" -t
systemctl daemon-reload
systemctl enable --now "php$PHP_VERSION-fpm" nginx
systemctl restart "php$PHP_VERSION-fpm"
systemctl reload nginx
systemctl enable --now pulse-backup-daily.timer pulse-backup-weekly.timer pulse-updates.timer
echo "Open http://RASPBERRY_IP:8080/setup"
echo "Installation code:"
sudo -u www-data env PULSE_STORAGE=/var/lib/pulse php "$TARGET/bin/console.php" setup-token

