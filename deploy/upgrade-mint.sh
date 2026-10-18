#!/usr/bin/env bash
# Upgrade an existing PULSE installation and retain a sanitized USB support report.
set -euo pipefail
SOURCE="$(cd "$(dirname "$0")/.." && pwd)"
command -v python3 >/dev/null || { echo "Python 3 non trovato." >&2; exit 1; }
exec python3 "$SOURCE/deploy/support-mint.py" upgrade --source "$SOURCE" "$@"
