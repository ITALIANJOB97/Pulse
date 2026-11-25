#!/usr/bin/env python3
"""Network selection tests with temporary state and simulated NetworkManager."""
import contextlib
import importlib.util
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parent.parent
spec = importlib.util.spec_from_file_location("lan_wifi", ROOT / "deploy/raspberry-lan-wifi.py")
app = importlib.util.module_from_spec(spec)
spec.loader.exec_module(app)


class NetworkSelection(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix="pulse-network-test-")
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.state = self.root / "state"
        self.state.mkdir()
        (self.state / "enabled").write_text("enabled")
        self.net = self.root / "net"
        self.net.mkdir()

    def fixture(self, name="eth0", carrier="1", physical=True):
        interface = self.net / name
        interface.mkdir()
        if physical:
            (interface / "device").mkdir()
        (interface / "carrier").write_text(carrier)

    def select(self, status="100 (connected)", ip="192.168.1.12/24", name="eth0"):
        def nm(*args):
            if args == ("-t", "-f", "DEVICE,TYPE", "device", "status"):
                return name + ":ethernet\nwlan0:wifi\nlo:loopback\ntailscale0:tun"
            if "GENERAL.STATE" in args:
                return status
            if "IP4.ADDRESS" in args:
                return ip
            raise AssertionError(args)
        with patch.object(app, "nm", side_effect=nm):
            return app.ready_ethernet(self.net)

    def test_only_connected_physical_ethernet_with_address_is_ready(self):
        self.fixture()
        self.assertEqual(self.select(), ["eth0"])

    def test_cable_without_ip_keeps_wifi(self):
        self.fixture()
        self.assertEqual(self.select(ip=""), [])
        self.assertEqual(self.select(status="70 (connecting (getting IP configuration))"), [])

    def test_unplugged_cable_is_ignored_even_with_stale_ip(self):
        self.fixture(carrier="0")
        self.assertEqual(self.select(), [])

    def test_virtual_ethernet_is_ignored(self):
        self.fixture(name="veth123", physical=False)
        self.assertEqual(self.select(name="veth123"), [])

    def test_link_local_invalid_ipv6_loopback_and_multicast_are_ignored(self):
        self.fixture()
        for ip in ("169.254.2.1/16", "127.0.0.1/8", "0.0.0.0/0", "224.0.0.1/24", "::1/128", "bad"):
            with self.subTest(ip=ip):
                self.assertEqual(self.select(ip=ip), [])

    def test_usb_ethernet_and_multiple_addresses(self):
        self.fixture(name="enx001122")
        self.assertEqual(self.select(name="enx001122", ip="169.254.3.2/16\n10.4.1.8/24"), ["enx001122"])

    def test_lan_disables_wifi_and_remembers_ownership(self):
        with patch.object(app, "ready_ethernet", return_value=["eth0"]), \
                patch.object(app, "radio", side_effect=["enabled", "disabled"]), patch.object(app, "nm") as nm:
            app.reconcile(self.state)
        nm.assert_called_once_with("radio", "wifi", "off")
        self.assertTrue((self.state / "wifi-disabled").exists())

    def test_unplug_restores_only_our_wifi_change(self):
        (self.state / "wifi-disabled").write_text("owned")
        with patch.object(app, "ready_ethernet", return_value=[]), \
                patch.object(app, "radio", side_effect=["disabled", "enabled"]), patch.object(app, "nm") as nm:
            app.reconcile(self.state)
        nm.assert_called_once_with("radio", "wifi", "on")
        self.assertFalse((self.state / "wifi-disabled").exists())

    def test_manually_disabled_wifi_is_never_enabled(self):
        with patch.object(app, "ready_ethernet", return_value=["eth0"]), \
                patch.object(app, "radio", return_value="disabled"), patch.object(app, "nm") as nm:
            app.reconcile(self.state)
        self.assertFalse((self.state / "wifi-disabled").exists())
        with patch.object(app, "ready_ethernet", return_value=[]), patch.object(app, "nm") as nm:
            app.reconcile(self.state)
        nm.assert_not_called()

    def test_no_lan_no_owned_change_does_not_touch_wifi(self):
        with patch.object(app, "ready_ethernet", return_value=[]), patch.object(app, "nm") as nm:
            app.reconcile(self.state)
        nm.assert_not_called()

    def test_radio_failure_keeps_recovery_marker(self):
        with patch.object(app, "ready_ethernet", return_value=["eth0"]), \
                patch.object(app, "radio", return_value="enabled"), \
                patch.object(app, "nm", side_effect=subprocess.CalledProcessError(1, "nmcli")):
            with self.assertRaises(subprocess.CalledProcessError):
                app.reconcile(self.state)
        self.assertTrue((self.state / "wifi-disabled").exists())

    def test_failed_restore_keeps_marker_for_next_retry(self):
        (self.state / "wifi-disabled").write_text("owned")
        with patch.object(app, "radio", return_value="disabled"), patch.object(app, "nm"):
            with self.assertRaises(RuntimeError):
                app.restore_wifi(self.state)
        self.assertTrue((self.state / "wifi-disabled").exists())

    def test_nm_failure_does_not_turn_wifi_off(self):
        with patch.object(app, "ready_ethernet", side_effect=RuntimeError("NM unavailable")), patch.object(app, "nm") as nm:
            with self.assertRaises(RuntimeError):
                app.reconcile(self.state)
        nm.assert_not_called()

    def test_no_enabled_marker_no_network_operations(self):
        (self.state / "enabled").unlink()
        with patch.object(app, "ready_ethernet") as wired, patch.object(app, "nm") as nm:
            app.reconcile(self.state)
        wired.assert_not_called()
        nm.assert_not_called()

    def test_power_loss_marker_restores_wifi_on_new_instance_without_cable(self):
        (self.state / "wifi-disabled").write_text("persisted from previous boot")
        with patch.object(app, "ready_ethernet", return_value=[]), \
                patch.object(app, "radio", side_effect=["disabled", "enabled"]), patch.object(app, "nm") as nm:
            app.reconcile(self.state)
        nm.assert_called_once_with("radio", "wifi", "on")

    def test_disable_stops_worker_before_restoring_and_persists_disabled(self):
        operations = []
        @contextlib.contextmanager
        def lock():
            operations.append("lock")
            yield
        with patch.object(app, "STATE", self.state), patch.object(app, "state_lock", lock), \
                patch.object(app, "command", side_effect=lambda *args: operations.append(args)), \
                patch.object(app, "restore_wifi", side_effect=lambda: operations.append("restore")):
            app.disable()
        self.assertEqual(operations, [
            ("systemctl", "disable", "--now", app.TIMER), ("systemctl", "stop", app.SERVICE), "lock", "restore"])
        self.assertFalse((self.state / "enabled").exists())

    def test_install_writes_correct_units_and_enables_timer(self):
        program = self.root / "pulse-lan-wifi"
        units = self.root / "units"
        units.mkdir()
        @contextlib.contextmanager
        def lock():
            yield
        with patch.object(app, "STATE", self.state), patch.object(app, "PROGRAM", program), \
                patch.object(app, "UNITS", units), patch.object(app, "preflight"), patch.object(app, "state_lock", lock), \
                patch.object(app, "command") as command, patch.object(app, "status"):
            app.install()
        self.assertTrue(program.is_file())
        self.assertIn("OnUnitInactiveSec=5s", (units / app.TIMER).read_text())
        self.assertIn("/usr/local/bin/pulse-lan-wifi tick", (units / app.SERVICE).read_text())
        command.assert_any_call("systemctl", "enable", "--now", app.TIMER)
        command.assert_any_call("systemctl", "start", app.SERVICE)


if __name__ == "__main__":
    unittest.main()
