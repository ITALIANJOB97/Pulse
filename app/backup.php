<?php
declare(strict_types=1);

function snapshot(string $kind): void {
    if (!in_array($kind,['daily','weekly'],true)) throw new InvalidArgumentException('Invalid snapshot kind.');
    $period = (new DateTimeImmutable())->format($kind === 'weekly' ? 'o-\WW' : 'Y-m-d');
    $q = db()->prepare('INSERT INTO snapshots VALUES(?,?,?,?) ON CONFLICT(kind,period) DO UPDATE SET payload=excluded.payload,captured_at=excluded.captured_at');
    $q->execute([$kind,$period,jsonValue(screenData()),gmdate('c')]);
}
function createBackup(string $kind = 'manual'): string {
    if (!in_array($kind,['daily','weekly','manual','preupdate'],true)) throw new InvalidArgumentException('Invalid backup kind.');
    $lock = fopen(storage('backup.lock'),'c');
    if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) throw new RuntimeException('Another backup is running.');
    $temp = storage('backups/' . bin2hex(random_bytes(12)) . '.sqlite.tmp');
    $name = $kind . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.zip';
    $target = storage('backups/' . $name);
    $partial = $target . '.partial';
    $zip = null;
    try {
        if (in_array($kind,['daily','weekly'],true)) snapshot($kind);
        $source = new SQLite3(storage('pulse.sqlite'),SQLITE3_OPEN_READONLY);
        $source->busyTimeout(5000);
        $copy = new SQLite3($temp);
        if (!$source->backup($copy)) throw new RuntimeException('Database backup failed.');
        if ($copy->querySingle('PRAGMA integrity_check') !== 'ok') throw new RuntimeException('Database integrity check failed.');
        $rows = $copy->query('SELECT filename FROM media');
        $names = [];
        while ($row = $rows->fetchArray(SQLITE3_ASSOC)) $names[] = $row['filename'];
        $copy->close(); $source->close();
        $zip = new ZipArchive();
        if ($zip->open($partial,ZipArchive::CREATE|ZipArchive::EXCL) !== true) throw new RuntimeException('Cannot create backup archive.');
        if (!$zip->addFile($temp,'pulse.sqlite')) throw new RuntimeException('Cannot archive database.');
        foreach ($names as $file) {
            if (!preg_match('/^[a-f0-9]{40}\.jpg$/D',$file) || !is_file(storage('media/'.$file))) throw new RuntimeException('A referenced image is missing.');
            if (!$zip->addFile(storage('media/'.$file),'media/'.$file)) throw new RuntimeException('Cannot archive image.');
            $zip->setCompressionName('media/'.$file,ZipArchive::CM_STORE);
        }
        $zip->addFromString('manifest.json',jsonValue(['app'=>'pulse-dietikon','version'=>version(),'created_at'=>gmdate('c'),'kind'=>$kind,'database_sha256'=>hash_file('sha256',$temp)]));
        if (!$zip->close()) throw new RuntimeException('Cannot finalize backup.');
        $zip = null;
        if (is_file($target) || !rename($partial,$target)) throw new RuntimeException('Cannot publish backup.');
        @chmod($target,0640);
        $files = glob(storage('backups/'.$kind.'-*.zip')) ?: [];
        rsort($files);
        $keep = ['daily'=>30,'weekly'=>12,'manual'=>10,'preupdate'=>5][$kind];
        foreach (array_slice($files,$keep) as $old) unlink($old);
        setSetting('last_backup',['file'=>$name,'at'=>gmdate('c')]);
        return $name;
    } catch (Throwable $ex) {
        if ($zip instanceof ZipArchive) { try { @$zip->close(); } catch (Throwable) {} }
        if (is_file($partial)) @unlink($partial);
        throw $ex;
    } finally {
        if (is_file($temp)) unlink($temp);
        flock($lock,LOCK_UN); fclose($lock);
    }
}
function backupList(): array {
    $files = array_merge(glob(storage('backups/*.zip')) ?: [],glob(updaterPath('backups/preupdate-*.zip')) ?: []); rsort($files);
    return array_map(static fn($f)=>['name'=>basename($f),'size'=>filesize($f),'date'=>date('c',filemtime($f))],$files);
}
