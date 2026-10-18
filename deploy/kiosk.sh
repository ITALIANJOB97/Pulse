#!/usr/bin/env bash
set -eu
mkdir -p "$HOME/.local/state/pulse" "$HOME/.config/pulse-kiosk"
exec 9>"$HOME/.local/state/pulse/kiosk.lock"
flock -n 9 || exit 0
while true; do
    until [ "$(curl --silent --max-time 5 --output /dev/null --write-out '%{http_code}' http://127.0.0.1:8080/api/screen || true)" = "200" ]; do
        sleep 5
    done
    chromium --kiosk --no-first-run --noerrdialogs \
        --disable-session-crashed-bubble --password-store=basic \
        --user-data-dir="$HOME/.config/pulse-kiosk" \
        http://127.0.0.1:8080/display || true
    sleep 5
done

