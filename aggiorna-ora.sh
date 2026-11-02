#!/usr/bin/env bash
# Run from the extracted release on the existing Mint PC.
set -euo pipefail
SOURCE="$(cd "$(dirname "$0")" && pwd)"
exec bash "$SOURCE/deploy/upgrade-mint.sh" "$@"
