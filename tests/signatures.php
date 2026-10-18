<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!extension_loaded('sodium'))exit(1);
$dir=sys_get_temp_dir().'/pulse-signature-test-'.bin2hex(random_bytes(4));mkdir($dir);
$pair=sodium_crypto_sign_keypair();$body='{"app":"pulse-dietikon","version":"0.2.1"}';
file_put_contents($dir.'/manifest.json',$body);
file_put_contents($dir.'/manifest.sig',base64_encode(sodium_crypto_sign_detached($body,sodium_crypto_sign_secretkey($pair))));
file_put_contents($dir.'/public.key',base64_encode(sodium_crypto_sign_publickey($pair)));
function verifyTest(string $dir):int{
    $pipes=[];$process=proc_open([PHP_BINARY,'-d','extension=sodium',dirname(__DIR__).'/deploy/verify-release.php',$dir.'/manifest.json',$dir.'/manifest.sig',$dir.'/public.key'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fclose($pipes[0]);stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return proc_close($process);
}
if(verifyTest($dir)!==0)throw new RuntimeException('Valid signature rejected.');
file_put_contents($dir.'/manifest.json',$body.' ');
if(verifyTest($dir)===0)throw new RuntimeException('Modified manifest accepted.');
file_put_contents($dir.'/manifest.json',$body);
file_put_contents($dir.'/public.key',base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair())));
if(verifyTest($dir)===0)throw new RuntimeException('Untrusted signing key accepted.');
file_put_contents($dir.'/manifest.sig','invalid');
if(verifyTest($dir)===0)throw new RuntimeException('Malformed signature accepted.');
echo "Ed25519 checks passed: valid signature, exact-byte tampering, wrong key, malformed signature.\n";
