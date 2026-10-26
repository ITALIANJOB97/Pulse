<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||count($argv)!==4)exit(1);
try {
    if(!function_exists('sodium_crypto_sign_verify_detached'))throw new RuntimeException('PHP sodium is required.');
    if(filesize($argv[1])>1048576||filesize($argv[2])>256||filesize($argv[3])>128)throw new RuntimeException('Invalid signature input.');
    $body=file_get_contents($argv[1]);
    $sig=base64_decode(trim(file_get_contents($argv[2])),true);
    $key=base64_decode(trim(file_get_contents($argv[3])),true);
    if($sig===false||$key===false||strlen($sig)!==SODIUM_CRYPTO_SIGN_BYTES||strlen($key)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES||!sodium_crypto_sign_verify_detached($sig,$body,$key))throw new RuntimeException('Invalid release signature.');
    echo "Signature verified.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
