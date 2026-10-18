#!/usr/bin/env bash
# Public installer entry point: keep a sanitized report even when installation fails.
set -euo pipefail
SOURCE="$(cd "$(dirname "$0")/.." && pwd)"
command -v python3 >/dev/null || { echo "Python 3 non trovato. Linux Mint Desktop lo include normalmente." >&2; exit 1; }
exec python3 "$SOURCE/deploy/support-mint.py" install --source "$SOURCE" "$@"
