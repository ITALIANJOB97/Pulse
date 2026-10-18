#!/usr/bin/env php
<?php
declare(strict_types=1);
// Workstation publisher: private GitHub release assets and a public guide-only ZIP.
if(PHP_SAPI!=='cli')exit(1);
try{
    if(!extension_loaded('sodium'))throw new RuntimeException('PHP sodium required (Windows: php -d extension=sodium).');
    $root=dirname(__DIR__);$command=$argv[1]??'';
    $private=$root.'/.release-private/signing.key';$public=$root.'/deploy/release-public.key';
    if($command==='init-key'){
        if(is_file($private)||is_file($public))throw new RuntimeException('Key already exists. Never replace a deployed signing key without a rotation plan.');
        if(!is_dir(dirname($private)))mkdir(dirname($private),0700,true);
        $pair=sodium_crypto_sign_keypair();
        $handle=fopen($private,'x');fwrite($handle,base64_encode(sodium_crypto_sign_secretkey($pair)).PHP_EOL);fclose($handle);chmod($private,0600);
        file_put_contents($public,base64_encode(sodium_crypto_sign_publickey($pair)).PHP_EOL);sodium_memzero($pair);
        echo "Signing key created. Back it up offline; never publish it.\n";exit;
    }
    if($command!=='publish')throw new RuntimeException('Usage: php bin/publish-release.php init-key | publish');
    $version=trim(file_get_contents($root.'/VERSION'));
    if(!preg_match('/^\d+\.\d+\.\d+$/D',$version))throw new RuntimeException('Invalid version.');
    $zip=$root.'/dist/PULSE-Dietikon-Linux-Mint-'.$version.'.zip';
    if(!is_file($zip))throw new RuntimeException('Run bin/build-linux-mint-zip.py first.');
    $secret=base64_decode(trim(file_get_contents($private)),true);$key=base64_decode(trim(file_get_contents($public)),true);
    if($secret===false||strlen($secret)!==SODIUM_CRYPTO_SIGN_SECRETKEYBYTES||!hash_equals(sodium_crypto_sign_publickey_from_secretkey($secret),$key))throw new RuntimeException('Signing key mismatch.');
    $dir=$root.'/dist/GitHub/v'.$version;if(!is_dir($dir))mkdir($dir,0755,true);
    $target=$dir.'/pulse-'.$version.'.zip';
    if(is_file($target)&&hash_file('sha256',$target)!==hash_file('sha256',$zip))throw new RuntimeException('This version already has a different package. Increment VERSION.');
    copy($zip,$target);
    $notes=json_decode(file_get_contents($root.'/deploy/release-notes.json'),true,32,JSON_THROW_ON_ERROR);
    foreach(['en','de','fr'] as $lang)if(!is_string($notes[$lang]??null)||mb_strlen($notes[$lang])>2000)throw new RuntimeException('Invalid release notes.');
    $manifest=[
        'app'=>'pulse-dietikon','schema'=>1,'channel'=>'stable','version'=>$version,
        'published_at'=>gmdate('c'),'expires_at'=>gmdate('c',time()+366*86400),
        'min_from'=>'0.2.0','min_updater'=>2,'platform'=>'linuxmint22-amd64','php_min'=>'8.3.0','db_schema'=>1,
        'url'=>'https://github.com/ITALIANJOB97/Pulse/releases/download/v'.$version.'/pulse-'.$version.'.zip',
        'size'=>filesize($target),'sha256'=>hash_file('sha256',$target),'notes'=>$notes
    ];
    $json=json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
    $sig=sodium_crypto_sign_detached($json,$secret);sodium_memzero($secret);
    if(!sodium_crypto_sign_verify_detached($sig,$json,$key))throw new RuntimeException('Signature self-check failed.');
    file_put_contents($dir.'/stable.json',$json);file_put_contents($dir.'/stable.json.sig',base64_encode($sig).PHP_EOL);
    $text="# PULSE ".$version."\n\nRepository privata: gli aggiornamenti richiedono una credenziale di sola lettura sul Mint.\n\n";
    $text.="Per passare dal blog a GitHub, scarica **pulse-".$version.".zip**, estrailo e segui PUBBLICAZIONE_GITHUB.txt. Dalla cartella estratta esegui:\n\n```bash\nsudo python3 deploy/configure-github-mint.py\nsudo bash deploy/upgrade-mint.sh\n```\n\n";
    $text.="Il primo comando richiede un fine-grained token limitato a Pulse con Contents: Read-only. Il secondo aggiorna una volta anche il servizio, crea il backup e conserva dati, utenti e foto. Non ripetere l’installer iniziale. Usa l’allegato **pulse-".$version.".zip**: gli archivi Source code di GitHub non sono pacchetti installabili.\n\n";
    foreach(['en'=>'English','de'=>'Deutsch','fr'=>'Français'] as $lang=>$title)$text.='## '.$title."\n\n".$notes[$lang]."\n\n";
    file_put_contents($dir.'/release-notes.md',$text);
    // The blog package deliberately contains only the public help pages.
    $out=$root.'/dist/PULSE-Guida-Blog-'.$version.'.zip';$archive=new ZipArchive();
    if($archive->open($out,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Cannot build guide archive.');
    $guide=$root.'/public/guide';
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($guide,FilesystemIterator::SKIP_DOTS)) as $file){
        if($file->isLink()||!$file->isFile())throw new RuntimeException('Unexpected guide entry.');
        $relative=str_replace('\\','/',substr($file->getPathname(),strlen($guide)+1));
        $archive->addFile($file->getPathname(),'Pulse/guide/'.$relative);
    }
    $archive->close();file_put_contents($out.'.sha256',hash_file('sha256',$out).'  '.basename($out).PHP_EOL);
    echo "Signed private GitHub assets: dist/GitHub/v".$version."\nPublic guide only: ".basename($out)."\nNo runtime data or private signing key packaged.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
