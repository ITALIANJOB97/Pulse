<?php
declare(strict_types=1);
require_once __DIR__.'/update_protocol.php';

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
function checkUpdates(bool $automatic=false): array {
    if(updaterManaged()){
        $state=managedUpdateState();
        if(!$automatic||time()-(int)($state['checked_epoch']??0)>=21600)setSetting('update_requested',bin2hex(random_bytes(12)));
        return ['error'=>null,'queued'=>true];
    }
    return notificationUpdateCheck($automatic);
}
function publicUpdateState(bool $admin=true): array {
    if(!$admin)return ['available'=>false,'version'=>null,'notes'=>null,'installed'=>null];
    $state = updaterManaged()?managedUpdateState():setting('update_state', []);
    $release = $state['release'] ?? null;
    $available = is_array($release) && updateVersionValid($release['version']??null) && version_compare($release['version'],version(),'>');
    if($available&&empty($release['mandatory'])&&setting('update_skip','')===$release['version'])$available=false;
    $event = setting('version_event');
    if ($event && time()-strtotime($event['at'])>86400) $event = null;
    return [
        'available' => $available, 'version' => $available ? $release['version'] : null,
        'notes' => $available ? ($release['notes']??[]) : null, 'installed' => $event,
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
        if(($config['schema']??null)===1&&($config['origin']??'')===updateSource()['baseUrl'])return 'update_source_blog';
        if(($config['schema']??null)===3&&($config['manifest']??'')===updateManifestUrl())return 'update_source_protocol';
        if(($config['schema']??null)===2&&($config['repository']??'')===updateSource()['repository'])return ($config['private']??true)?'update_source_github_private':'update_source_github';
    }catch(Throwable){}
    return 'update_source_unknown';
}
function managedUpdateState(): array {
    $file=updaterPath('status.json');
    if(!is_file($file)||filesize($file)>1048576)return [];
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
