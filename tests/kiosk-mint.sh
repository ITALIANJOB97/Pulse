#!/usr/bin/env bash
# Linux workstation test: substitutes only browser and HTTP response, never installs services.
set -euo pipefail
SOURCE="$(cd "$(dirname "$0")/.." && pwd)"
TEST_DIR="$(mktemp -d /tmp/pulse-mint-test.XXXXXX)"
KIOSK_PID=""
cleanup() {
    if [[ -n "$KIOSK_PID" ]]; then kill "$KIOSK_PID" 2>/dev/null || true; wait "$KIOSK_PID" 2>/dev/null || true; fi
    case "$TEST_DIR" in /tmp/pulse-mint-test.*) rm -rf -- "$TEST_DIR" ;; esac
}
trap cleanup EXIT
mkdir -p "$TEST_DIR/bin" "$TEST_DIR/config" "$TEST_DIR/state"
export XDG_CONFIG_HOME="$TEST_DIR/config" XDG_STATE_HOME="$TEST_DIR/state"
export PULSE_KIOSK_TEST="$TEST_DIR"
export DISPLAY=:pulse-test
export PATH="$TEST_DIR/bin:$PATH"
cat > "$TEST_DIR/bin/curl" <<'MOCK'
#!/usr/bin/env bash
if [[ -e "$PULSE_KIOSK_TEST/ready" ]]; then printf 200; else printf 503; fi
MOCK
cat > "$TEST_DIR/bin/chromium" <<'MOCK'
#!/usr/bin/env bash
echo "$$" >> "$PULSE_KIOSK_TEST/launches"
printf '%s\n' "$@" > "$PULSE_KIOSK_TEST/arguments"
if [[ ! -f "$PULSE_KIOSK_TEST/crashed" ]]; then touch "$PULSE_KIOSK_TEST/crashed"; exit 7; fi
trap 'exit 0' TERM INT
while true; do sleep 0.1; done
MOCK
chmod +x "$TEST_DIR/bin/"*
wait_launches() {
    for ((n=0;n<150;n++)); do
        if [[ -f "$TEST_DIR/launches" && "$(wc -l < "$TEST_DIR/launches")" -ge "$1" ]]; then return; fi
        sleep 0.1
    done
    echo "Kiosk did not launch $1 times." >&2; exit 1
}
wait_stopped() {
    for ((n=0;n<60;n++)); do
        if ! kill -0 "$KIOSK_PID" 2>/dev/null; then wait "$KIOSK_PID"; KIOSK_PID=""; return; fi
        sleep 0.1
    done
    echo "Kiosk did not stop." >&2; exit 1
}
bash "$SOURCE/deploy/kiosk-mint.sh" &
KIOSK_PID=$!
sleep 1
[[ ! -f "$TEST_DIR/launches" ]] || { echo "Browser started before server readiness."; exit 1; }
touch "$TEST_DIR/ready"
wait_launches 2
bash "$SOURCE/deploy/kiosk-mint.sh"
[[ "$(wc -l < "$TEST_DIR/launches")" == 2 ]] || { echo "Duplicate browser started."; exit 1; }
grep -qx -- '--kiosk' "$TEST_DIR/arguments"
grep -qx -- "--user-data-dir=$XDG_CONFIG_HOME/pulse-kiosk" "$TEST_DIR/arguments"
if grep -qx -- '--no-sandbox' "$TEST_DIR/arguments"; then echo "Browser sandbox disabled."; exit 1; fi
bash "$SOURCE/deploy/kiosk-mint.sh" --stop
wait_stopped
BROWSER_PID="$(tail -n 1 "$TEST_DIR/launches")"
if kill -0 "$BROWSER_PID" 2>/dev/null; then echo "Browser survived stop."; exit 1; fi
bash "$SOURCE/deploy/kiosk-mint.sh" &
KIOSK_PID=$!
wait_launches 3
kill "$KIOSK_PID"
wait_stopped
BROWSER_PID="$(tail -n 1 "$TEST_DIR/launches")"
if kill -0 "$BROWSER_PID" 2>/dev/null; then echo "Browser survived supervisor termination."; exit 1; fi
echo "Mint kiosk checks passed: server readiness, crash restart, single instance, isolated profile, sandbox, stop, restart and signal cleanup."
