<?php
declare(strict_types=1);
/** Hosting CLI relay. Keep this script, config and public key OUTSIDE the web root.
 * It mirrors already signed assets; it never holds the private signing key.
 */
const PULSE_REPO_API='https://api.github.com/repos/ITALIANJOB97/Pulse';
const PULSE_BLOG_ORIGIN='https://www.luigipranzo.com/myfile/Pulse/';
const PULSE_MAX_PACKAGE=104857600;

function pulseRelayAssert(bool $ok,string $message): void {
    if(!$ok)throw new RuntimeException($message);
}
function pulseRelayApi(string $url): bool {
    return $url===PULSE_REPO_API.'/releases/latest'||preg_match('~^'.preg_quote(PULSE_REPO_API,'~').'/releases/assets/[1-9][0-9]*$~D',$url)===1;
}
function pulseRelayCdn(string $url): bool {
    $p=parse_url($url);
    return is_array($p)&&!preg_match('/[\\\\\s]/',$url)&&($p['scheme']??'')==='https'
        &&($p['host']??'')==='release-assets.githubusercontent.com'
        &&str_starts_with($p['path']??'','/github-production-release-asset/')
        &&!isset($p['port'])&&!isset($p['user'])&&!isset($p['pass'])&&!isset($p['fragment']);
}
function pulseRelayHeaders(string $url,string $token): array {
    if(pulseRelayApi($url))return ['Authorization: Bearer '.$token,
        'Accept: '.(str_ends_with($url,'/latest')?'application/vnd.github+json':'application/octet-stream'),
        'X-GitHub-Api-Version: 2022-11-28'];
    pulseRelayAssert(pulseRelayCdn($url),'Untrusted download host.');
    return ['Accept: application/octet-stream']; // Never forward GitHub credentials to the CDN.
}
function pulseRelayDownload(string $url,int $limit,string $token): string {
    pulseRelayAssert(pulseRelayApi($url),'Download must start at the repository API.');
    for($redirect=0;$redirect<4;$redirect++){
        $body='';$location='';$curl=curl_init($url);
        curl_setopt_array($curl,[CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>120,
            CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_USERAGENT=>'PULSE-Blog-Relay/1',CURLOPT_HTTPHEADER=>pulseRelayHeaders($url,$token),
            CURLOPT_WRITEFUNCTION=>static function($c,string $chunk)use(&$body,$limit):int{
                if(strlen($body)+strlen($chunk)>$limit)return 0;
                $body.=$chunk;return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION=>static function($c,string $line)use(&$location):int{
                if(strncasecmp($line,'Location:',9)===0)$location=trim(substr($line,9));
                return strlen($line);
            }]);
        $ok=curl_exec($curl);$code=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
        pulseRelayAssert($ok!==false,'GitHub request failed or exceeded its size/time limit.');
        if($code===200)return $body;
        if(in_array($code,[301,302,303,307,308],true)){
            pulseRelayAssert(!str_ends_with($url,'/latest')&&pulseRelayCdn($location),'Untrusted redirect.');
            $url=$location;continue;
        }
        throw new RuntimeException('GitHub returned HTTP '.$code.'. Check access, token expiry and API limits.');
    }
    throw new RuntimeException('Too many download redirects.');
}
function pulseRelayManifest(string $body,string $signature,string $publicKey,int $clock): array {
    pulseRelayAssert(strlen($body)<=65536&&strlen($signature)<=256,'Manifest exceeds limit.');
    $sig=base64_decode(trim($signature),true);$key=base64_decode(trim($publicKey),true);
    pulseRelayAssert($sig!==false&&strlen($sig)===64&&$key!==false&&strlen($key)===32,'Invalid signature encoding.');
    pulseRelayAssert(sodium_crypto_sign_verify_detached($sig,$body,$key),'Release signature rejected.');
    $m=json_decode($body,true,32,JSON_THROW_ON_ERROR);
    pulseRelayAssert(is_array($m)&&($m['app']??'')==='pulse-dietikon'&&($m['schema']??null)===1&&($m['channel']??'')==='stable','Wrong application.');
    foreach(['version','min_from','php_min'] as $k)pulseRelayAssert(is_string($m[$k]??null)&&preg_match('/^\d+\.\d+\.\d+$/D',$m[$k])===1,'Invalid version.');
    pulseRelayAssert(($m['min_updater']??null)===1&&($m['platform']??'')==='linuxmint22-amd64'&&($m['db_schema']??null)===1,'Incompatible blog update.');
    pulseRelayAssert(($m['url']??'')===PULSE_BLOG_ORIGIN.'pulse-'.$m['version'].'.zip','Wrong blog package URL.');
    pulseRelayAssert(is_int($m['size']??null)&&$m['size']>0&&$m['size']<=PULSE_MAX_PACKAGE,'Invalid package size.');
    pulseRelayAssert(is_string($m['sha256']??null)&&preg_match('/^[a-f0-9]{64}$/D',$m['sha256'])===1,'Invalid checksum.');
    foreach(['published_at','expires_at'] as $k){
        pulseRelayAssert(is_string($m[$k]??null)&&preg_match('/(?:Z|[+-]\d\d:\d\d)$/D',$m[$k])===1,'Missing release timestamp.');
        $time=strtotime($m[$k]);pulseRelayAssert($time!==false&&($k==='expires_at'?$time>$clock:$time<=$clock+600),'Release is expired or from the future.');
    }
    foreach(['en','de','fr'] as $lang)pulseRelayAssert(is_string($m['notes'][$lang]??null)&&mb_strlen($m['notes'][$lang])<=2000,'Invalid release notes.');
    return $m;
}
function pulseRelayAssets(array $release): array {
    pulseRelayAssert(($release['draft']??null)===false&&($release['prerelease']??null)===false,'Not a published stable release.');
    $tag=$release['tag_name']??'';
    pulseRelayAssert(is_string($tag)&&preg_match('/^v\d+\.\d+\.\d+$/D',$tag)===1&&is_array($release['assets']??null),'Invalid release tag or assets.');
    $result=[];
    foreach(['blog-stable.json','blog-stable.json.sig','pulse-'.substr($tag,1).'.zip'] as $name){
        $matches=array_values(array_filter($release['assets'],static fn($a)=>is_array($a)&&($a['name']??null)===$name));
        pulseRelayAssert(count($matches)===1&&($matches[0]['state']??'')==='uploaded','Release is missing a relay asset.');
        $asset=$matches[0];
        pulseRelayAssert(($asset['browser_download_url']??'')==='https://github.com/ITALIANJOB97/Pulse/releases/download/'.$tag.'/'.$name,'Wrong repository asset.');
        $url=$asset['url']??'';
        pulseRelayAssert(is_string($url)&&pulseRelayApi($url)&&!str_ends_with($url,'/latest'),'Wrong asset API URL.');
        $result[$name]=$url;
    }
    return [substr($tag,1),$result];
}
function pulseRelayAtomic(string $path,string $contents): void {
    $tmp=tempnam(dirname($path),'.pulse-stage-');
    pulseRelayAssert($tmp!==false,'Cannot create staging file.');
    try{
        pulseRelayAssert(file_put_contents($tmp,$contents,LOCK_EX)===strlen($contents),'Cannot write staging file.');
        chmod($tmp,0644);
        pulseRelayAssert(rename($tmp,$path),'Cannot publish file.');
    }finally{if(is_file($tmp))unlink($tmp);}
}
function pulseRelaySync(string $directory,string $publicKey,callable $fetch,?int $clock=null): string {
    $clock??=time();
    $release=json_decode($fetch(PULSE_REPO_API.'/releases/latest',1048576),true,32,JSON_THROW_ON_ERROR);
    [$version,$assets]=pulseRelayAssets($release);
    $body=$fetch($assets['blog-stable.json'],65536);$sig=$fetch($assets['blog-stable.json.sig'],256);
    $m=pulseRelayManifest($body,$sig,$publicKey,$clock);
    pulseRelayAssert($version===$m['version'],'Manifest does not match the release tag.');
    $jsonPath=$directory.'/stable.json';$sigPath=$jsonPath.'.sig';
    $oldBody=is_file($jsonPath)?file_get_contents($jsonPath):null;
    $oldSig=is_file($sigPath)?file_get_contents($sigPath):null;
    if($oldBody!==null&&$oldSig!==null&&$oldBody!==$body){
        // Ignore expiration only for the existing, signed version floor.
        $oldParsed=json_decode($oldBody,true,32,JSON_THROW_ON_ERROR);
        $oldClock=strtotime($oldParsed['published_at']??'');
        try{$old=pulseRelayManifest($oldBody,$oldSig,$publicKey,$oldClock?:$clock);}
        catch(Throwable $e){
            // Recover a crash between the two atomic pointer writes, but only
            // when the signature is exactly that of the verified incoming release.
            if($oldSig!==$sig)throw $e;
            $old=null;
        }
        if($old!==null){
            pulseRelayAssert(version_compare($version,$old['version'],'>='),'Refusing a release downgrade.');
            if($version===$old['version'])pulseRelayAssert($m['sha256']===$old['sha256'],'Version already has a different package.');
        }
    }
    $package=$directory.'/pulse-'.$version.'.zip';
    if(is_file($package))pulseRelayAssert(filesize($package)===$m['size']&&hash_file('sha256',$package)===$m['sha256'],'Existing version has a different package.');
    else{
        $zip=$fetch($assets[basename($package)],$m['size']);
        pulseRelayAssert(strlen($zip)===$m['size']&&hash('sha256',$zip)===$m['sha256'],'Package checksum or size rejected.');
        pulseRelayAtomic($package,$zip);unset($zip);
    }
    if($oldBody!==$body||$oldSig!==$sig){
        pulseRelayAtomic($sigPath,$sig);
        pulseRelayAtomic($jsonPath,$body); // Publish the pointer only after ZIP and signature are ready.
    }
    return $version;
}
function pulseRelayMain(array $args): void {
    pulseRelayAssert(count($args)===3&&$args[1]==='--config','Usage: php blog-sync.php --config /private/pulse-relay/config.php');
    foreach(['curl','sodium','mbstring'] as $extension)pulseRelayAssert(extension_loaded($extension),'PHP extension required: '.$extension);
    $configPath=realpath($args[2]);pulseRelayAssert($configPath!==false,'Configuration file not found.');
    $config=require $configPath;
    pulseRelayAssert(is_array($config),'Invalid configuration.');
    $webroot=realpath($config['web_root']??'');$directory=realpath($config['public_dir']??'');
    pulseRelayAssert($webroot!==false&&$directory!==false&&is_dir($directory)&&is_writable($directory),'Invalid hosting paths.');
    $inside=static fn(string $p):bool=>$p===$webroot||str_starts_with($p,$webroot.DIRECTORY_SEPARATOR);
    pulseRelayAssert(!$inside($configPath)&&!$inside((string)realpath(__FILE__)),'Relay and token configuration must be outside the public web root.');
    pulseRelayAssert($directory===$webroot.DIRECTORY_SEPARATOR.'myfile'.DIRECTORY_SEPARATOR.'Pulse','public_dir must be web_root/myfile/Pulse.');
    if(PHP_OS_FAMILY!=='Windows')pulseRelayAssert((fileperms($configPath)&0077)===0,'Set config.php permissions to 600.');
    $token=$config['github_token']??getenv('PULSE_GITHUB_TOKEN');
    pulseRelayAssert(is_string($token)&&preg_match('/^[A-Za-z0-9_]{20,255}$/D',$token)===1,'Configure a repository-only Contents: Read-only token.');
    $key=file_get_contents(__DIR__.'/release-public.key');
    $lock=fopen(dirname($configPath).'/sync.lock','c');pulseRelayAssert($lock!==false,'Cannot open relay lock.');
    if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);return;}
    try{
        $version=pulseRelaySync($directory,$key,static fn(string $url,int $limit):string=>pulseRelayDownload($url,$limit,$token));
        echo 'PULSE '.$version.' available via blog.'.PHP_EOL;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
    ini_set('display_errors','0');
    try{pulseRelayMain($argv);}catch(Throwable $e){
        // No tokens, response bodies or signed CDN query strings in diagnostics.
        fwrite(STDERR,'PULSE relay: '.($e instanceof RuntimeException?$e->getMessage():'Invalid response or hosting configuration.').PHP_EOL);exit(1);
    }
}
