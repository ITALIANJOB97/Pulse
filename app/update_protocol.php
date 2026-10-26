<?php
declare(strict_types=1);

function updateSource(): array {
    static $source;
    return $source ??= json_decode((string)file_get_contents(dirname(__DIR__).'/deploy/update-source.json'),true,8,JSON_THROW_ON_ERROR);
}
function updateManifestUrl(): string { return updateSource()['baseUrl'].updateSource()['manifest']; }
function updateVersionValid(mixed $value): bool {
    return is_string($value)&&preg_match('/^(?:0|[1-9][0-9]{0,8})\.(?:0|[1-9][0-9]{0,8})\.(?:0|[1-9][0-9]{0,8})$/D',$value)===1;
}
function updatePackageUrl(mixed $url): bool {
    return is_string($url)&&preg_match('~^'.preg_quote(updateSource()['baseUrl'],'~').'[A-Za-z0-9][A-Za-z0-9_-]*(?:\.[A-Za-z0-9_-]+)*\.zip$~D',$url)===1;
}
function validateUpdateManifest(array $data): array {
    if(($data['schemaVersion']??null)!==1||($data['program']??null)!==updateSource()['program']||!updateVersionValid($data['version']??null))throw new DomainException('Invalid update manifest or program.');
    if(!updatePackageUrl($data['downloadUrl']??null)||!is_string($data['sha256']??null)||!preg_match('/^[a-fA-F0-9]{64}$/D',$data['sha256']))throw new DomainException('Invalid download URL or SHA-256.');
    $minimum=$data['minimumSupportedVersion']??'0.0.0';
    if(!updateVersionValid($minimum)||version_compare($minimum,$data['version'],'>'))throw new DomainException('Invalid minimum version.');
    $mandatory=$data['mandatory']??false;
    if(!is_bool($mandatory))throw new DomainException('Invalid mandatory flag.');
    if(array_key_exists('size',$data)&&(!is_int($data['size'])||$data['size']<1||$data['size']>104857600))throw new DomainException('Invalid download size.');
    $published=$data['publishedAt']??null;
    if($published!==null){
        if(!is_string($published)||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D',$published))throw new DomainException('Invalid publication date.');
        $date=new DateTimeImmutable($published);
        $errors=DateTimeImmutable::getLastErrors();
        if($errors!==false&&($errors['warning_count']||$errors['error_count']))throw new DomainException('Invalid publication date.');
        if($date->getTimestamp()>time()+600)throw new DomainException('Publication date is in the future.');
    }
    $notes=$data['releaseNotes']??[];$clean=[];
    if(!is_array($notes))throw new DomainException('Invalid release notes.');
    foreach(['features','improvements','fixes'] as $category){
        $items=$notes[$category]??[];
        if(!is_array($items)||!array_is_list($items)||count($items)>50)throw new DomainException('Invalid release notes.');
        foreach($items as $text)if(!is_string($text)||mb_strlen($text)>2000)throw new DomainException('Invalid release notes.');
        $clean[$category]=$items;
    }
    return ['version'=>$data['version'],'release_url'=>$data['downloadUrl'],'url'=>$data['downloadUrl'],
        'sha256'=>strtolower($data['sha256']),'size'=>$data['size']??null,'published_at'=>$published,
        'mandatory'=>$mandatory,'min_from'=>$minimum,'release_notes'=>$clean,'notes'=>['en'=>'','de'=>'','fr'=>'']];
}
function updateHttp(string $url,?string $etag=null,int $limit=1048576): array {
    if(!in_array($url,[updateManifestUrl(),updateManifestUrl().'.sig'],true))throw new DomainException('Untrusted manifest URL.');
    $body='';$responseEtag=null;$headers=['Accept: application/json','Cache-Control: no-cache'];
    if($etag!==null&&strlen($etag)<=512&&!preg_match('/[\r\n]/',$etag))$headers[]='If-None-Match: '.$etag;
    $curl=curl_init($url);
    curl_setopt_array($curl,[CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>10,CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_USERAGENT=>'PULSE/'.version(),CURLOPT_HTTPHEADER=>$headers,
        CURLOPT_HEADERFUNCTION=>static function($handle,string $line) use (&$responseEtag):int {
            if(strncasecmp($line,'ETag:',5)===0){$value=trim(substr($line,5));if(strlen($value)<=512&&!preg_match('/[\r\n]/',$value))$responseEtag=$value;}
            return strlen($line);
        },
        CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk) use (&$body,$limit):int {
            if(strlen($body)+strlen($chunk)>$limit)return 0;
            $body.=$chunk;return strlen($chunk);
        },
    ]);
    try{
        $ok=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$errno=curl_errno($curl);
        if($ok===false)throw new RuntimeException($errno===CURLE_OPERATION_TIMEDOUT?'timeout':($errno===CURLE_WRITE_ERROR?'invalid_manifest':'offline'));
        if($status===304&&$etag!==null)return ['not_modified'=>true,'etag'=>$etag];
        if($status!==200)throw new RuntimeException(in_array($status,[403,404,500],true)?'http_'.$status:'http_error');
        return ['not_modified'=>false,'etag'=>$responseEtag,'body'=>$body];
    }finally{curl_close($curl);}
}
function verifyUpdateSignature(string $body,string $encoded,?string $publicKey=null): void {
    if(strlen($body)>1048576||strlen($encoded)>256||!function_exists('sodium_crypto_sign_verify_detached'))throw new RuntimeException('signature_invalid');
    $signature=base64_decode(trim($encoded),true);$key=base64_decode(trim($publicKey??(string)file_get_contents(PULSE_ROOT.'/deploy/release-public.key')),true);
    if($signature===false||$key===false||strlen($signature)!==64||strlen($key)!==32||!sodium_crypto_sign_verify_detached($signature,$body,$key))throw new RuntimeException('signature_invalid');
}
function updateLog(string $event,array $fields=[]): void {
    error_log('[PULSE update] '.json_encode(['event'=>$event]+$fields,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
}
function notificationUpdateCheck(bool $automatic=false,?callable $fetch=null,?callable $verify=null): array {
    $state=setting('update_state',[]);
    if($automatic&&time()-(int)($state['checked_epoch']??0)<21600)return $state;
    $lock=fopen(storage('update-check.lock'),'c');
    if($lock===false)throw new RuntimeException('Cannot lock update check.');
    if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);return $state+['queued'=>true];}
    try{
        $state=setting('update_state',[]);
        if($automatic&&time()-(int)($state['checked_epoch']??0)<21600)return $state;
        updateLog('check_started',['url'=>updateManifestUrl(),'current'=>version()]);
        $fetch??= 'updateHttp';
        try{
            $cache=setting('update_manifest_cache',[]);
            $response=$fetch(updateManifestUrl(),$cache['etag']??null,1048576);
            if($response['not_modified']){
                if(!is_string($cache['body']??null)||!is_string($cache['signature']??null))throw new RuntimeException('invalid_manifest');
                $body=$cache['body'];$signature=$cache['signature'];
            }else{
                $body=$response['body'];$signature=$fetch(updateManifestUrl().'.sig',null,256)['body'];
            }
            ($verify??'verifyUpdateSignature')($body,$signature);
            $data=json_decode($body,true,32,JSON_THROW_ON_ERROR);
            if(!is_array($data))throw new RuntimeException('invalid_manifest');
            $release=validateUpdateManifest($data);
            setSetting('update_manifest_cache',['body'=>$body,'signature'=>$signature,'etag'=>$response['etag']??null]);
            $state=['release'=>$release,'phase'=>version_compare($release['version'],version(),'>')?'available':'up_to_date','error'=>null];
            updateLog('check_completed',['current'=>version(),'remote'=>$release['version'],'available'=>$state['phase']==='available']);
        }catch(Throwable $error){
            $code=in_array($error->getMessage(),['http_403','http_404','http_500','http_error','timeout','offline','signature_invalid'],true)?$error->getMessage():'invalid_manifest';
            $state['error']=$code;$state['phase']='offline';setSetting('update_manifest_cache',[]);
            updateLog('check_failed',['error'=>$code]);
        }
        $state['checked_at']=gmdate('c');$state['checked_epoch']=time();$state['configured']=true;
        setSetting('update_state',$state);return $state;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
function updaterControlsAvailable(): bool { return updaterManaged()&&(int)(managedUpdateState()['updater_version']??0)>=4; }
function updateAction(string $action): void {
    $state=updaterManaged()?managedUpdateState():setting('update_state',[]);
    $release=$state['release']??null;
    if($action==='cancel_update'){
        if(!updaterControlsAvailable()||($state['phase']??'')!=='downloading')throw new DomainException(t('invalid_input'));
        setSetting('update_cancel',bin2hex(random_bytes(12)));return;
    }
    if(!is_array($release)||!updateVersionValid($release['version']??null)||!version_compare($release['version'],version(),'>'))throw new DomainException(t('invalid_input'));
    if($action==='skip_update'){
        if(!empty($release['mandatory']))throw new DomainException(t('update_mandatory'));
        if(updaterManaged()&&!updaterControlsAvailable())throw new DomainException(t('update_service_upgrade'));
        setSetting('update_skip',$release['version']);setSetting('update_install',[]);return;
    }
    if($action==='install_update'){
        if(!updaterControlsAvailable()||in_array($state['phase']??'',['incompatible','offline','checking'],true))throw new DomainException(t('update_service_upgrade'));
        setSetting('update_skip','');setSetting('update_postpone_until',0);
        setSetting('update_install',['version'=>$release['version'],'nonce'=>bin2hex(random_bytes(12))]);
        setSetting('update_retry',bin2hex(random_bytes(12)));return;
    }
    throw new DomainException(t('invalid_input'));
}
