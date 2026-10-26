<?php
declare(strict_types=1);
// Always use an isolated temporary directory, never an installed database.
$dir=sys_get_temp_dir().'/pulse-core-test-'.bin2hex(random_bytes(6));
putenv('PULSE_STORAGE='.$dir);
require dirname(__DIR__).'/app/bootstrap.php';
boot();startSession();
$checks=0;
function check(bool $condition,string $message): void {
    global $checks;if(!$condition)throw new RuntimeException($message);$checks++;
}
function rejects(callable $fn,string $message): void {
    try{$fn();}catch(DomainException $ex){check(true,$message);return;}throw new RuntimeException($message);
}
try{
    check(!installed(),'Fresh installation must be empty.');
    $token=setupToken();
    $input=['token'=>'wrong','name'=>'Test Admin','email'=>'admin@example.invalid','password'=>'test-only-phrase-123','store'=>'Test Store','language'=>'en','duration'=>20];
    rejects(fn()=>initialize($input),'An invalid installation token was accepted.');
    $input['token']=$token;initialize($input);
    check(installed(),'Setup failed.');
    check(!is_file(storage('setup-token')),'Setup token was not removed.');
    $hash=db()->query('SELECT password_hash FROM admins')->fetchColumn();
    check(password_verify($input['password'],$hash)&&$hash!==$input['password'],'Password is not hashed.');
    rejects(fn()=>initialize($input),'Setup must not run twice.');
    check((int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn()===1,'Duplicate administrator.');
    $_SERVER['REMOTE_ADDR']='192.0.2.10';
    check(login($input['email'],$input['password']),'Correct login failed.');
    check(currentAdmin()!==null,'Session did not retain the administrator.');
    $_SESSION['admin_id']=null;
    check(!screenAllowed(),'An unauthenticated remote display was allowed.');
    $_COOKIE['pulse_screen']=setting('screen_key');
    check(screenAllowed(),'The display key was rejected.');
    $_COOKIE['pulse_screen']='invalid';
    check(!screenAllowed(),'An invalid display key was accepted.');
    $_SERVER['REMOTE_ADDR']='192.0.2.11';
    for($i=0;$i<5;$i++)check(!login('missing@example.invalid','incorrect'),'Invalid login accepted.');
    rejects(fn()=>login($input['email'],$input['password']),'Rate limiting failed.');
    check(validPeriod('transfers','2026-W53')&&!validPeriod('transfers','2025-W53'),'ISO week validation failed.');
    check(validPeriod('daily','2024-02-29')&&!validPeriod('daily','2025-02-29'),'Date validation failed.');
    db()->exec("INSERT INTO employees(name,role) VALUES('Original','member')");
    $old=(int)db()->lastInsertId();db()->exec('DELETE FROM employees');
    db()->exec("INSERT INTO employees(name,role) VALUES('Alex Test','member')");
    $id=(int)db()->lastInsertId();check($id>$old,'Employee IDs must not be reused.');
    $file=str_repeat('a',40).'.jpg';$image=imagecreatetruecolor(80,80);
    imagefill($image,0,0,imagecolorallocate($image,232,203,85));imagejpeg($image,storage('media/'.$file));
    db()->prepare('INSERT INTO media(filename,label,kind,created_at) VALUES(?,?,?,?)')->execute([$file,'Test image','employee',gmdate('c')]);
    $photo=(int)db()->lastInsertId();db()->prepare('UPDATE employees SET photo=? WHERE id=?')->execute([$photo,$id]);
    saveContent('spotlight',date('Y-m'),['employee_id'=>$id,'message'=>['en'=>'Well done']]);
    $daily=createBackup('daily');$weekly=createBackup('weekly');
    check(is_file(storage('backups/'.$daily))&&is_file(storage('backups/'.$weekly)),'Backups were not created.');
    $zip=new ZipArchive();check($zip->open(storage('backups/'.$daily))===true,'Backup ZIP cannot be opened.');
    check($zip->locateName('media/'.$file)!==false,'Photo missing from backup.');
    $manifest=json_decode($zip->getFromName('manifest.json'),true);
    check(hash('sha256',$zip->getFromName('pulse.sqlite'))===$manifest['database_sha256'],'Backup checksum mismatch.');
    $zip->close();
    check((int)db()->query('SELECT COUNT(*) FROM snapshots')->fetchColumn()===2,'Daily/weekly snapshots missing.');
    db()->exec("UPDATE employees SET name='Renamed' WHERE id=".$id);
    $snapshot=json_decode(db()->query("SELECT payload FROM snapshots WHERE kind='daily'")->fetchColumn(),true);
    check($snapshot['employees'][0]['name']==='Alex Test','Historical names were changed.');
    setSetting('installed_version','0.0.9');syncVersion();
    check(setting('version_event')['version']===version(),'Installed-version notification missing.');
    $release=validateRelease(['app'=>'pulse-dietikon','version'=>'0.3.0','notes'=>['en'=>'New version']]);
    setSetting('update_state',['release'=>$release]);
    check(publicUpdateState()['available'],'Available-version notification missing.');
    setSetting('update_state',['release'=>validateRelease(['app'=>'pulse-dietikon','version'=>version()])]);
    check(!publicUpdateState()['available'],'Equal version must not be advertised as newer.');
    rejects(fn()=>validateRelease(['app'=>'other','version'=>'1.0.0']),'Wrong app accepted.');
    rejects(fn()=>validateRelease(['app'=>'pulse-dietikon','version'=>'1.0.0','release_url'=>'javascript:alert(1)']),'Unsafe release URL accepted.');
    setSetting('update_state',['checked_epoch'=>time(),'error'=>null]);$state=checkUpdates(true);
    check($state['error']===null,'Automatic check must respect the six-hour interval without network access.');
    check(!publicUpdateState(false)['available'],'TV must not disclose administrator update notices.');
    echo $checks." core checks passed.\nTemporary test data: ".$dir."\n";
}catch(Throwable $ex){fwrite(STDERR,$ex->getMessage()."\n");exit(1);}
