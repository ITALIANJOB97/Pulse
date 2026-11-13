<?php
declare(strict_types=1);
$dir=sys_get_temp_dir().'/pulse-countdowns-'.bin2hex(random_bytes(6));
putenv('PULSE_STORAGE='.$dir);putenv('PULSE_UPDATER_STATE='.$dir.'/updater');putenv('PULSE_UPDATER_CONFIG='.$dir.'/updater/config.json');
require dirname(__DIR__).'/app/bootstrap.php';boot();startSession();require PULSE_ROOT.'/app/actions.php';require PULSE_ROOT.'/app/views.php';require PULSE_ROOT.'/app/views_admin.php';
$count=0;
function verifyCountdown(bool $ok,string $message):void{global $count;$count++;if(!$ok)throw new RuntimeException($message);}
function atCountdown(string $when):DateTimeImmutable{return new DateTimeImmutable($when,new DateTimeZone('Europe/Zurich'));}
$before=atCountdown('2026-11-13 12:00:00');
initialize(['token'=>setupToken(),'name'=>'Test Admin','email'=>'countdown@example.invalid','password'=>'countdown-test-only-phrase','store'=>'Countdown test','language'=>'de','duration'=>20]);login('countdown@example.invalid','countdown-test-only-phrase');
foreach(['2026-11-18 23:59:59'=>['gta','doomsday'],'2026-11-19 00:00:00'=>['doomsday'],'2026-12-15 23:59:59'=>['doomsday'],'2026-12-16 00:00:00'=>[],'2027-11-13'=>[]] as $when=>$ids){
    verifyCountdown(array_column(activeCountdowns(atCountdown($when)),'id')===$ids,'Expiry boundary '.$when);
    $html=countdownSettingsHtml(atCountdown($when));
    foreach(['gta','doomsday'] as $id)verifyCountdown(str_contains($html,'name="countdowns['.$id.']"')===in_array($id,$ids,true),'Settings expiry '.$id.' '.$when);
}
verifyCountdown(countdownCatalog()['gta']['ends_at']===(new DateTimeImmutable('2026-11-18T23:00:00Z'))->getTimestamp(),'Zurich midnight uses correct UTC offset');
verifyCountdown(countdownPreferences(['gta'=>'1','unknown'=>'1'],$before)===['gta'=>true,'doomsday'=>false],'Independent switches and unknown IDs');
verifyCountdown(countdownPreferences(['gta'=>'1','doomsday'=>'1'],atCountdown('2026-12-16'))===[],'Stale forms cannot restore expired events');
verifyCountdown(countdownPreferences('malformed',$before)===['gta'=>false,'doomsday'=>false],'Invalid payload safely ignored');
setSetting('countdowns',['gta'=>false,'doomsday'=>true]);$events=screenData($before)['countdowns'];
verifyCountdown($events[0]['enabled']===false&&$events[1]['enabled']===true,'API exposes independent preferences');
verifyCountdown(str_contains(countdownSettingsHtml($before),'value="1"><span>Grand Theft Auto VI'),'Unchecked setting rendered');
$post=['csrf'=>$_SESSION['csrf'],'store'=>'Countdown test','language'=>'de','duration'=>20,'pages'=>['daily'=>'on']];$_POST=$post;handleAction('settings');
verifyCountdown(setting('countdowns')===['gta'=>false,'doomsday'=>true],'Older open form preserves countdown preferences');
$_POST=$post+['countdowns_present'=>'1','countdowns'=>['doomsday'=>'1']];handleAction('settings');
verifyCountdown(setting('countdowns')===countdownPreferences(['doomsday'=>'1']),'Settings route saves preferences');
$saved=setting('countdowns');$_POST['csrf']='invalid';try{handleAction('settings');throw new RuntimeException('CSRF accepted');}catch(DomainException $expected){$count++;}
verifyCountdown(setting('countdowns')===$saved,'Rejected form does not change preferences');
ob_start();renderDisplay(screenData($before),true);$html=ob_get_clean();verifyCountdown(str_contains($html,'"countdowns":[]'),'Historical snapshots do not revive countdowns');
verifyCountdown(!in_array('countdown_gta',moduleKeys(),true),'Temporary pages do not become permanent modules');
foreach(countdownCatalog() as $event){
    verifyCountdown(count($event['images'])===($event['id']==='gta'?23:6),'All supplied backgrounds included');
    foreach($event['images'] as $url){
        verifyCountdown((bool)preg_match('~^/assets/countdowns/(gta|doomsday)-\d{2}\.jpg$~D',$url),'Local image URL');
        $info=getimagesize(PULSE_ROOT.'/public'.$url);verifyCountdown($info!==false&&$info[0]<=1920&&$info[1]<=1080&&$info['mime']==='image/jpeg','Optimized JPEG is decodable');
    }
}
foreach(['en','de','fr'] as $lang)foreach(['countdown_gta','countdown_doomsday','temporary_countdowns','countdown_hint','countdown_days','countdown_hours','countdown_minutes','countdown_seconds'] as $label)verifyCountdown(isset(dictionary($lang)[$label]),'Translated label '.$lang.'/'.$label);
verifyCountdown(password_verify('countdown-test-only-phrase',db()->query('SELECT password_hash FROM admins')->fetchColumn()),'Existing account preserved');
echo $count." countdown checks passed.\n";
