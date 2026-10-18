#!/usr/bin/env bash
# This entry point can run from an extracted USB package without installing PULSE.
set -euo pipefail
SOURCE="$(cd "$(dirname "$0")/.." && pwd)"
command -v python3 >/dev/null || { echo "Python 3 non trovato. Linux Mint Desktop lo include normalmente." >&2; exit 1; }
exec python3 "$SOURCE/deploy/support-mint.py" diagnose "$@"
