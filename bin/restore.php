#!/usr/bin/env php
<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
$temp=null;$zip=null;$opened=false;$status=0;
try{
    if(($argv[2]??'')!=='--confirm')throw new RuntimeException('Stop the web server first. Usage: php bin/restore.php /path/backup.zip --confirm');
    boot();
    $file=realpath($argv[1]??'');
    if(!$file||!is_file($file))throw new RuntimeException('Backup not found.');
    $zip=new ZipArchive();
    if($zip->open($file,ZipArchive::RDONLY)!==true)throw new RuntimeException('Cannot open backup.');
    $opened=true;
    $seen=[];$total=0;
    for($i=0;$i<$zip->numFiles;$i++){
        $entry=$zip->statIndex($i);$name=$entry['name'];
        if(isset($seen[$name])||!preg_match('~^(?:manifest\.json|pulse\.sqlite|media/[a-f0-9]{40}\.jpg)$~D',$name))throw new RuntimeException('Unexpected archive entry.');
        $seen[$name]=true;$total+=$entry['size'];
        if($total>2*1024*1024*1024)throw new RuntimeException('Backup exceeds the 2 GiB restore limit.');
    }
    if(!isset($seen['manifest.json'],$seen['pulse.sqlite']))throw new RuntimeException('Incomplete backup.');
    if($zip->statName('manifest.json')['size']>65536)throw new RuntimeException('Invalid manifest.');
    $meta=json_decode($zip->getFromName('manifest.json'),true,32,JSON_THROW_ON_ERROR);
    if(($meta['app']??'')!=='pulse-dietikon'||!is_string($meta['version']??null)||!preg_match('/^\d+\.\d+\.\d+$/D',$meta['version'])||version_compare($meta['version'],version(),'>'))throw new RuntimeException('Incompatible backup version.');
    $temp=storage('backups/restore-'.bin2hex(random_bytes(8)).'.sqlite.tmp');
    $stream=$zip->getStream('pulse.sqlite');$out=fopen($temp,'xb');
    if(!$stream||!$out)throw new RuntimeException('Cannot read database.');
    stream_copy_to_stream($stream,$out);fclose($stream);fclose($out);
    if(!is_string($meta['database_sha256']??null)||!hash_equals($meta['database_sha256'],hash_file('sha256',$temp)))throw new RuntimeException('Database checksum mismatch.');
    $source=new SQLite3($temp,SQLITE3_OPEN_READONLY);
    if($source->querySingle('PRAGMA integrity_check')!=='ok'||(int)$source->querySingle('SELECT MAX(version) FROM schema_migrations')>1||(int)$source->querySingle('SELECT COUNT(*) FROM admins')<1)throw new RuntimeException('Invalid application database.');
    $rows=$source->query('SELECT filename FROM media');$files=[];
    while($row=$rows->fetchArray(SQLITE3_ASSOC)){
        $name=$row['filename'];
        if(!preg_match('/^[a-f0-9]{40}\.jpg$/D',$name)||!isset($seen['media/'.$name]))throw new RuntimeException('A referenced photo is missing.');
        if(is_link(storage('media/'.$name)))throw new RuntimeException('Unexpected symbolic link.');
        $files[]=$name;
    }
    if(installed())echo 'Before-restore backup: '.createBackup('manual').PHP_EOL;
    foreach($files as $name){
        $stream=$zip->getStream('media/'.$name);
        $stage=storage('media/'.bin2hex(random_bytes(20)).'.restore.tmp');
        $out=fopen($stage,'xb');if(!$stream||!$out)throw new RuntimeException('Cannot restore photo.');
        stream_copy_to_stream($stream,$out);fclose($stream);fclose($out);
        $target=storage('media/'.$name);
        if(is_file($target)){
            if(hash_file('sha256',$target)!==hash_file('sha256',$stage)){unlink($stage);throw new RuntimeException('Photo conflict; existing data was preserved.');}
            unlink($stage);
        }else{rename($stage,$target);@chmod($target,0640);}
    }
    $targetDb=new SQLite3(storage('pulse.sqlite'));$targetDb->busyTimeout(5000);
    if(!$source->backup($targetDb))throw new RuntimeException('Database restore failed.');
    $targetDb->close();$source->close();
    foreach(glob(storage('sessions/sess_*'))?:[] as $session){if(is_file($session)&&!is_link($session))unlink($session);}
    @unlink(storage('setup-token'));
    echo "Restore complete. Restart the web server and sign in with an account from the backup.\n";
}catch(Throwable $ex){fwrite(STDERR,$ex->getMessage().PHP_EOL);$status=1;}
finally{
    if($opened)$zip->close();
    if(isset($source)){try{$source->close();}catch(Throwable $ignored){}}
    if(isset($targetDb)){try{$targetDb->close();}catch(Throwable $ignored){}}
    if($temp&&is_file($temp))unlink($temp);
}
exit($status);

