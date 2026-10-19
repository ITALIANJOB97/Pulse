<?php
declare(strict_types=1);

function syncVersion(): void {
    $previous = setting('installed_version', version());
    if ($previous === version()) return;
    db()->exec('BEGIN IMMEDIATE');
    try {
        $previous = setting('installed_version', version());
        if ($previous !== version()) {
            setSetting('installed_version', version());
            setSetting('version_event', ['from' => $previous, 'version' => version(), 'at' => gmdate('c')]);
            touchRevision();
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) { db()->exec('ROLLBACK'); throw $ex; }
}
function httpsUrl(string $url): bool {
    return strlen($url) <= 2000 && filter_var($url,FILTER_VALIDATE_URL)
        && strtolower((string)parse_url($url,PHP_URL_SCHEME)) === 'https'
        && parse_url($url,PHP_URL_USER) === null && parse_url($url,PHP_URL_PASS) === null;
}
function validateRelease(array $release): array {
    if (($release['app'] ?? '') !== 'pulse-dietikon' || !is_string($release['version'] ?? null) || !preg_match('/^\d+\.\d+\.\d+$/D',$release['version'])) {
        throw new DomainException('Invalid release manifest.');
    }
    $url = $release['release_url'] ?? '';
    if (!is_string($url) || ($url !== '' && !httpsUrl($url))) throw new DomainException('Invalid release URL.');
    $notes = $release['notes'] ?? [];
    if (!is_array($notes)) throw new DomainException('Invalid release notes.');
    $clean = [];
    foreach (['en','de','fr'] as $lang) {
        $text = $notes[$lang] ?? '';
        if (!is_string($text) || mb_strlen($text) > 2000) throw new DomainException('Invalid release notes.');
        $clean[$lang] = $text;
    }
    return ['version' => $release['version'], 'release_url' => $url, 'notes' => $clean];
}
function checkUpdates(): array {
    if(updaterManaged()){
        setSetting('update_requested',bin2hex(random_bytes(12)));
        return ['error'=>null,'queued'=>true];
    }
    $url = setting('update_url', '');
    if ($url === '') {
        $state = ['checked_at' => gmdate('c'), 'release' => null, 'error' => null, 'configured' => false];
        setSetting('update_state',$state); return $state;
    }
    $state = setting('update_state', ['release'=>null]);
    try {
        if (!httpsUrl($url)) throw new RuntimeException('Update source must use HTTPS.');
        $body = '';
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_USERAGENT => 'PULSE/' . version(),
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_WRITEFUNCTION => static function($handle,string $chunk) use (&$body): int {
                if (strlen($body)+strlen($chunk)>65536) return 0;
                $body .= $chunk; return strlen($chunk);
            },
        ]);
        $ok = curl_exec($curl); $code = curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
        if ($ok === false || $code !== 200) throw new RuntimeException('Release source unavailable (HTTP ' . $code . ').');
        $data = json_decode($body,true,32,JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new RuntimeException('Invalid release manifest.');
        $state['release'] = validateRelease($data); $state['error'] = null;
    } catch (Throwable $ex) {
        $state['error'] = 'check_failed';
        error_log('[PULSE update] ' . $ex->getMessage());
    }
    $state['checked_at'] = gmdate('c'); $state['configured'] = true;
    setSetting('update_state',$state); return $state;
}
function publicUpdateState(): array {
    $state = updaterManaged()?managedUpdateState():setting('update_state', []);
    $release = $state['release'] ?? null;
    $available = is_array($release) && version_compare($release['version'],version(),'>');
    $event = setting('version_event');
    if ($event && time()-strtotime($event['at'])>86400) $event = null;
    return [
        'available' => $available, 'version' => $available ? $release['version'] : null,
        'notes' => $available ? $release['notes'] : null, 'installed' => $event,
    ];
}

function updaterPath(string $file): string {
    return rtrim($GLOBALS['pulse_config']['updater_state'],'/\\').'/'.$file;
}
function updaterManaged(): bool { return is_file($GLOBALS['pulse_config']['updater_config']); }
function updaterSourceLabel(): string {
    $path=$GLOBALS['pulse_config']['updater_config'];
    try {
        if(!is_file($path)||filesize($path)>4096)return 'update_source_unknown';
        $config=json_decode((string)file_get_contents($path),true,8,JSON_THROW_ON_ERROR);
        if(($config['schema']??null)===1&&($config['origin']??'')==='https://www.luigipranzo.com/myfile/Pulse/')return 'update_source_blog';
        if(($config['schema']??null)===2&&($config['repository']??'')==='ITALIANJOB97/Pulse')return ($config['private']??true)?'update_source_github_private':'update_source_github';
    }catch(Throwable){}
    return 'update_source_unknown';
}
function managedUpdateState(): array {
    $file=updaterPath('status.json');
    if(!is_file($file)||filesize($file)>131072)return [];
    try{$data=json_decode((string)file_get_contents($file),true,32,JSON_THROW_ON_ERROR);return is_array($data)?$data:[];}
    catch(Throwable){return [];}
}
function applicationHealth(): array {
    $file=storage('pulse.sqlite');
    if(!is_file($file))throw new RuntimeException('Database not found.');
    $check=new SQLite3($file,SQLITE3_OPEN_READONLY);$check->busyTimeout(5000);
    if($check->querySingle('PRAGMA quick_check')!=='ok'||(int)$check->querySingle('SELECT MAX(version) FROM schema_migrations')!==1)throw new RuntimeException('Database check failed.');
    if((int)$check->querySingle('SELECT COUNT(*) FROM admins')<1)throw new RuntimeException('Initial setup is incomplete.');
    $rows=$check->query('SELECT filename FROM media');
    while($row=$rows->fetchArray(SQLITE3_ASSOC)){
        if(!preg_match('/^[a-f0-9]{40}\.jpg$/D',$row['filename'])||!is_file(storage('media/'.$row['filename'])))throw new RuntimeException('Missing media.');
    }
    $check->close();
    return ['app'=>'pulse-dietikon','version'=>version(),'schema'=>1,'ok'=>true];
}
