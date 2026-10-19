<?php
declare(strict_types=1);
$dir=sys_get_temp_dir().'/pulse-podium-'.bin2hex(random_bytes(6));
putenv('PULSE_STORAGE='.$dir);
require dirname(__DIR__).'/app/bootstrap.php';boot();startSession();
require PULSE_ROOT.'/app/actions.php';
$checks=0;
function podiumCheck(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
initialize(['token'=>setupToken(),'name'=>'Test admin','email'=>'podium@example.invalid','password'=>'test-podium-phrase-123','store'=>'Test Store','language'=>'de','duration'=>20]);
login('podium@example.invalid','test-podium-phrase-123');
db()->exec("INSERT INTO employees(name,role,active) VALUES('One','member',1),('Two','member',1),('Three','member',1),('Inactive','member',0)");
$month=periodFor('podium');
saveContent('podium',$month,[['employee_id'=>4,'count'=>0.25]]);
saveContent('targets',$month,['service_target'=>123,'service_total'=>45]);
$_POST=['csrf'=>$_SESSION['csrf'],'module'=>'podium','period'=>$month,'counts'=>[1=>'0.6',2=>'0,8',3=>'1.25']];
handleAction('content');$saved=content('podium',$month);
podiumCheck(array_column($saved,'count','employee_id')===[1=>0.6,2=>0.8,3=>1.25,4=>0.25],'Decimals or inactive record lost.');
podiumCheck(screenData()['content']['podium']===$saved,'Screen payload rounded a decimal.');
podiumCheck(content('targets',$month)===['service_target'=>123,'service_total'=>45],'Podium changed manual team target.');
snapshot('daily');$archive=json_decode(db()->query("SELECT payload FROM snapshots WHERE kind='daily'")->fetchColumn(),true);
podiumCheck($archive['content']['podium']===$saved,'Archive rounded a decimal.');
foreach(['0','0.01','0.000001','1.2','123.4567','1000000'] as $value){
    $_POST['counts']=[1=>$value];handleAction('content');
    podiumCheck(content('podium',$month)[0]['count']==(float)$value,'Valid decimal was changed: '.$value);
}
$before=content('podium',$month);
foreach(['-0.1','1000000.1','NaN','INF','1e309','wrong','0,6,7',[]] as $bad){
    $_POST['counts']=[1=>$bad];$rejected=false;
    try{handleAction('content');}catch(DomainException){$rejected=true;}
    podiumCheck($rejected&&content('podium',$month)===$before,'Invalid value changed saved data.');
}
$next=(new DateTimeImmutable($month.'-01'))->modify('+1 month')->format('Y-m');
$_POST['period']=$next;$_POST['counts']=[1=>'0.8'];handleAction('content');
podiumCheck(content('podium',$month)===$before,'Changed a different month.');
podiumCheck(json_decode(db()->query("SELECT payload FROM snapshots WHERE kind='daily'")->fetchColumn(),true)['content']['podium']===$saved,'Changed a historical snapshot.');
echo $checks." podium decimal checks passed.\n";
