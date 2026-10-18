#!/usr/bin/env bash
# Runs in the desktop session as its normal user. Never disable Chromium's sandbox.
set -euo pipefail
[[ "$(id -u)" != 0 ]] || { echo "Avviare PULSE TV dal desktop come utente normale, senza sudo." >&2; exit 1; }
STATE="${XDG_STATE_HOME:-$HOME/.local/state}/pulse"
PROFILE="${XDG_CONFIG_HOME:-$HOME/.config}/pulse-kiosk"
umask 0077
mkdir -p "$STATE"
case "${1:-}" in
    --stop) touch "$STATE/kiosk.stop"; echo "Arresto di PULSE TV richiesto."; exit 0 ;;
    "") [[ $# == 0 ]] || exit 1 ;;
    *) echo "Uso: pulse-kiosk [--stop]" >&2; exit 1 ;;
esac
[[ -n "${DISPLAY:-}${WAYLAND_DISPLAY:-}" ]] || { echo "Serve una sessione desktop aperta." >&2; exit 1; }
for dependency in flock curl; do
    command -v "$dependency" >/dev/null || { echo "Componente mancante: $dependency" >&2; exit 1; }
done
BROWSER=""
for candidate in chromium chromium-browser google-chrome-stable google-chrome; do
    if command -v "$candidate" >/dev/null; then BROWSER="$(command -v "$candidate")"; break; fi
done
[[ -n "$BROWSER" ]] || { echo "Installare Chromium: sudo apt install chromium" >&2; exit 1; }
exec 9>"$STATE/kiosk.lock"
flock -n 9 || exit 0
rm -f -- "$STATE/kiosk.stop"
mkdir -p "$PROFILE"
# Bound the browser log; one previous file is retained.
if [[ -f "$STATE/kiosk.log" && "$(stat -c %s "$STATE/kiosk.log")" -gt 5242880 ]]; then
    mv -f -- "$STATE/kiosk.log" "$STATE/kiosk.previous.log"
fi
exec >>"$STATE/kiosk.log" 2>&1
printf '\nPULSE TV avviata: %s\n' "$(date -Is)"
BROWSER_PID=""
cleanup() {
    if [[ -n "$BROWSER_PID" ]] && kill -0 "$BROWSER_PID" 2>/dev/null; then
        kill "$BROWSER_PID" 2>/dev/null || true
        for ((attempt=0; attempt<10; attempt++)); do
            kill -0 "$BROWSER_PID" 2>/dev/null || break
            sleep 0.2
        done
        if kill -0 "$BROWSER_PID" 2>/dev/null; then kill -KILL "$BROWSER_PID" 2>/dev/null || true; fi
        wait "$BROWSER_PID" 2>/dev/null || true
    fi
}
trap cleanup EXIT
trap 'exit 0' INT TERM
while [[ ! -e "$STATE/kiosk.stop" ]]; do
    if [[ "$(curl --silent --max-time 5 --output /dev/null --write-out '%{http_code}' http://127.0.0.1:8080/api/screen || true)" != 200 ]]; then
        sleep 3
        continue
    fi
    "$BROWSER" --kiosk --no-first-run --noerrdialogs --start-fullscreen \
        --disable-session-crashed-bubble --password-store=basic \
        --window-position=0,0 --window-size=1920,1080 \
        --user-data-dir="$PROFILE" http://127.0.0.1:8080/display 9>&- &
    BROWSER_PID=$!
    while kill -0 "$BROWSER_PID" 2>/dev/null && [[ ! -e "$STATE/kiosk.stop" ]]; do sleep 1; done
    [[ ! -e "$STATE/kiosk.stop" ]] || break
    wait "$BROWSER_PID" 2>/dev/null || true
    BROWSER_PID=""
    sleep 3
done
printf 'PULSE TV arrestata: %s\n' "$(date -Is)"
