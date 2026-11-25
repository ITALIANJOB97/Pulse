#!/usr/bin/env bash
# Disposable Linux workstation tests: real temporary SQLite, simulated services/browser.
set -euo pipefail
cd "$(dirname "$0")/.."
for file in deploy/install-raspberry*.sh deploy/upgrade-raspberry*.sh deploy/diagnose-raspberry.sh deploy/install-mint-server.sh deploy/kiosk-mint.sh deploy/enable-kiosk-mint.sh; do
    bash -n "$file"
done
export PYTHONWARNINGS=ignore::ResourceWarning
python3 tests/updater.py
python3 tests/update-protocol.py
python3 tests/support-mint.py
runuser -u nobody -- bash tests/kiosk-mint.sh
