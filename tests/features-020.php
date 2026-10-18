<?php
declare(strict_types=1);
$dir=sys_get_temp_dir().'/pulse-qa-features-'.bin2hex(random_bytes(6));
putenv('PULSE_STORAGE='.$dir);
putenv('PULSE_UPDATER_STATE='.$dir.'/updater');
putenv('PULSE_UPDATER_CONFIG='.$dir.'/updater/config.json');
require dirname(__DIR__).'/app/bootstrap.php';boot();startSession();
require PULSE_ROOT.'/app/actions.php';
function ensure020(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
initialize(['token'=>setupToken(),'name'=>'Existing admin','email'=>'feature@example.invalid','password'=>'existing-password-123','store'=>'Existing Store','language'=>'de','duration'=>20]);
login('feature@example.invalid','existing-password-123');
$week=periodFor('transfers');$month=periodFor('targets');
saveContent('transfers',$week,['days'=>[1,2,3,4,5,6,77],'pending'=>8]);
$_POST=['csrf'=>$_SESSION['csrf'],'store'=>'Existing Store','language'=>'de','duration'=>20,'effects'=>'on','update_auto'=>'on','update_hour'=>'3','pages'=>array_fill_keys(moduleKeys(),'on')];
handleAction('settings');ensure020(setting('show_sunday')===false,'Sunday checkbox not saved.');
$_POST=['csrf'=>$_SESSION['csrf'],'module'=>'transfers','period'=>$week,'pending'=>9,'day_0'=>12,'day_1'=>2,'day_2'=>3,'day_3'=>4,'day_4'=>5,'day_5'=>6,'day_6'=>999];
handleAction('content');ensure020(content('transfers',$week)['days'][6]===77,'Hidden Sunday was overwritten.');
snapshot('daily');$archive=json_decode(db()->query("SELECT payload FROM snapshots WHERE kind='daily'")->fetchColumn(),true);
setSetting('show_sunday',true);
ensure020($archive['show_sunday']===false&&$archive['content']['transfers']['days'][6]===77,'Historical Sunday state lost.');
db()->exec("INSERT INTO employees(name,role,active) VALUES('One','manager',1),('Two','member',0)");
saveContent('podium',$month,[['employee_id'=>1,'count'=>250],['employee_id'=>2,'count'=>175]]);
$_POST=['csrf'=>$_SESSION['csrf'],'module'=>'podium','period'=>$month,'counts'=>[1=>250]];handleAction('content');
// A saved target from 0.2.0 must remain, without inventing a current result.
saveContent('targets',$month,['service_target'=>500]);
$state=screenData();ensure020(($state['content']['targets']['service_total']??null)===null,'Legacy current result must stay unknown.');
ensure020($state['content']['targets']['service_target']==500,'Existing team target changed.');
$_POST=['csrf'=>$_SESSION['csrf'],'module'=>'targets','period'=>$month,'service_target'=>'500','service_total'=>'320'];handleAction('content');
$state=screenData();ensure020($state['content']['targets']['service_total']==320,'Manual team result was not saved.');
ensure020($state['content']['targets']['service_target']==500,'Team target missing.');
$_POST=['csrf'=>$_SESSION['csrf'],'module'=>'podium','period'=>$month,'counts'=>[1=>999]];handleAction('content');
ensure020(screenData()['content']['targets']['service_total']==320,'Podium overwrote manual team result.');
snapshot('daily');$manualArchive=json_decode(db()->query("SELECT payload FROM snapshots WHERE kind='daily'")->fetchColumn(),true);
ensure020($manualArchive['content']['targets']['service_total']==320,'Snapshot lost manual team result.');
foreach([''=>null,'0'=>0] as $value=>$expected){
    $_POST=['csrf'=>$_SESSION['csrf'],'module'=>'targets','period'=>$month,'service_target'=>'500','service_total'=>(string)$value];handleAction('content');
    $actual=screenData()['content']['targets']['service_total'];
    ensure020($expected===null?$actual===null:$actual===0,'Blank and zero results must remain distinct.');
}
$_POST=['csrf'=>$_SESSION['csrf'],'module'=>'targets','period'=>$month,'service_target'=>'500','service_total'=>'-1'];
try{handleAction('content');throw new RuntimeException('Negative manual result was accepted.');}catch(DomainException $expected){}
ensure020(screenData()['content']['targets']['service_total']===0,'Rejected value changed saved data.');
$next=(new DateTimeImmutable($month.'-01'))->modify('+1 month')->format('Y-m');
$_POST=['csrf'=>$_SESSION['csrf'],'module'=>'targets','period'=>$next,'service_target'=>'800','service_total'=>'600'];handleAction('content');
ensure020(content('targets',$next)['service_total']==600&&content('targets',$month)['service_total']===0,'Monthly results are not independent.');
$savedArchive=json_decode(db()->query("SELECT payload FROM snapshots WHERE kind='daily'")->fetchColumn(),true);
ensure020($savedArchive['content']['targets']['service_total']==320,'Later edits changed historical data.');
ensure020(applicationHealth()['ok']===true,'Application health failed.');
mkdir($dir.'/updater');file_put_contents($dir.'/updater/config.json','{}');
file_put_contents($dir.'/updater/status.json',jsonValue(['phase'=>'scheduled','release'=>['version'=>'0.3.0','notes'=>['de'=>'Neu','en'=>'New','fr'=>'Nouveau']]]));
ensure020(publicUpdateState()['available']===true,'Managed available notice missing.');
$check=checkUpdates();ensure020($check['queued']===true&&strlen(setting('update_requested'))===24,'Check request was not queued.');
$_POST=['csrf'=>$_SESSION['csrf']];handleAction('postpone_update');ensure020(setting('update_postpone_until')>=time()+86399,'Postpone not persisted.');
handleAction('retry_update');ensure020(strlen(setting('update_retry'))===24,'Retry not persisted.');
ensure020(password_verify('existing-password-123',db()->query('SELECT password_hash FROM admins')->fetchColumn()),'Existing account changed.');
echo "Feature checks passed: Sunday preservation/archive, manual team targets (independence, months, blank/zero, validation, history), health, managed notifications, check/postpone/retry, account preservation.\n";
