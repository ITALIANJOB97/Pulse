<?php
declare(strict_types=1);
$dir=sys_get_temp_dir().'/pulse-targets-'.bin2hex(random_bytes(6));
putenv('PULSE_STORAGE='.$dir);
require dirname(__DIR__).'/app/bootstrap.php';boot();startSession();
require PULSE_ROOT.'/app/actions.php';
require PULSE_ROOT.'/app/views.php';require PULSE_ROOT.'/app/views_admin.php';
$checks=0;
function targetCheck(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
initialize(['token'=>setupToken(),'name'=>'Test admin','email'=>'targets@example.invalid','password'=>'test-targets-phrase-123','store'=>'Test Store','language'=>'en','duration'=>20]);
login('targets@example.invalid','test-targets-phrase-123');
$month=periodFor('targets');
$_POST=['csrf'=>$_SESSION['csrf'],'module'=>'targets','period'=>$month];
foreach(['0.6'=>0.6,'0,6'=>0.6,'0.01'=>0.01,'0.000001'=>0.000001,'1.25'=>1.25,' 0,8 '=>0.8,'0'=>0,'100000000'=>100000000,''=>null,'  '=>null] as $value=>$expected){
    $_POST['service_target']=(string)$value;$_POST['service_total']=(string)$value;handleAction('content');
    $saved=content('targets',$month);
    foreach(['service_target','service_total'] as $key)targetCheck($expected===null?$saved[$key]===null:$saved[$key]===$expected||$saved[$key]==(float)$expected,'Decimal not retained: '.$key.' '.$value);
}
$_POST['service_target']='0,8';$_POST['service_total']='0.6';handleAction('content');
$before=content('targets',$month);
targetCheck(screenData()['content']['targets']===$before,'TV payload changed values.');
snapshot('daily');$archive=json_decode(db()->query("SELECT payload FROM snapshots WHERE kind='daily'")->fetchColumn(),true);
targetCheck($archive['content']['targets']===$before,'Snapshot changed values.');
foreach(['service_target','service_total'] as $key){
    foreach(['-0.1','100000000.1','NaN','INF','1e309','wrong','0,6,7','1.2.3',[]] as $bad){
        $_POST['service_target']='0.8';$_POST['service_total']='0.6';$_POST[$key]=$bad;$rejected=false;
        try{handleAction('content');}catch(DomainException){$rejected=true;}
        targetCheck($rejected&&content('targets',$month)===$before,'Invalid value changed data: '.$key);
    }
}
$next=(new DateTimeImmutable($month.'-01'))->modify('+1 month')->format('Y-m');
$_POST['period']=$next;$_POST['service_target']='1.25';$_POST['service_total']='0,25';handleAction('content');
targetCheck(content('targets',$month)===$before,'Changed another month.');
targetCheck(json_decode(db()->query("SELECT payload FROM snapshots WHERE kind='daily'")->fetchColumn(),true)['content']['targets']===$before,'Changed historical snapshot.');
$_GET=['module'=>'targets','period'=>$month];ob_start();adminContent();$html=ob_get_clean();
foreach(['service_target'=>'0.8','service_total'=>'0.6'] as $key=>$value){
    targetCheck(preg_match('/<input[^>]*name="'.$key.'"[^>]*>/',$html,$match)===1,'Missing editor field.');
    targetCheck(str_contains($match[0],'inputmode="decimal"')&&str_contains($match[0],'value="'.$value.'"'),'Editor cannot accept/reload decimal.');
}
echo $checks." Team Targets decimal checks passed.\n";
