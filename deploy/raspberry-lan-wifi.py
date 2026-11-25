#!/usr/bin/env python3
# PULSE-LAN-WIFI-MANAGED:v1
"""Prefer connected physical Ethernet; restore only Wi-Fi disabled by this tool."""
import argparse
from contextlib import contextmanager
import ipaddress
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import time

MARK = "# PULSE-LAN-WIFI-MANAGED:v1"
PROGRAM = Path("/usr/local/bin/pulse-lan-wifi")
STATE = Path("/var/lib/pulse-network")
UNITS = Path("/etc/systemd/system")
SERVICE = "pulse-lan-wifi.service"
TIMER = "pulse-lan-wifi.timer"
ENV = {**os.environ, "LC_ALL": "C", "LANG": "C"}

SERVICE_BODY = f"""{MARK}
[Unit]
Description=PULSE Raspberry Ethernet and Wi-Fi selection
After=NetworkManager.service

[Service]
Type=oneshot
ExecStart=/usr/bin/python3 {PROGRAM.as_posix()} tick
TimeoutStartSec=30
StateDirectory=pulse-network
StateDirectoryMode=0700
NoNewPrivileges=true
PrivateTmp=true
ProtectHome=true
ProtectSystem=strict
"""
TIMER_BODY = f"""{MARK}
[Unit]
Description=Check PULSE Ethernet connection every five seconds

[Timer]
OnBootSec=10s
OnUnitInactiveSec=5s
AccuracySec=1s
Unit={SERVICE}

[Install]
WantedBy=timers.target
"""


def command(*args):
    return subprocess.check_output(args, text=True, env=ENV, stderr=subprocess.PIPE, timeout=20).strip()


def nm(*args):
    return command("/usr/bin/nmcli", "--wait", "5", "--colors", "no", *args)


def radio():
    value = nm("radio", "wifi")
    if value not in ("enabled", "disabled"):
        raise RuntimeError("Stato Wi-Fi non riconosciuto; rete non modificata.")
    return value


def usable_ipv4(value):
    try:
        address = ipaddress.ip_interface(value).ip
    except ValueError:
        return False
    return (address.version == 4 and not any((address.is_unspecified, address.is_loopback,
            address.is_link_local, address.is_multicast, address.is_reserved)))


def ready_ethernet(net=Path("/sys/class/net")):
    devices = nm("-t", "-f", "DEVICE,TYPE", "device", "status")
    ready = []
    for line in devices.splitlines():
        name, separator, kind = line.partition(":")
        if not separator or kind != "ethernet" or not re.fullmatch(r"[a-zA-Z0-9_.-]+", name):
            continue
        interface = net / name
        # Physical interfaces only: ignore VPNs, bridges and virtual Ethernet.
        if not (interface / "device").exists():
            continue
        try:
            if (interface / "carrier").read_text().strip() != "1":
                continue
        except OSError:
            continue  # Interface removed while enumerating, or no link.
        state = nm("-g", "GENERAL.STATE", "device", "show", name)
        if not re.match(r"^100(?:\s|$)", state):
            continue
        addresses = nm("-g", "IP4.ADDRESS", "device", "show", name)
        if any(usable_ipv4(address) for address in addresses.splitlines()):
            ready.append(name)
    return ready


def write_atomic(path, data, mode=0o600):
    descriptor, temporary = tempfile.mkstemp(prefix=".pulse-", dir=path.parent)
    try:
        with os.fdopen(descriptor, "wb") as out:
            out.write(data)
            out.flush()
            os.fsync(out.fileno())
        os.chmod(temporary, mode)
        os.replace(temporary, path)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


def restore_wifi(state=STATE):
    marker = state / "wifi-disabled"
    if marker.exists():
        if radio() == "disabled":
            nm("radio", "wifi", "on")
            if radio() != "enabled":
                raise RuntimeError("Riattivazione Wi-Fi non confermata; ritento al prossimo controllo.")
        marker.unlink()
        print("Wi-Fi riattivato; le reti salvate restano disponibili.", flush=True)


def reconcile(state=STATE, net=Path("/sys/class/net")):
    if not (state / "enabled").exists():
        return
    wired = ready_ethernet(net)
    marker = state / "wifi-disabled"
    if not wired:
        restore_wifi(state)
    elif radio() == "enabled":
        # Save ownership before switching: survive a power loss after the switch.
        write_atomic(marker, b"PULSE disabled a previously enabled Wi-Fi radio\n")
        nm("radio", "wifi", "off")
        if radio() != "disabled":
            raise RuntimeError("Disattivazione Wi-Fi non confermata; ritento al prossimo controllo.")
        print("LAN pronta su " + ", ".join(wired) + "; Wi-Fi disattivato.", flush=True)


