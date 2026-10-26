#!/usr/bin/env bash
set -Eeuo pipefail
umask 0027
[[ "$(id -u)" == 0 ]] || { echo "Eseguire: sudo bash deploy/upgrade-mint.sh" >&2; exit 1; }
CHANNEL="blog"
if [[ $# == 2 && "$1" == "--channel" && ( "$2" == "blog" || "$2" == "github" ) ]]; then CHANNEL="$2"
elif [[ $# != 0 ]]; then echo "Uso: upgrade-mint.sh [--channel blog|github]" >&2; exit 1; fi
SOURCE="$(cd "$(dirname "$0")/.." && pwd)"
[[ -L /opt/pulse/current && -f /var/lib/pulse/pulse.sqlite ]] || { echo "Installazione PULSE esistente non trovata. Nessun dato modificato." >&2; exit 1; }
printf 'Aggiornamento PULSE con backup verificato. Non spegnere il PC.\n'
python3 "$SOURCE/deploy/updater.py" install --source "$SOURCE" --channel "$CHANNEL"
printf '\nAprire http://127.0.0.1:8080/admin e accedere con le credenziali esistenti.\n'
