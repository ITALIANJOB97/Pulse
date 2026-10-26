#!/usr/bin/env php
<?php
declare(strict_types=1);
// Sign an operator-written manifest without ever changing its contents or version.
if(PHP_SAPI!=='cli')exit(1);
define('PULSE_ROOT',dirname(__DIR__));
require PULSE_ROOT.'/app/update_protocol.php';
try{
    if(count($argv)!==3)throw new RuntimeException('Usage: php bin/sign-update.php /path/update.json /path/package.zip');
    [$script,$manifest,$package]=$argv;
    if(!is_file($manifest)||filesize($manifest)>1048576||!is_file($package))throw new RuntimeException('Missing manifest/package or manifest exceeds 1 MB.');
    $body=file_get_contents($manifest);$data=json_decode($body,true,32,JSON_THROW_ON_ERROR);
    if(!is_array($data))throw new RuntimeException('Invalid JSON object.');
    $release=validateUpdateManifest($data);
    $current=trim(file_get_contents(PULSE_ROOT.'/VERSION'));
    if($release['version']!==$current)throw new RuntimeException('Manifest version must equal VERSION. Choose the release version explicitly before signing.');
    if(isset($release['size'])&&$release['size']!==filesize($package)||!hash_equals($release['sha256'],hash_file('sha256',$package)))throw new RuntimeException('Package size/SHA-256 mismatch.');
    $zip=new ZipArchive();if($zip->open($package,ZipArchive::CHECKCONS)!==true)throw new RuntimeException('Invalid ZIP.');
    $versions=[];
    for($i=0;$i<$zip->numFiles;$i++){ $name=$zip->getNameIndex($i);if(preg_match('~^[^/]+/VERSION$~D',$name))$versions[]=trim($zip->getFromIndex($i)); }
    $zip->close();if($versions!==[$current])throw new RuntimeException('Package version does not match manifest.');
    $secret=base64_decode(trim(file_get_contents(PULSE_ROOT.'/.release-private/signing.key')),true);
    $public=base64_decode(trim(file_get_contents(PULSE_ROOT.'/deploy/release-public.key')),true);
    if($secret===false||strlen($secret)!==SODIUM_CRYPTO_SIGN_SECRETKEYBYTES||$public===false||!hash_equals(sodium_crypto_sign_publickey_from_secretkey($secret),$public))throw new RuntimeException('Signing key mismatch.');
    try{$signature=base64_encode(sodium_crypto_sign_detached($body,$secret)).PHP_EOL;}finally{sodium_memzero($secret);}
    verifyUpdateSignature($body,$signature);
    $output=$manifest.'.sig';$handle=fopen($output,'x');if(!$handle)throw new RuntimeException('Signature already exists; review it before replacing.');
    try{fwrite($handle,$signature);}finally{fclose($handle);}
    echo 'Signature saved: '.$output.PHP_EOL.'Manifest unchanged. Upload the package, then its signature, and update.json last.'.PHP_EOL;
}catch(Throwable $error){fwrite(STDERR,$error->getMessage().PHP_EOL);exit(1);}
