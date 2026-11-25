<?php
declare(strict_types=1);

// Explicit CLI opt-in for the new branch devices, never enabled by an app update.
function prepareBranchSetup(): void {
    if (PHP_SAPI !== 'cli') throw new RuntimeException('Local preparation required.');
    if (installed() || setting('installed_at') !== null) throw new DomainException(t('already_installed'));
    setupToken(); // Normal recovery path; never disclose this code to a browser.
    $path = storage('branch-setup.json');
    $data = jsonValue(['version'=>1, 'prepared'=>true, 'created_at'=>gmdate('c')]);
    if (file_put_contents($path, $data, LOCK_EX) === false) throw new RuntimeException('Cannot prepare branch setup.');
    @chmod($path, 0640);
}
function cancelBranchSetup(): void {
    if (PHP_SAPI !== 'cli') throw new RuntimeException('Local preparation required.');
    $path = storage('branch-setup.json');
    if (is_file($path) && !unlink($path)) throw new RuntimeException('Cannot cancel branch setup.');
}
function branchSetupEnabled(): bool {
    if (installed() || setting('installed_at') !== null) return false;
    $path = storage('branch-setup.json');
    if (!is_file($path)) return false;
    $state = json_decode((string)file_get_contents($path), true);
    return is_array($state) && ($state['version'] ?? null) === 1 && ($state['prepared'] ?? null) === true;
}
function normalizedSetupIp(string $ip): string {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return '';
    $packed = inet_pton($ip);
    if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10)."\xff\xff") $packed = substr($packed, 12);
    return inet_ntop($packed);
}
function privateSetupIp(string $ip, bool $loopback = false): bool {
    $ip = normalizedSetupIp($ip);
    if ($ip === '') return false;
    if ($loopback && in_array($ip, ['127.0.0.1', '::1'], true)) return true;
    $packed = inet_pton($ip);
    if (strlen($packed) === 16) return (ord($packed[0]) & 0xfe) === 0xfc;
    $a = ord($packed[0]); $b = ord($packed[1]);
    return $a === 10 || ($a === 172 && $b >= 16 && $b <= 31) || ($a === 192 && $b === 168);
}
function branchIpAddresses(?array $interfaces = null): array {
    $interfaces ??= function_exists('net_get_interfaces') ? (@net_get_interfaces() ?: []) : [];
    $ips = [];
    foreach ($interfaces as $name => $interface) {
        if (!branchLanInterface((string)$name, $interface)) continue;
        foreach ($interface['unicast'] ?? [] as $address) {
            $ip = normalizedSetupIp((string)($address['address'] ?? ''));
            if (privateSetupIp($ip)) $ips[$ip] = $ip;
        }
    }
    $ips = array_values($ips);
    usort($ips, static fn($a, $b) => (str_contains($a, ':') <=> str_contains($b, ':')) ?: strnatcmp($a, $b));
    return $ips;
}
function branchLanInterface(string $name, array $interface): bool {
    return ($interface['up'] ?? false) === true
        && !preg_match('/^(lo$|docker|veth|br-|virbr|vboxnet|tun|tap|wg|tailscale|zt|ppp|ipsec|gre|sit)/i', $name);
}
function branchRouteIp(): string {
    // Let the OS choose its primary IPv4 source. UDP connect only: no payload,
    // DNS lookup or external service is used, and Internet access is not required.
    $socket = @stream_socket_client('udp://192.0.2.1:9', $code, $error, 0.2, STREAM_CLIENT_CONNECT);
    if ($socket === false) return '';
    $local = stream_socket_get_name($socket, false);
    fclose($socket);
    return is_string($local) && preg_match('/^([0-9.]+):[0-9]+$/D', $local, $match)
        ? normalizedSetupIp($match[1]) : '';
}
function branchDisplayIp(?array $interfaces = null, ?string $preferredIp = null): string {
    $interfaces ??= function_exists('net_get_interfaces') ? (@net_get_interfaces() ?: []) : [];
    $candidates = [];
    foreach ($interfaces as $name => $interface) {
        if (!branchLanInterface((string)$name, $interface)) continue;
        foreach ($interface['unicast'] ?? [] as $address) {
            $ip = normalizedSetupIp((string)($address['address'] ?? ''));
            if (!privateSetupIp($ip)) continue;
            $candidates[] = ['ip'=>$ip, 'interface'=>(string)$name,
                'family'=>str_contains($ip, ':') ? 1 : 0,
                'link'=>preg_match('/^(eth|en)/i', (string)$name) ? 0 : (preg_match('/^wl/i', (string)$name) ? 1 : 2)];
        }
    }
    $preferredIp = normalizedSetupIp($preferredIp ?? branchRouteIp());
    foreach ($candidates as $candidate) if ($candidate['ip'] === $preferredIp) return $preferredIp;
    // Offline LAN or a VPN default route: prefer physical Ethernet, then Wi-Fi.
    // Keep IPv4 first for the simplest address to type on the manager's PC.
    usort($candidates, static fn($a, $b) =>
        [$a['family'], $a['link'], $a['interface'], $a['ip']] <=> [$b['family'], $b['link'], $b['interface'], $b['ip']]);
    return $candidates[0]['ip'] ?? '';
}
function branchSetupRequestAllowed(?array $server = null, ?array $interfaces = null): bool {
    $server ??= $_SERVER;
    if (!privateSetupIp((string)($server['REMOTE_ADDR'] ?? ''), true)) return false;
    // Direct requests to a local literal IP only: no DNS-rebinding or proxy path.
    foreach (['HTTP_FORWARDED', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED_HOST'] as $header) {
        if (isset($server[$header])) return false;
    }
    $host = (string)($server['HTTP_HOST'] ?? '');
    if (!preg_match('/^(\[[0-9a-f:]+\]|[0-9.]+)(?::([0-9]{1,5}))?$/iD', $host, $m)) return false;
    if (isset($m[2]) && ((int)$m[2] < 1 || (int)$m[2] > 65535)) return false;
    $ip = normalizedSetupIp(trim($m[1], '[]'));
    if ($ip === '') return false;
    $local = array_merge(['127.0.0.1', '::1'], branchIpAddresses($interfaces));
    $serverIp = normalizedSetupIp((string)($server['SERVER_ADDR'] ?? ''));
    if (privateSetupIp($serverIp, true)) $local[] = $serverIp;
    if (!in_array($ip, $local, true)) return false;
    if (($server['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') return false;
    if (isset($server['HTTP_ORIGIN'])) {
        $scheme = isset($server['HTTPS']) && $server['HTTPS'] !== 'off' ? 'https' : 'http';
        if (strtolower((string)$server['HTTP_ORIGIN']) !== strtolower($scheme.'://'.$host)) return false;
    }
    return true;
}
function branchSetupAllowed(): bool { return branchSetupEnabled() && branchSetupRequestAllowed(); }
function branchSetupUrl(string $ip): string {
    return 'http://'.(str_contains($ip, ':') ? '['.$ip.']' : $ip).':8080';
}
