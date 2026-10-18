<?php
declare(strict_types=1);
$sourceDir=sys_get_temp_dir().'/pulse-restore-source-'.bin2hex(random_bytes(5));
$targetDir=sys_get_temp_dir().'/pulse-restore-target-'.bin2hex(random_bytes(5));
putenv('PULSE_STORAGE='.$sourceDir);
require dirname(__DIR__).'/app/bootstrap.php';boot();
initialize(['token'=>setupToken(),'name'=>'Restore Admin','email'=>'restore@example.invalid','password'=>'restore-test-only-123','store'=>'Restore Test','language'=>'en','duration'=>20]);
db()->exec("INSERT INTO employees(name,role) VALUES('Original name','member')");
$image=imagecreatetruecolor(40,40);$filename=str_repeat('b',40).'.jpg';imagejpeg($image,storage('media/'.$filename));
db()->prepare('INSERT INTO media(filename,label,kind,created_at) VALUES(?,?,?,?)')->execute([$filename,'Backup image','gallery',gmdate('c')]);
$backup=storage('backups/'.createBackup());
function restoreRun(string $file,string $target): array {
    $pipes=[];$p=proc_open([PHP_BINARY,dirname(__DIR__).'/bin/restore.php',$file,'--confirm'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,array_replace(getenv(),['PULSE_STORAGE'=>$target]));
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return [proc_close($p),$out,$error];
}
function ensure(bool $condition,string $message): void {if(!$condition)throw new RuntimeException($message);}
try{
    [$code,$out,$error]=restoreRun($backup,$targetDir);ensure($code===0,'Restore failed: '.$error);
    $db=new PDO('sqlite:'.$targetDir.'/pulse.sqlite');
    ensure($db->query('SELECT name FROM employees')->fetchColumn()==='Original name','Database was not restored.');
    ensure(hash_file('sha256',$targetDir.'/media/'.$filename)===hash_file('sha256',storage('media/'.$filename)),'Photo was not restored.');
    $db->exec("UPDATE employees SET name='Changed'");$db=null;
    file_put_contents($targetDir.'/sessions/sess_test','test');
    [$code,$out,$error]=restoreRun($backup,$targetDir);ensure($code===0,'Replacement restore failed: '.$error);
    ensure(str_contains($out,'Before-restore backup:'),'Safety backup missing.');
    ensure(!is_file($targetDir.'/sessions/sess_test'),'Old session was not invalidated.');
    $bad=$sourceDir.'/bad-checksum.zip';copy($backup,$bad);$zip=new ZipArchive();$zip->open($bad);
    $meta=json_decode($zip->getFromName('manifest.json'),true);$meta['database_sha256']=str_repeat('0',64);$zip->addFromString('manifest.json',jsonValue($meta));$zip->close();
    ensure(restoreRun($bad,$targetDir)[0]===1,'Corrupt backup was accepted.');
    ensure(count(glob($targetDir.'/backups/restore-*.tmp')?:[])===0,'Restore temporary file was not removed.');
    $badPath=$sourceDir.'/bad-path.zip';$zip=new ZipArchive();$zip->open($badPath,ZipArchive::CREATE);$zip->addFromString('../escape.txt','no');$zip->close();
    ensure(restoreRun($badPath,$targetDir)[0]===1,'Path traversal archive was accepted.');
    $db=new PDO('sqlite:'.$targetDir.'/pulse.sqlite');ensure($db->query('SELECT name FROM employees')->fetchColumn()==='Original name','Rejected restore changed existing data.');
    echo "Restore checks passed: new database, replacement, photo integrity, safety backup, sessions, checksum, path traversal, cleanup.\n";
}catch(Throwable $ex){fwrite(STDERR,$ex->getMessage()."\n");exit(1);}

