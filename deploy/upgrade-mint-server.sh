#!/usr/bin/env bash
set -Eeuo pipefail
umask 0027
[[ "$(id -u)" == 0 ]] || { echo "Eseguire: sudo bash deploy/upgrade-mint.sh" >&2; exit 1; }
[[ $# == 0 ]] || { echo "Parametri non supportati." >&2; exit 1; }
SOURCE="$(cd "$(dirname "$0")/.." && pwd)"
[[ -L /opt/pulse/current && -f /var/lib/pulse/pulse.sqlite ]] || { echo "Installazione PULSE esistente non trovata. Nessun dato modificato." >&2; exit 1; }
printf 'Aggiornamento PULSE con backup verificato. Non spegnere il PC.\n'
python3 "$SOURCE/deploy/updater.py" install --source "$SOURCE"
printf '\nAprire http://127.0.0.1:8080/admin e accedere con le credenziali esistenti.\n'
