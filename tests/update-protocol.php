<?php
declare(strict_types=1);
$dir=sys_get_temp_dir().'/pulse-protocol-php-'.bin2hex(random_bytes(5));
putenv('PULSE_STORAGE='.$dir);putenv('PULSE_UPDATER_CONFIG='.$dir.'/updater/config.json');putenv('PULSE_UPDATER_STATE='.$dir.'/updater');
require dirname(__DIR__).'/app/bootstrap.php';boot();startSession();
require PULSE_ROOT.'/app/actions.php';require PULSE_ROOT.'/app/views.php';require PULSE_ROOT.'/app/views_admin.php';
$checks=0;
function checkProtocol(bool $ok,string $message):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
function rejectProtocol(callable $action,string $message):void{try{$action();}catch(Throwable){checkProtocol(true,$message);return;}checkProtocol(false,$message);}
function protocolFixture(array $extra=[]):array{return array_replace(['schemaVersion'=>1,'program'=>'Pulse','version'=>'0.3.0','downloadUrl'=>updateSource()['baseUrl'].'pulse-0.3.0.zip','sha256'=>str_repeat('a',64)],$extra);}
function htmlProtocol():string{ob_start();adminUpdatePanel();return ob_get_clean();}
initialize(['token'=>setupToken(),'name'=>'Update Test','email'=>'updates@example.invalid','password'=>'temporary-update-password','store'=>'Test','language'=>'en','duration'=>20]);
login('updates@example.invalid','temporary-update-password');
foreach(['1.10.0','2.0.0'] as $v)checkProtocol(updateVersionValid($v),'Valid version rejected');
checkProtocol(version_compare('1.10.0','1.9.0','>')&&version_compare('2.0.0','1.99.0','>'),'Semantic ordering');
foreach(['1.2','01.2.3','1.2.3-rc','1.0.0+meta',true,null,'1.2.3'."\n"] as $v)checkProtocol(!updateVersionValid($v),'Invalid version accepted');
foreach(['schemaVersion','program','version','downloadUrl','sha256'] as $key){$data=protocolFixture();unset($data[$key]);rejectProtocol(fn()=>validateUpdateManifest($data),'Missing '.$key);}
foreach([['program'=>'pulse'],['schemaVersion'=>true],['mandatory'=>1],['minimumSupportedVersion'=>'9.0.0'],['size'=>true],['size'=>0],['sha256'=>'x'],['releaseNotes'=>['fixes'=>'not-list']]] as $changes)rejectProtocol(fn()=>validateUpdateManifest(protocolFixture($changes)),'Invalid field');
foreach(['http://www.luigipranzo.com/myfile/Pulse/a.zip','https://evil.test/a.zip',updateSource()['baseUrl'].'../a.zip',updateSource()['baseUrl'].'a.zip?x=1','https://user@www.luigipranzo.com/myfile/Pulse/a.zip'] as $url)rejectProtocol(fn()=>validateUpdateManifest(protocolFixture(['downloadUrl'=>$url])),'Unsafe URL');
checkProtocol(validateUpdateManifest(protocolFixture(['future'=>['x'=>1]]))['version']==='0.3.0','Future fields');
$pair=sodium_crypto_sign_keypair();$secret=sodium_crypto_sign_secretkey($pair);$key=base64_encode(sodium_crypto_sign_publickey($pair));
$verify=static fn(string $body,string $sig)=>verifyUpdateSignature($body,$sig,$key);
$body=jsonValue(protocolFixture(['publishedAt'=>'2026-10-01T10:00:00Z','size'=>100,'releaseNotes'=>['features'=>['Feature'],'improvements'=>['Improvement'],'fixes'=>['<script>alert(1)</script>']]]));
$sig=base64_encode(sodium_crypto_sign_detached($body,$secret));
$verify($body,$sig);checkProtocol(true,'Signature');
rejectProtocol(fn()=>$verify($body.' ',$sig),'Tampered body');rejectProtocol(fn()=>$verify($body,'bad'),'Malformed signature');
$calls=[];
$fetch=static function(string $url,?string $etag,int $limit)use(&$calls,$body,$sig):array{$calls[]=[$url,$etag,$limit];return ['not_modified'=>false,'etag'=>'"first"','body'=>str_ends_with($url,'.sig')?$sig:$body];};
$state=notificationUpdateCheck(false,$fetch,$verify);checkProtocol($state['error']===null&&$state['phase']==='available','Valid check');
checkProtocol(count($calls)===2&&$calls[0][0]===updateManifestUrl(),'Exact official manifest URL');
$html=htmlProtocol();checkProtocol(str_contains($html,'&lt;script&gt;')&&!str_contains($html,'<script>alert'),'Notes escaped');
foreach(['New features','Improvements','Fixes','0.3.0','Download size','Published','Skip this version'] as $label)checkProtocol(str_contains($html,$label),'Missing label '.$label);
notificationUpdateCheck(true,static function(){throw new RuntimeException('Should not use network');},$verify);
checkProtocol(setting('update_state')['phase']==='available','Six-hour interval respected');
$cached=notificationUpdateCheck(false,static function(string $url,?string $etag)use(&$calls):array{$calls[]=[$url,$etag];return ['not_modified'=>true,'etag'=>$etag];},$verify);
checkProtocol($cached['error']===null&&end($calls)[1]==='"first"','ETag/304 reuses signed cache');
foreach(['http_404','http_403','http_500','timeout','offline'] as $code){
 $state=notificationUpdateCheck(false,static function()use($code){throw new RuntimeException($code);},$verify);
 checkProtocol($state['error']===$code&&installed(),'Network failure handled: '.$code);
}
$bad=notificationUpdateCheck(false,static fn()=>['not_modified'=>false,'etag'=>null,'body'=>'{'],$verify);checkProtocol($bad['error']==='signature_invalid','Unsigned malformed manifest rejected');
$bad=notificationUpdateCheck(false,static fn()=>['not_modified'=>false,'etag'=>null,'body'=>'{'],static fn()=>null);checkProtocol($bad['error']==='invalid_manifest','Malformed JSON rejected');
mkdir($dir.'/updater');file_put_contents($dir.'/updater/config.json',jsonValue(['schema'=>3,'program'=>'Pulse','manifest'=>updateManifestUrl()]));
$release=validateUpdateManifest(protocolFixture(['mandatory'=>true]));
$status=['updater_version'=>4,'phase'=>'available','checked_epoch'=>time(),'release'=>$release];
file_put_contents($dir.'/updater/status.json',jsonValue($status));
checkProtocol(updaterSourceLabel()==='update_source_protocol','Source label');
$_POST=['csrf'=>$_SESSION['csrf']];
rejectProtocol(fn()=>handleAction('skip_update'),'Required update cannot be skipped');
checkProtocol(!str_contains(htmlProtocol(),'value="skip_update"'),'Required update has no skip button');
handleAction('install_update');checkProtocol(setting('update_install')['version']==='0.3.0','Manual installation binds version');
checkProtocol(strlen(setting('update_install')['nonce'])===24,'Unique installation request');
handleAction('postpone_update');checkProtocol(setting('update_install')===[]&&setting('update_postpone_until')>time(),'Postpone cancels pending consent');
$status['release']['mandatory']=false;file_put_contents($dir.'/updater/status.json',jsonValue($status));
handleAction('skip_update');checkProtocol(setting('update_skip')==='0.3.0'&&!publicUpdateState()['available'],'Optional skipped version');
$status['phase']='downloading';$status['progress']=['percent'=>50,'bytes'=>524288,'total'=>1048576,'speed'=>10240,'files'=>0];file_put_contents($dir.'/updater/status.json',jsonValue($status));
handleAction('cancel_update');checkProtocol(strlen(setting('update_cancel'))===24,'Cancellation request');
$html=htmlProtocol();checkProtocol(str_contains($html,'50%')&&str_contains($html,'0.50 / 1.00 MB')&&str_contains($html,'Cancel download'),'Real progress rendered');
$status['phase']='installed';file_put_contents($dir.'/updater/status.json',jsonValue($status));
rejectProtocol(fn()=>handleAction('cancel_update'),'Cannot cancel an installation transaction');
$status['phase']='up_to_date';$status['release']['version']=version();file_put_contents($dir.'/updater/status.json',jsonValue($status));
checkProtocol(str_contains(htmlProtocol(),'You are using the latest available version.'),'Up-to-date message');
rejectProtocol(fn()=>handleAction('install_update'),'Equal version cannot install');
$status['release']['version']='0.1.0';file_put_contents($dir.'/updater/status.json',jsonValue($status));rejectProtocol(fn()=>handleAction('install_update'),'Downgrade rejected');
$status['phase']='available';$status['release']['version']='0.3.0';file_put_contents($dir.'/updater/status.json',jsonValue($status));
checkProtocol(!publicUpdateState(false)['available']&&publicUpdateState(false)['installed']===null,'Updates hidden on TV');
$_POST=['csrf'=>'wrong'];rejectProtocol(fn()=>handleAction('install_update'),'CSRF validation');
foreach(['en','de','fr'] as $lang){$_SESSION['language']=$lang;checkProtocol(!str_contains(htmlProtocol(),'update_state_available'),'Translated state '.$lang);}
checkProtocol(password_verify('temporary-update-password',db()->query('SELECT password_hash FROM admins')->fetchColumn()),'Account retained');
sodium_memzero($secret);echo $checks." update protocol/UI checks passed.\n";
