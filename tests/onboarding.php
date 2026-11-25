<?php
declare(strict_types=1);
$dir=sys_get_temp_dir().'/pulse-onboarding-test-'.bin2hex(random_bytes(6));
putenv('PULSE_STORAGE='.$dir);
require dirname(__DIR__).'/app/bootstrap.php';
require PULSE_ROOT.'/app/views.php';
require PULSE_ROOT.'/app/actions.php';
boot();startSession();$checks=0;
function check(bool $ok,string $message): void {global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function rejects(callable $fn,string $message): void {try{$fn();}catch(DomainException $e){check(true,$message);return;}throw new RuntimeException($message);}
try {
    $interfaces=['eth0'=>['up'=>true,'unicast'=>[['address'=>'10.20.30.40'],['address'=>'fd12::123'],['address'=>'169.254.1.2'],['address'=>'8.8.8.8']]],
        'lo'=>['up'=>true,'unicast'=>[['address'=>'127.0.0.1']]],'docker0'=>['up'=>true,'unicast'=>[['address'=>'172.17.0.1']]],
        'wlan0'=>['up'=>false,'unicast'=>[['address'=>'192.168.5.2']]]];
    check(branchIpAddresses($interfaces)===['10.20.30.40','fd12::123'],'Only active LAN interfaces are advertised');
    check(branchSetupUrl('fd12::123')==='http://[fd12::123]:8080','IPv6 URL');
    $networks=$interfaces;
    $networks['wlan0']=['up'=>true,'unicast'=>[['address'=>'192.168.1.50']]];
    $networks['tailscale0']=['up'=>true,'unicast'=>[['address'=>'100.90.1.2'],['address'=>'fd7a:115c:a1e0::1']]];
    check(branchDisplayIp($networks,'10.20.30.40')==='10.20.30.40','Display selects primary Ethernet only');
    check(branchDisplayIp($networks,'192.168.1.50')==='192.168.1.50','Default Wi-Fi route takes precedence');
    check(branchDisplayIp($networks,'')==='10.20.30.40','Offline LAN prefers Ethernet');
    check(branchDisplayIp($networks,'100.90.1.2')==='10.20.30.40','VPN default does not replace LAN address');
    check(branchDisplayIp($networks,'fd7a:115c:a1e0::1')==='10.20.30.40','VPN ULA excluded');
    check(!in_array('fd7a:115c:a1e0::1',branchIpAddresses($networks),true),'VPN ULA not offered as local setup address');
    $networks['eth0']['up']=false;
    check(branchDisplayIp($networks,'10.20.30.40')==='192.168.1.50','Disconnected Ethernet is replaced by Wi-Fi');
    check(branchDisplayIp(['eth0'=>['up'=>true,'unicast'=>[['address'=>'10.20.30.41']]]],'10.20.30.40')==='10.20.30.41','DHCP address change reflected');
    check(branchDisplayIp(['eth0'=>['up'=>true,'unicast'=>[['address'=>'fd12::123']]]],'')==='fd12::123','IPv6-only LAN fallback');
    check(branchDisplayIp([], '')==='','No address until network connected');
    $lan=['REMOTE_ADDR'=>'10.20.30.55','HTTP_HOST'=>'10.20.30.40:8080'];
    check(branchSetupRequestAllowed($lan,$interfaces),'Private client and literal local address accepted');
    foreach(['10.0.0.1','172.16.0.1','172.31.255.254','192.168.1.2','fc00::1','fd12::2','::ffff:10.0.0.1'] as $ip)check(privateSetupIp($ip),'Private address '.$ip);
    foreach(['8.8.8.8','172.15.1.1','172.32.1.1','169.254.2.2','fe80::1','127.0.0.1','bad'] as $ip)check(!privateSetupIp($ip),'Non-private address '.$ip);
    foreach([
        ['REMOTE_ADDR'=>'8.8.8.8'],['HTTP_HOST'=>'attacker.example'],['HTTP_HOST'=>'192.168.9.9:8080'],
        ['HTTP_HOST'=>'10.20.30.40:99999'],['HTTP_ORIGIN'=>'https://evil.invalid'],['HTTP_ORIGIN'=>'null'],
        ['HTTP_SEC_FETCH_SITE'=>'cross-site'],['HTTP_X_FORWARDED_FOR'=>'10.20.30.55'],['HTTP_FORWARDED'=>'for=10.20.30.55'],
        ['HTTP_X_FORWARDED_HOST'=>'10.20.30.40:8080']
    ] as $bad)check(!branchSetupRequestAllowed(array_replace($lan,$bad),$interfaces),'Reject unsafe request '.json_encode($bad));
    check(branchSetupRequestAllowed($lan+['HTTP_ORIGIN'=>'http://10.20.30.40:8080'],$interfaces),'Same-origin accepted');
    check(branchSetupRequestAllowed(['REMOTE_ADDR'=>'fd12::2','HTTP_HOST'=>'[fd12::123]:8080'],$interfaces),'Private IPv6 accepted');
    $_SERVER=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8080','REQUEST_URI'=>'/setup','REQUEST_METHOD'=>'POST'];
    check(!branchSetupEnabled(),'Default closed');
    $input=['name'=>'Branch Admin','email'=>'branch@example.invalid','password'=>'test-only-phrase-123','store'=>'PULSE Branch Test','language'=>'de','duration'=>20];
    rejects(fn()=>initialize($input),'Default requires code');
    prepareBranchSetup();$token=setupToken();
    check(branchSetupAllowed(),'Explicit preparation enables setup');
    ob_start();renderSetup();$html=ob_get_clean();
    check(!str_contains($html,'name="token"')&&!str_contains($html,$token),'Code neither requested nor disclosed');
    check(!str_contains($html,'value="PULSE · Dietikon"'),'New store is not prefilled with Dietikon');
    cancelBranchSetup();check(!branchSetupEnabled(),'Cancellation closes access');
    ob_start();renderSetup();$html=ob_get_clean();check(str_contains($html,'name="token"'),'Normal setup code field retained');
    file_put_contents(storage('branch-setup.json'),'{invalid');check(!branchSetupEnabled(),'Malformed marker fails closed');
    file_put_contents(storage('branch-setup.json'),'[{"version":1,"prepared":true}]');check(!branchSetupEnabled(),'Wrong marker shape fails closed');
    prepareBranchSetup();
    $_POST=['action'=>'setup',...$input,'password_repeat'=>$input['password'],'csrf'=>'invalid'];
    rejects(fn()=>handleAction('setup'),'CSRF still mandatory');check(!installed(),'Bad CSRF created no admin');
    rejects(fn()=>initialize(array_replace($input,['password'=>'short'])),'Password policy retained');
    check(!installed()&&branchSetupEnabled(),'Validation failure preserves setup');
    $_SERVER['REMOTE_ADDR']='8.8.8.8';rejects(fn()=>initialize($input),'Public source cannot bypass code');
    $_SERVER['REMOTE_ADDR']='127.0.0.1';initialize($input);
    check(installed(),'Automatic setup succeeded');
    check(!branchSetupEnabled()&&!is_file(storage('branch-setup.json'))&&!is_file(storage('setup-token')),'Setup closes and consumes both files');
    rejects(fn()=>initialize($input),'Second setup refused');rejects(fn()=>prepareBranchSetup(),'Cannot prepare installed device');
    check((int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn()===1,'Only one admin');
    check(password_verify($input['password'],db()->query('SELECT password_hash FROM admins')->fetchColumn()),'Password hashed');
    db()->exec('DELETE FROM admins');file_put_contents(storage('branch-setup.json'),'{"version":1,"prepared":true}');
    check(!branchSetupEnabled(),'Stale marker never reopens an installed database');
    rejects(fn()=>prepareBranchSetup(),'Historical setup blocks preparation even if admins removed');
    echo "$checks onboarding checks passed.\n";
} finally {
    session_write_close();
    // Isolated fixture only. Windows may hold the SQLite handle until process exit.
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $f){if($f->isDir())@rmdir($f->getPathname());else @unlink($f->getPathname());}
    @rmdir($dir);
}
