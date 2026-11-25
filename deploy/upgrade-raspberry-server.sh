#!/usr/bin/env bash
# Updates trusted deployment components only when explicitly run by the operator.
set -euo pipefail
[[ "$(id -u)" == 0 ]] || { echo "Eseguire con sudo." >&2; exit 1; }
if [[ $# != 0 && !( $# == 2 && "$1" == --channel && "$2" == github ) ]]; then
    echo "Raspberry usa esclusivamente il canale GitHub." >&2; exit 1
fi
SOURCE="$(cd "$(dirname "$0")/.." && pwd)"
echo "Aggiornamento con backup verificato. Non spegnere il Raspberry."
exec python3 "$SOURCE/deploy/updater.py" install --source "$SOURCE" --channel github
