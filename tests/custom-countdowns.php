<?php
declare(strict_types=1);
$dir=sys_get_temp_dir().'/pulse-custom-countdown-'.bin2hex(random_bytes(6));
putenv('PULSE_STORAGE='.$dir);putenv('PULSE_UPDATER_STATE='.$dir.'/updater');putenv('PULSE_UPDATER_CONFIG='.$dir.'/updater/config.json');
require dirname(__DIR__).'/app/bootstrap.php';boot();startSession();require PULSE_ROOT.'/app/actions.php';require PULSE_ROOT.'/app/views.php';require PULSE_ROOT.'/app/views_admin.php';
$count=0;$now=new DateTimeImmutable('2030-01-01 12:00:00',new DateTimeZone('Europe/Zurich'));
function verifyCustom(bool $ok,string $message):void{global $count;$count++;if(!$ok)throw new RuntimeException($message);}
function rejectCustom(callable $fn):void{try{$fn();}catch(DomainException $e){verifyCustom(true,'Invalid input rejected');return;}throw new RuntimeException('Invalid input accepted');}
function postCustom(array $values=[]):void{$_POST=['csrf'=>$_SESSION['csrf'],'id'=>'','title'=>'Team celebration','caption'=>'Together, we make it happen.','date'=>'2030-12-20','time'=>'18:30','enabled'=>'1']+$values;foreach($values as $k=>$v)$_POST[$k]=$v;$_FILES=[];}
function imageCustom(string $color='blue'):void{
    $file=storage('fixture-'.$color.'.png');$image=imagecreatetruecolor(160,90);imagefill($image,0,0,imagecolorallocate($image,$color==='blue'?0:180,85,157));imagepng($image,$file);
    $_FILES=['image'=>['error'=>UPLOAD_ERR_OK,'size'=>filesize($file),'tmp_name'=>$file,'name'=>'test.png']];
}
initialize(['token'=>setupToken(),'name'=>'Test Admin','email'=>'custom@example.invalid','password'=>'custom-test-only-phrase','store'=>'Custom test','language'=>'en','duration'=>20]);login('custom@example.invalid','custom-test-only-phrase');
db()->exec("INSERT INTO employees(name,role) VALUES('Preserved Employee','member')");
setSetting('countdowns',['gta'=>false,'doomsday'=>true]);
postCustom();rejectCustom(fn()=>customCountdownAction('countdown_save',$now));verifyCustom(customCountdowns()===[],'New countdown requires image');
foreach([['title'=>''],['title'=>str_repeat('x',101)],['caption'=>str_repeat('x',281)],['date'=>'2030-02-30'],['date'=>'2029-12-31'],['time'=>'24:10'],['date'=>'2100-01-01'],['date'=>'2030-03-31','time'=>'02:30']] as $invalid){postCustom($invalid);imageCustom();rejectCustom(fn()=>customCountdownAction('countdown_save',$now));}
verifyCustom(db()->query('SELECT COUNT(*) FROM media')->fetchColumn()==0,'Validation occurs before image writes');
postCustom();imageCustom();$_FILES['image']['size']=9*1024*1024;rejectCustom(fn()=>customCountdownAction('countdown_save',$now));
postCustom();imageCustom();customCountdownAction('countdown_save',$now);$one=customCountdowns()[0];$id=$one['id'];$photo=$one['photo'];$original=mediaFile($photo);
verifyCustom((bool)preg_match('/^custom_[a-f0-9]{16}$/D',$id),'Independent generated identity');
verifyCustom($one['ends_at']===(new DateTimeImmutable('2030-12-20T17:30:00Z'))->getTimestamp(),'Swiss winter deadline converted to UTC');
verifyCustom(setting('countdowns')[$id]===true&&setting('countdowns')['gta']===false,'Default visible without changing themed preferences');
verifyCustom(is_file($original)&&getimagesize($original)['mime']==='image/jpeg','Uploaded image normalized');
verifyCustom(db()->query("SELECT kind FROM media WHERE id=$photo")->fetchColumn()==='countdown','Images kept out of employee and gallery lists');
$active=activeCountdowns($now);verifyCustom(count($active)===1&&$active[0]['images']===['/media?id='.$photo]&&$active[0]['kind']==='custom','Screen contract includes local image, content and theme');
$before=new DateTimeImmutable('@'.($one['ends_at']-1));$at=new DateTimeImmutable('@'.$one['ends_at']);
verifyCustom(count(activeCountdowns($before))===1&&activeCountdowns($at)===[],'Exact custom expiry boundary');
verifyCustom(!str_contains(countdownSettingsHtml($at),$id),'Expired visibility control removed');
verifyCustom(countdownPreferences([], $now, ['gta'])[$id]===true,'Stale Settings form preserves unseen countdown');
postCustom(['id'=>$id,'enabled'=>'0']);customCountdownAction('countdown_toggle',$now);verifyCustom(activeCountdowns($now)[0]['enabled']===false,'Hide countdown');
postCustom(['id'=>$id,'enabled'=>'1']);customCountdownAction('countdown_toggle',$now);verifyCustom(activeCountdowns($now)[0]['enabled']===true,'Show countdown');
rejectCustom(fn()=>customCountdownAction('countdown_toggle',$at));verifyCustom(customCountdowns()[0]===$one,'Stale toggle cannot reactivate expired record');
postCustom(['id'=>$id,'title'=>'Updated <script>alert(1)</script>','caption'=>'Caption <img src=x onerror=alert(1)>','time'=>'']);customCountdownAction('countdown_save',$now);
$edited=customCountdowns()[0];verifyCustom($edited['photo']===$photo&&is_file($original),'Editing without upload keeps image');
verifyCustom($edited['ends_at']===(new DateTimeImmutable('2030-12-20 00:00',new DateTimeZone('Europe/Zurich')))->getTimestamp(),'Empty time means midnight');
$_GET=['edit'=>$id];ob_start();adminCountdowns($now);$html=ob_get_clean();verifyCustom(str_contains($html,'&lt;script&gt;')&&!str_contains($html,'<script>alert(1)</script>'),'Admin content escaped');
ob_start();renderDisplay(screenData($now));$html=ob_get_clean();verifyCustom(str_contains($html,'\\u003Cscript\\u003E')&&!str_contains($html,'<script>alert(1)</script>'),'TV JSON escaped');
postCustom(['id'=>$id]);$_POST['csrf']='invalid';rejectCustom(fn()=>handleAction('countdown_delete'));verifyCustom(count(customCountdowns())===1,'CSRF failure preserves record');
postCustom(['id'=>'custom_0000000000000000']);rejectCustom(fn()=>customCountdownAction('countdown_save',$now));
postCustom(['id'=>'gta']);rejectCustom(fn()=>customCountdownAction('countdown_delete',$now));
postCustom(['id'=>$id,'date'=>'not-a-date']);rejectCustom(fn()=>handleAction('countdown_save'));verifyCustom(($_SESSION['countdown_form']['id']??null)===$id,'Validation failure keeps typed fields');unset($_SESSION['countdown_form']);
// Failure after upload rolls back both record and media metadata, and removes only the new file.
$filesBefore=glob(storage('media/*'));$stateBefore=customCountdowns();$prefsBefore=setting('countdowns');
db()->exec("CREATE TRIGGER fail_countdown_write BEFORE UPDATE ON content WHEN NEW.module='countdowns' BEGIN SELECT RAISE(ABORT,'test failure'); END");
postCustom(['id'=>$id]);imageCustom('red');try{customCountdownAction('countdown_save',$now);throw new RuntimeException('Failure trigger ignored');}catch(PDOException $expected){$count++;}
db()->exec('DROP TRIGGER fail_countdown_write');
verifyCustom(customCountdowns()===$stateBefore&&setting('countdowns')===$prefsBefore&&glob(storage('media/*'))===$filesBefore,'Failed write preserves content, preferences and original image');
db()->exec("CREATE TRIGGER fail_media_write BEFORE INSERT ON media BEGIN SELECT RAISE(ABORT,'test failure'); END");
postCustom(['id'=>$id]);imageCustom('red');try{customCountdownAction('countdown_save',$now);throw new RuntimeException('Media failure ignored');}catch(PDOException $expected){$count++;}
db()->exec('DROP TRIGGER fail_media_write');verifyCustom(glob(storage('media/*'))===$filesBefore,'Failed media insert cleans generated file');
postCustom(['id'=>$id]);imageCustom('red');customCountdownAction('countdown_save',$now);$final=customCountdowns()[0];
verifyCustom($final['photo']!==$photo&&is_file($original),'Replacing image retains historical file');
verifyCustom(db()->query("SELECT kind FROM media WHERE id=$photo")->fetchColumn()==='archived','Previous photo archived');
// Full backup and restore to a different disposable storage, including custom metadata and images.
$backup=storage('backups/'.createBackup());$target=sys_get_temp_dir().'/pulse-custom-restore-'.bin2hex(random_bytes(5));
$process=proc_open([PHP_BINARY,PULSE_ROOT.'/bin/restore.php',$backup,'--confirm'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,array_replace(getenv(),['PULSE_STORAGE'=>$target]));
fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);verifyCustom(proc_close($process)===0,'Restore: '.$err);
$restored=new PDO('sqlite:'.$target.'/pulse.sqlite');$payload=$restored->query("SELECT payload FROM content WHERE module='countdowns' AND period='all'")->fetchColumn();
verifyCustom(json_decode($payload,true)===customCountdowns(),'Backup restores all countdown fields');
foreach($restored->query('SELECT filename FROM media') as $row)verifyCustom(hash_file('sha256',$target.'/media/'.$row['filename'])===hash_file('sha256',storage('media/'.$row['filename'])),'Backup restores image bytes');
verifyCustom($restored->query('SELECT name FROM employees')->fetchColumn()==='Preserved Employee','Existing team survives restore');
verifyCustom(password_verify('custom-test-only-phrase',$restored->query('SELECT password_hash FROM admins')->fetchColumn()),'Existing admin survives restore');
postCustom(['id'=>$id]);customCountdownAction('countdown_delete',$now);verifyCustom(customCountdowns()===[]&&!isset(setting('countdowns')[$id]),'Deletion removes record and preference');
verifyCustom(is_file($original)&&is_file(mediaFile($final['photo'])),'Deletion retains images for backup/history');
rejectCustom(fn()=>customCountdownAction('countdown_save',$now));
// Count bounds are checked inside the same write transaction.
$full=[];for($i=0;$i<20;$i++)$full[]=array_replace($final,['id'=>'custom_'.str_pad(dechex($i),16,'0',STR_PAD_LEFT)]);saveContent('countdowns','all',$full);
postCustom();imageCustom();rejectCustom(fn()=>customCountdownAction('countdown_save',$now));verifyCustom(count(customCountdowns())===20,'Capacity limit preserves stored records');
verifyCustom(db()->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn()==1,'No schema migration');
foreach(['en','de','fr'] as $lang)foreach(['new_countdown','countdown_invalid_date','countdown_image_hint','countdown_expired'] as $key)verifyCustom(isset(dictionary($lang)[$key]),'Locale coverage');
echo $count." custom countdown and backup/restore checks passed.\n";