def private_state():
    if STATE.is_symlink():
        raise RuntimeError("Cartella di stato non sicura.")
    STATE.mkdir(mode=0o700, exist_ok=True)
    if not STATE.is_dir() or STATE.stat().st_uid != 0 or STATE.stat().st_mode & 0o022:
        raise RuntimeError("La cartella di stato deve appartenere a root.")
    STATE.chmod(0o700)


@contextmanager
def state_lock():
    import fcntl
    private_state()
    with (STATE / "lock").open("a") as lock:
        fcntl.flock(lock, fcntl.LOCK_EX)
        yield


def preflight():
    if sys.platform != "linux" or os.geteuid() != 0:
        raise RuntimeError("Eseguire sul Raspberry con sudo python3 raspberry-lan-wifi.py install.")
    os_info = dict(line.split("=", 1) for line in Path("/etc/os-release").read_text().splitlines()
                   if "=" in line and not line.startswith("#"))
    os_info = {key: value.strip('\"\'') for key, value in os_info.items()}
    if (os_info.get("ID") not in ("debian", "raspbian") or os_info.get("VERSION_ID") != "13"
            or os_info.get("VERSION_CODENAME") != "trixie"
            or b"Raspberry Pi" not in Path("/proc/device-tree/model").read_bytes()):
        raise RuntimeError("Serve Raspberry Pi OS Trixie con NetworkManager.")
    if not Path("/usr/bin/nmcli").is_file() or not Path("/run/systemd/system").is_dir():
        raise RuntimeError("NetworkManager o systemd non disponibile.")
    command("systemctl", "is-active", "--quiet", "NetworkManager.service")


def managed_file(path, data, mode):
    if path.is_symlink():
        raise RuntimeError("Non sostituisco un collegamento: " + str(path))
    if path.exists():
        if path.stat().st_uid != 0 or path.stat().st_mode & 0o022:
            raise RuntimeError("File non protetto: " + str(path))
        old = path.read_bytes()
        if old == data:
            path.chmod(mode)
            return
        if MARK.encode() not in old[:150]:
            raise RuntimeError("File esistente non gestito da PULSE: " + str(path))
        backup = STATE / (path.name + ".previous-" + str(time.time_ns()))
        write_atomic(backup, old)
    write_atomic(path, data, mode)


def install():
    preflight()
    with state_lock():
        managed_file(PROGRAM, Path(__file__).read_bytes(), 0o755)
        managed_file(UNITS / SERVICE, SERVICE_BODY.encode(), 0o644)
        managed_file(UNITS / TIMER, TIMER_BODY.encode(), 0o644)
        write_atomic(STATE / "enabled", b"enabled\n")
    command("systemctl", "daemon-reload")
    command("systemctl", "enable", "--now", TIMER)
    command("systemctl", "start", SERVICE)
    command("systemctl", "is-enabled", "--quiet", TIMER)
    command("systemctl", "is-active", "--quiet", TIMER)
    print("Automatismo LAN/Wi-Fi attivo, anche dopo il riavvio. Controllo ogni 5 secondi.")
    status()


def disable():
    # Stop the worker before acquiring its lock; do not deadlock an active tick.
    command("systemctl", "disable", "--now", TIMER)
    command("systemctl", "stop", SERVICE)
    with state_lock():
        (STATE / "enabled").unlink(missing_ok=True)
        restore_wifi()
    print("Automatismo disattivato. Wi-Fi ripristinato se era stato spento da PULSE.")


def status():
    print("Automatismo: " + ("abilitato" if (STATE / "enabled").exists() else "disabilitato"))
    print("LAN con IPv4 valido: " + (", ".join(ready_ethernet()) or "nessuna"))
    print("Wi-Fi: " + radio())
    print("Wi-Fi spento da PULSE: " + ("si" if (STATE / "wifi-disabled").exists() else "no"))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("action", choices=("install", "tick", "status", "disable"))
    args = parser.parse_args()
    if sys.platform != "linux" or os.geteuid() != 0:
        raise RuntimeError("Eseguire sul Raspberry con sudo.")
    if args.action == "install":
        install()
    elif args.action == "disable":
        disable()
    elif args.action == "status":
        status()
    else:
        with state_lock():
            reconcile()


if __name__ == "__main__":
    try:
        main()
    except (OSError, RuntimeError, subprocess.SubprocessError) as error:
        print("PULSE rete: " + str(error), file=sys.stderr)
        sys.exit(1)
