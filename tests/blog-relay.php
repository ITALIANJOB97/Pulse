<?php
declare(strict_types=1);
require dirname(__DIR__).'/deploy/blog-sync.php';
$checks=0;
function check(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function rejects(callable $fn,string $message):void{try{$fn();}catch(Throwable){check(true,$message);return;}throw new RuntimeException($message);}
$pair=sodium_crypto_sign_keypair();$secret=sodium_crypto_sign_secretkey($pair);$public=base64_encode(sodium_crypto_sign_publickey($pair));
$clock=time();$package='test-package-bytes';$root=sys_get_temp_dir().'/pulse-relay-test-'.bin2hex(random_bytes(8));mkdir($root,0700);
function fixture(string $version,array $overrides=[]):array{
    global $clock,$package,$secret;
    $m=array_replace(['app'=>'pulse-dietikon','schema'=>1,'channel'=>'stable','version'=>$version,'min_from'=>'0.2.0','min_updater'=>1,
        'platform'=>'linuxmint22-amd64','php_min'=>'8.3.0','db_schema'=>1,'published_at'=>gmdate('c',$clock-60),'expires_at'=>gmdate('c',$clock+86400),
        'url'=>PULSE_BLOG_ORIGIN.'pulse-'.$version.'.zip','size'=>strlen($package),'sha256'=>hash('sha256',$package),'notes'=>['en'=>'New','de'=>'Neu','fr'=>'Nouveau']],$overrides);
    $body=json_encode($m,JSON_UNESCAPED_SLASHES);$sig=base64_encode(sodium_crypto_sign_detached($body,$secret))."\n";
    $names=['blog-stable.json','blog-stable.json.sig','pulse-'.$version.'.zip'];$assets=[];$responses=[];
    foreach($names as $i=>$name){$url=PULSE_REPO_API.'/releases/assets/'.($i+1);$assets[]=['name'=>$name,'state'=>'uploaded','url'=>$url,'browser_download_url'=>'https://github.com/ITALIANJOB97/Pulse/releases/download/v'.$version.'/'.$name];$responses[$url]=[$body,$sig,$package][$i];}
    $release=['tag_name'=>'v'.$version,'draft'=>false,'prerelease'=>false,'assets'=>$assets];
    $responses[PULSE_REPO_API.'/releases/latest']=json_encode($release);
    return [$responses,$body,$sig,$release];
}
function fetcher(array $responses):Closure{return static function(string $url,int $limit)use($responses):string{
    if(!isset($responses[$url])||strlen($responses[$url])>$limit)throw new RuntimeException('Unexpected URL/limit.');return $responses[$url];
};}
try{
    [$responses,$body,$sig,$release]=fixture('0.2.3');
    check(pulseRelaySync($root,$public,fetcher($responses),$clock)==='0.2.3','First publish');
    check(file_get_contents($root.'/pulse-0.2.3.zip')===$package,'Package retained');
    check(file_get_contents($root.'/stable.json')===$body&&file_get_contents($root.'/stable.json.sig')===$sig,'Matching pointers');
    unset($responses[PULSE_REPO_API.'/releases/assets/3']);
    check(pulseRelaySync($root,$public,fetcher($responses),$clock)==='0.2.3','Idempotent without redownloading package');
    [$responses]=fixture('0.2.4');$responses[PULSE_REPO_API.'/releases/assets/1'].=' ';
    rejects(fn()=>pulseRelaySync($root,$public,fetcher($responses),$clock),'Tampered manifest rejected');
    [$responses]=fixture('0.2.4');$responses[PULSE_REPO_API.'/releases/assets/3']='bad';
    rejects(fn()=>pulseRelaySync($root,$public,fetcher($responses),$clock),'Bad package rejected');
    check(file_get_contents($root.'/stable.json')===$body&&!file_exists($root.'/pulse-0.2.4.zip'),'Failures preserve live release');
    [$responses]=fixture('0.2.2');rejects(fn()=>pulseRelaySync($root,$public,fetcher($responses),$clock),'Downgrade rejected');
    [$responses]=fixture('0.2.3',['sha256'=>str_repeat('a',64)]);rejects(fn()=>pulseRelaySync($root,$public,fetcher($responses),$clock),'Version mutation rejected');
    foreach([
        ['min_updater'=>2],['url'=>'https://example.com/pulse-0.2.4.zip'],['expires_at'=>gmdate('c',$clock-1)],
        ['published_at'=>gmdate('c',$clock+3600)],['version'=>'0.2.5'],['size'=>PULSE_MAX_PACKAGE+1],['db_schema'=>2]
    ] as $override){[$responses]=fixture('0.2.4',$override);rejects(fn()=>pulseRelaySync($root,$public,fetcher($responses),$clock),'Manifest requirement rejected');}
    $bad=$release;$bad['draft']=true;rejects(fn()=>pulseRelayAssets($bad),'Draft rejected');
    $bad=$release;$bad['assets'][]=$bad['assets'][0];rejects(fn()=>pulseRelayAssets($bad),'Duplicate asset rejected');
    $bad=$release;$bad['assets'][0]['url']='https://api.github.com/repos/other/repo/releases/assets/1';rejects(fn()=>pulseRelayAssets($bad),'Wrong repository rejected');
    check(in_array('Authorization: Bearer secret',pulseRelayHeaders(PULSE_REPO_API.'/releases/assets/1','secret'),true),'API authorization');
    $cdn='https://release-assets.githubusercontent.com/github-production-release-asset/1/2?sig=abc';
    check(!str_contains(implode(' ',pulseRelayHeaders($cdn,'secret')),'secret'),'CDN receives no token');
    foreach(['https://example.com/x','https://release-assets.githubusercontent.com.evil.test/github-production-release-asset/x','https://user@release-assets.githubusercontent.com/github-production-release-asset/x','https://release-assets.githubusercontent.com:444/github-production-release-asset/x'] as $url)rejects(fn()=>pulseRelayHeaders($url,'secret'),'Untrusted host rejected');
    [$responses,$newBody,$newSig]=fixture('0.2.4');file_put_contents($root.'/stable.json.sig',$newSig);
    check(pulseRelaySync($root,$public,fetcher($responses),$clock)==='0.2.4','Interrupted pointer change recovered');
    check(file_get_contents($root.'/stable.json')===$newBody,'New pointer published');
    // Source labels use the installed privileged configuration, not the PHP version.
    require dirname(__DIR__).'/app/updates.php';
    $GLOBALS['pulse_config']['updater_config']=$root.'/config.json';
    file_put_contents($root.'/config.json',json_encode(['schema'=>1,'origin'=>PULSE_BLOG_ORIGIN]));check(updaterSourceLabel()==='update_source_blog','Legacy label');
    file_put_contents($root.'/config.json',json_encode(['schema'=>2,'repository'=>'ITALIANJOB97/Pulse']));check(updaterSourceLabel()==='update_source_github','Direct GitHub label');
    file_put_contents($root.'/config.json','bad');check(updaterSourceLabel()==='update_source_unknown','Invalid config label');
    echo $checks." relay and source checks passed.\n";
}finally{
    foreach(glob($root.'/*') as $file)if(is_file($file))unlink($file);
    foreach(glob($root.'/.pulse-stage-*') as $file)if(is_file($file))unlink($file);
    rmdir($root);sodium_memzero($secret);
}
