#!/usr/bin/env bash
set -Eeuo pipefail
[[ "$(id -u)" == 0 ]] || { echo "Eseguire: sudo bash backup-e-aggiorna.sh" >&2; exit 1; }
SOURCE="$(cd "$(dirname "$0")" && pwd)"
exec python3 -u "$SOURCE/ripara-e-aggiorna.py"
