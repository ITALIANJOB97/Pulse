<?php
declare(strict_types=1);
$dir=sys_get_temp_dir().'/pulse-seasonal-'.bin2hex(random_bytes(6));
putenv('PULSE_STORAGE='.$dir);putenv('PULSE_UPDATER_STATE='.$dir.'/updater');putenv('PULSE_UPDATER_CONFIG='.$dir.'/updater/config.json');
require dirname(__DIR__).'/app/bootstrap.php';boot();startSession();require PULSE_ROOT.'/app/actions.php';require PULSE_ROOT.'/app/views.php';require PULSE_ROOT.'/app/views_admin.php';
$count=0;function checkSeason(bool $ok,string $message):void{global $count;$count++;if(!$ok)throw new RuntimeException($message);}
function activeAt(string $date):string{$now=new DateTimeImmutable($date,new DateTimeZone('Europe/Zurich'));foreach(seasonalState($now)['windows'] as $w)if($now->getTimestamp()>=$w['starts_at']&&$now->getTimestamp()<$w['ends_at'])return $w['key'];return '';}
foreach(['2026-10-19 23:59:59'=>'','2026-10-20 00:00:00'=>'halloween','2026-11-01 23:59:59'=>'halloween','2026-11-02 00:00:00'=>'','2026-11-30 23:59:59'=>'','2026-12-01'=>'christmas','2027-01-06 23:59:59'=>'christmas','2027-01-07'=>'','2026-03-28 23:59:59'=>'','2026-03-29'=>'easter','2026-04-06 23:59:59'=>'easter','2026-04-07'=>'','2027-03-21'=>'easter','2027-03-29'=>'easter','2027-03-30'=>''] as $when=>$expected)checkSeason(activeAt($when)===$expected,'Holiday boundary: '.$when);
foreach([2026=>'2026-04-05',2027=>'2027-03-28',2028=>'2028-04-16',2029=>'2029-04-01',2038=>'2038-04-25'] as $year=>$date)checkSeason(easterSunday($year)->format('Y-m-d')===$date,'Easter computation');
initialize(['token'=>setupToken(),'name'=>'Seasonal Admin','email'=>'seasonal@example.invalid','password'=>'seasonal-password-123','store'=>'Dietikon','language'=>'de','duration'=>20]);login('seasonal@example.invalid','seasonal-password-123');
$pages=['daily'=>false,'team'=>true];setSetting('pages',$pages);
checkSeason(in_array('motto',moduleKeys(),true),'Motto module registered');
checkSeason(periodFor('motto',new DateTimeImmutable('2027-01-01'))==='2026-W53','ISO year boundary');
checkSeason(validPeriod('motto','2026-W53')&&!validPeriod('motto','2027-W53')&&!validPeriod('motto','2026-10'),'Week validation');
$_POST=['csrf'=>$_SESSION['csrf'],'module'=>'motto','period'=>'2026-W44','motto_de'=>'Gemeinsam jeden Tag besser.','motto_en'=>'Better together, every day.','motto_fr'=>'Ensemble, meilleurs chaque jour.','motto_author'=>'Team Dietikon'];handleAction('content');
$first=content('motto','2026-W44');
checkSeason($first['text']['de']==='Gemeinsam jeden Tag besser.'&&$first['author']==='Team Dietikon','Translations and author saved');
$_POST['period']='2026-W45';$_POST['motto_de']='Nächste Woche.';handleAction('content');
checkSeason(content('motto','2026-W44')===$first,'Future week cannot overwrite current');
checkSeason(screenData(new DateTimeImmutable('2026-10-29'))['content']['motto']===$first,'Correct week displayed');
checkSeason(screenData(new DateTimeImmutable('2026-11-09'))['content']['motto']===[],'No carryover to empty weeks');
checkSeason(screenData(new DateTimeImmutable('2026-10-29'))['pages']===$pages,'Existing page preferences unchanged');
$_POST['motto_de']=str_repeat('ä',281);try{handleAction('content');throw new RuntimeException('Long motto accepted');}catch(DomainException $expected){$count++;}
checkSeason(content('motto','2026-W45')['text']['de']==='Nächste Woche.','Invalid save leaves prior motto intact');
$_POST['csrf']='invalid';$_POST['motto_de']='wrong';try{handleAction('content');throw new RuntimeException('Missing CSRF');}catch(DomainException $expected){$count++;}
setSetting('seasonal_decorations',false);checkSeason(screenData()['seasonal']['enabled']===false,'Decoration switch');setSetting('seasonal_decorations',true);
saveContent('motto','2026-W44',['text'=>['de'=>'<script>alert(1)</script>'],'author'=>'<img onerror=alert(1)>']);
$_GET=['module'=>'motto','period'=>'2026-W44'];ob_start();adminContent();$html=ob_get_clean();
checkSeason(!str_contains($html,'<script>alert(1)</script>')&&str_contains($html,'&lt;script&gt;'),'Admin text escaped');checkSeason(str_contains($html,'type="week"')&&str_contains($html,'maxlength="280"'),'Weekly editor limits');
ob_start();renderDisplay(screenData(new DateTimeImmutable('2026-10-29')));$html=ob_get_clean();checkSeason(str_contains($html,'/assets/seasonal.js?')&&!str_contains($html,'<script>alert(1)</script>'),'Safe TV payload');
ob_start();renderDisplay(screenData(),true);$html=ob_get_clean();checkSeason(str_contains($html,'"seasonal":{"enabled":false'),'Archive decorations disabled');
$_POST=['csrf'=>$_SESSION['csrf'],'store'=>'Dietikon','language'=>'de','duration'=>20,'pages'=>['motto'=>'on'],'seasonal_decorations'=>'on'];handleAction('settings');checkSeason(setting('seasonal_decorations')===true,'Settings switch saved');
$_POST['seasonal_decorations']=null;unset($_POST['seasonal_decorations']);handleAction('settings');checkSeason(setting('seasonal_decorations')===false,'Settings switch disabled');
checkSeason(password_verify('seasonal-password-123',db()->query('SELECT password_hash FROM admins')->fetchColumn()),'Account preserved');
foreach(['en','de','fr'] as $lang)checkSeason(isset(dictionary($lang)['motto'],dictionary($lang)['seasonal_hint']),'Locale complete');
echo $count." seasonal and motto checks passed.\n";
