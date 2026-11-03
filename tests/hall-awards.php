<?php
declare(strict_types=1);
$dir=sys_get_temp_dir().'/pulse-hall-test-'.bin2hex(random_bytes(6));
putenv('PULSE_STORAGE='.$dir);putenv('PULSE_UPDATER_STATE='.$dir.'/updater');putenv('PULSE_UPDATER_CONFIG='.$dir.'/updater/config.json');
require dirname(__DIR__).'/app/bootstrap.php';boot();startSession();require PULSE_ROOT.'/app/actions.php';require PULSE_ROOT.'/app/views.php';require PULSE_ROOT.'/app/views_admin.php';
$count=0;
function verifyHall(bool $ok,string $message):void{global $count;$count++;if(!$ok)throw new RuntimeException($message);}
function rejectHall(callable $fn):void{try{$fn();}catch(DomainException $e){verifyHall(true,'Rejected invalid input');return;}throw new RuntimeException('Invalid input accepted');}
function postHall(array $p):void{$_POST=['csrf'=>$_SESSION['csrf']]+$p;}
initialize(['token'=>setupToken(),'name'=>'Test Admin','email'=>'hall@example.invalid','password'=>'hall-test-only-phrase','store'=>'Test Store','language'=>'en','duration'=>20]);login('hall@example.invalid','hall-test-only-phrase');
verifyHall(in_array('hall',moduleKeys(),true)&&in_array('hall_story',moduleKeys(),true),'Both modules registered');
verifyHall(periodFor('hall')==='all'&&validPeriod('hall_story','all'),'Hall is not month-bound');
$file=str_repeat('b',40).'.jpg';$image=imagecreatetruecolor(80,120);imagejpeg($image,storage('media/'.$file));
db()->prepare('INSERT INTO media(filename,label,kind,created_at) VALUES(?,?,?,?)')->execute([$file,'Synthetic test','employee',gmdate('c')]);$photo=(int)db()->lastInsertId();
db()->prepare('INSERT INTO employees(name,role,photo) VALUES(?,?,?)')->execute(['Test <One>','member',$photo]);$one=(int)db()->lastInsertId();
db()->exec("INSERT INTO employees(name,role) VALUES('Test Two','member'),('Test Three','supervisor')");$three=(int)db()->lastInsertId();$two=$three-1;
$month=periodFor('spotlight');saveContent('spotlight',$month,['employee_id'=>$one,'message'=>['en'=>'Legacy']]);
$_GET=['module'=>'spotlight','period'=>$month];ob_start();adminContent();$html=ob_get_clean();verifyHall(str_contains($html,'name="employee_id_2"')&&str_contains($html,'value="'.$one.'" selected'),'Legacy award edit + second selector');
postHall(['module'=>'spotlight','period'=>$month,'employee_id'=>$one,'employee_id_2'=>$two,'message_en'=>'Together']);handleAction('content');$awards=content('spotlight',$month);
verifyHall($awards['employee_ids']===[$one,$two]&&$awards['employee_id']===$one,'Dual award and compatibility field');
$_POST['employee_id_2']=$one;rejectHall(fn()=>handleAction('content'));verifyHall(content('spotlight',$month)===$awards,'Duplicate save atomic');
$_POST['employee_id_2']='999999';rejectHall(fn()=>handleAction('content'));
$_POST['period']='2030-01';$_POST['employee_id']='';$_POST['employee_id_2']=$two;handleAction('content');verifyHall(content('spotlight','2030-01')['employee_ids']===[$two]&&content('spotlight',$month)===$awards,'Second-only normalized and months independent');
snapshot('daily');$history=db()->query("SELECT payload FROM snapshots WHERE kind='daily'")->fetchColumn();
postHall(['id'=>$one,'story_en'=>str_repeat('x',501)]);rejectHall(fn()=>handleAction('employee_archive'));verifyHall(count(hallMembers())===0&&count(employees())===3,'Overlong story cannot remove employee');
postHall(['id'=>$one,'story_en'=>'A <script>test</script> dedication.','story_de'=>'Danke.','story_fr'=>'Merci.']);
$_POST['csrf']='wrong';rejectHall(fn()=>handleAction('employee_archive'));verifyHall(count(employees())===3,'CSRF protects archive');$_POST['csrf']=$_SESSION['csrf'];
// Simulate a storage failure after removing the employee: the transaction must put them back.
db()->exec("CREATE TRIGGER fail_hall BEFORE INSERT ON content WHEN NEW.module='hall' BEGIN SELECT RAISE(ABORT,'test failure'); END");
try{handleAction('employee_archive');throw new RuntimeException('Trigger did not fail');}catch(PDOException $e){}
db()->exec('DROP TRIGGER fail_hall');verifyHall(count(hallMembers())===0&&count(employees())===3,'Archive rollback restores employee');
$now=new DateTimeImmutable('2026-10-22 12:30:00',new DateTimeZone('Europe/Zurich'));hallAction('employee_archive',$now);$member=hallMembers()[0];
verifyHall(count(employees())===2&&$member['id']===$one&&$member['photo']===$photo,'Photo and identity move atomically');
verifyHall($member['priority_until']===$now->modify('+7 days')->getTimestamp(),'Seven calendar days across DST');
verifyHall($member['priority_until']-$member['added_at']===7*86400+3600,'Zurich DST offset respected');
verifyHall($member['story']['de']==='Danke.'&&is_file(storage('media/'.$file)),'Translations and photo preserved');
rejectHall(fn()=>handleAction('employee_archive'));verifyHall(count(hallMembers())===1,'Duplicate archive rejected');
verifyHall(db()->query("SELECT payload FROM snapshots WHERE kind='daily'")->fetchColumn()===$history,'Old snapshot immutable');
verifyHall(screenData()['content']['hall'][0]===$member,'TV receives Hall snapshot');
postHall(['id'=>$one,'name'=>'Edited <Name>','story_en'=>'Edited story']);handleAction('hall_save');$edited=hallMembers()[0];
verifyHall($edited['priority_until']===$member['priority_until']&&$edited['added_at']===$member['added_at'],'Edit does not renew priority');
verifyHall($edited['photo']===$photo&&$edited['name']==='Edited <Name>','Edit retains photo');
postHall(['id'=>$two,'story_en'=>'Second newcomer']);handleAction('employee_archive');verifyHall(count(hallMembers())===2&&count(employees())===1,'Several newcomers coexist');
$_GET=['edit'=>$one];ob_start();adminHall();$html=ob_get_clean();verifyHall(str_contains($html,'Edited &lt;Name&gt;')&&!str_contains($html,'Edited <Name>'),'Hall admin output escaped');
$_GET=['remove'=>$three];ob_start();adminEmployees();$html=ob_get_clean();verifyHall(str_contains($html,'employee_archive')&&str_contains($html,'employee_delete'),'Removal has explicit choices');
ob_start();renderDisplay(screenData());$html=ob_get_clean();verifyHall(str_contains($html,'/assets/people.js?')&&!str_contains($html,'Edited <Name>'),'TV safe JSON and selection script');
$backup=createBackup();$zip=new ZipArchive();verifyHall($zip->open(storage('backups/'.$backup))===true,'Backup readable');
verifyHall($zip->getFromName('media/'.$file)===file_get_contents(storage('media/'.$file)),'Backup contains original photo');
$dbCopy=$dir.'/backup-test.sqlite';file_put_contents($dbCopy,$zip->getFromName('pulse.sqlite'));$zip->close();$copy=new PDO('sqlite:'.$dbCopy);
$h=json_decode($copy->query("SELECT payload FROM content WHERE module='hall'")->fetchColumn(),true);verifyHall(count($h)===2,'Backup contains Hall and dedications');
verifyHall(password_verify('hall-test-only-phrase',$copy->query('SELECT password_hash FROM admins')->fetchColumn()),'Backup retains admin account');
postHall(['id'=>$one]);handleAction('hall_delete');verifyHall(count(hallMembers())===1&&is_file(storage('media/'.$file)),'Hall removal keeps archived media');
postHall(['id'=>$three]);handleAction('employee_delete');verifyHall(count(employees())===0&&count(hallMembers())===1,'Direct deletion remains separate');
function carnivalAt(string $date,array $override=[]):string{$now=new DateTimeImmutable($date,new DateTimeZone('Europe/Zurich'));foreach(seasonalState($now,true,$override)['windows'] as $w)if($now->getTimestamp()>=$w['starts_at']&&$now->getTimestamp()<$w['ends_at'])return $w['key'];return '';}
foreach(['2027-02-03 23:59:59'=>'','2027-02-04'=>'carnival','2027-02-09 23:59:59'=>'carnival','2027-02-10'=>'','2028-02-24'=>'carnival','2028-02-29 23:59:59'=>'carnival','2028-03-01'=>''] as $date=>$expected)verifyHall(carnivalAt($date)===$expected,'Carnival boundary '.$date);
$custom=validateCarnivalDates('2027-01-30','2027-01-31');verifyHall(carnivalAt('2027-01-31 23:59:59',$custom)==='carnival'&&carnivalAt('2027-02-01',$custom)==='','Override inclusive end');
verifyHall(carnivalAt('2027-02-04',$custom)===''&&carnivalAt('2028-02-24',$custom)==='carnival','Override replaces one year only');
foreach([['2027-02-30','2027-03-01'],['2027-02-04',''],['2027-02-05','2027-02-04'],['2027-12-31','2028-01-01'],['2027-01-01','2027-04-01']] as [$a,$b])rejectHall(fn()=>validateCarnivalDates($a,$b));
postHall(['store'=>'Test Store','language'=>'en','duration'=>20,'pages'=>['hall'=>'on'],'carnival_start'=>'2027-01-30','carnival_end'=>'2027-01-31']);handleAction('settings');verifyHall(setting('carnival_dates')===$custom,'Custom dates saved');
unset($_POST['carnival_start'],$_POST['carnival_end']);handleAction('settings');verifyHall(setting('carnival_dates')===$custom,'Old settings form retains custom dates');
$_POST['carnival_start']='';$_POST['carnival_end']='';handleAction('settings');verifyHall(setting('carnival_dates')===[],'Empty dates restore automatic calendar');
foreach(['de','en','fr'] as $lang)verifyHall(isset(dictionary($lang)['hall'],dictionary($lang)['award_title_two'],dictionary($lang)['carnival_hint']),'Locale '.$lang);
echo $count." Hall of Fame, dual awards and Carnival checks passed.\n";
