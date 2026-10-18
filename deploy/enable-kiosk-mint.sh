#!/usr/bin/env bash
# XDG autostart works with Cinnamon, MATE and Xfce.
set -euo pipefail
[[ "$(id -u)" != 0 ]] || { echo "Eseguire dal proprio utente desktop, senza sudo." >&2; exit 1; }
CONFIG="${XDG_CONFIG_HOME:-$HOME/.config}"
DATA="${XDG_DATA_HOME:-$HOME/.local/share}"
AUTOSTART="$CONFIG/autostart/pulse-tv.desktop"
LAUNCHER="$DATA/applications/pulse-tv.desktop"
STOP_LAUNCHER="$DATA/applications/pulse-tv-stop.desktop"
umask 0022
case "${1:-}" in
    --disable)
        if [[ -f "$AUTOSTART" ]]; then
            grep -qx 'X-PULSE-Managed=true' "$AUTOSTART" || { echo "File autostart personalizzato: modificarlo manualmente."; exit 1; }
            rm -- "$AUTOSTART"
        fi
        /usr/local/bin/pulse-kiosk --stop
        echo "Avvio automatico disabilitato. Il server e i dati restano disponibili."
        exit 0
        ;;
    "") [[ $# == 0 ]] || exit 1 ;;
    *) echo "Uso: pulse-enable-kiosk [--disable]" >&2; exit 1 ;;
esac
[[ -x /usr/local/bin/pulse-kiosk ]] || { echo "Installare prima il server con deploy/install-mint.sh."; exit 1; }
for file in "$AUTOSTART" "$LAUNCHER" "$STOP_LAUNCHER"; do
    if [[ -e "$file" || -L "$file" ]]; then
        [[ ! -L "$file" ]] && grep -qx 'X-PULSE-Managed=true' "$file" ||
            { echo "File esistente non gestito da PULSE: $file"; exit 1; }
    fi
done
mkdir -p "$CONFIG/autostart" "$DATA/applications"
cat > "$LAUNCHER" <<'DESKTOP'
[Desktop Entry]
Type=Application
Name=PULSE TV
Comment=Dashboard Dietikon a schermo intero
Exec=/usr/local/bin/pulse-kiosk
TryExec=/usr/local/bin/pulse-kiosk
Icon=video-display
Terminal=false
Categories=Office;
StartupNotify=false
X-GNOME-Autostart-enabled=true
X-PULSE-Managed=true
DESKTOP
cp -- "$LAUNCHER" "$AUTOSTART"
cat > "$STOP_LAUNCHER" <<'DESKTOP'
[Desktop Entry]
Type=Application
Name=Ferma PULSE TV
Comment=Chiude la TV per la manutenzione
Exec=/usr/local/bin/pulse-kiosk --stop
TryExec=/usr/local/bin/pulse-kiosk
Icon=process-stop
Terminal=false
Categories=Office;
StartupNotify=false
X-PULSE-Managed=true
DESKTOP
chmod 0644 "$LAUNCHER" "$AUTOSTART" "$STOP_LAUNCHER"
echo "PULSE TV si avviera al prossimo accesso al desktop di questo utente."
echo "Per provarla ora: pulse-kiosk"
echo "Per fermarla: pulse-kiosk --stop"
echo "Per disabilitare l'avvio automatico: pulse-enable-kiosk --disable"
