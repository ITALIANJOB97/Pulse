#!/usr/bin/env php
<?php
declare(strict_types=1);
// Workstation publisher: sign public GitHub and legacy blog channels with one offline key.
if(PHP_SAPI!=='cli')exit(1);
try{
    if(!extension_loaded('sodium'))throw new RuntimeException('PHP sodium required (Windows: php -d extension=sodium).');
    $root=dirname(__DIR__);$command=$argv[1]??'';
    $updateSource=json_decode((string)file_get_contents($root.'/deploy/update-source.json'),true,8,JSON_THROW_ON_ERROR);
    $private=$root.'/.release-private/signing.key';$public=$root.'/deploy/release-public.key';
    if($command==='init-key'){
        if(is_file($private)||is_file($public))throw new RuntimeException('Key already exists. Never replace a deployed signing key without a rotation plan.');
        if(!is_dir(dirname($private)))mkdir(dirname($private),0700,true);
        $pair=sodium_crypto_sign_keypair();
        $handle=fopen($private,'x');fwrite($handle,base64_encode(sodium_crypto_sign_secretkey($pair)).PHP_EOL);fclose($handle);chmod($private,0600);
        file_put_contents($public,base64_encode(sodium_crypto_sign_publickey($pair)).PHP_EOL);sodium_memzero($pair);
        echo "Signing key created. Back it up offline; never publish it.\n";exit;
    }
    if(!in_array($command,['publish','publish-blog'],true))throw new RuntimeException('Usage: php bin/publish-release.php init-key | publish | publish-blog');
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
        'min_from'=>'0.2.0','min_updater'=>3,'platform'=>'linuxmint22-amd64','php_min'=>'8.3.0','db_schema'=>1,
        'url'=>'https://github.com/ITALIANJOB97/Pulse/releases/download/v'.$version.'/pulse-'.$version.'.zip',
        'size'=>filesize($target),'sha256'=>hash_file('sha256',$target),'notes'=>$notes
    ];
    $json=json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
    $sig=sodium_crypto_sign_detached($json,$secret);
    if(!sodium_crypto_sign_verify_detached($sig,$json,$key))throw new RuntimeException('Signature self-check failed.');
    file_put_contents($dir.'/stable.json',$json);file_put_contents($dir.'/stable.json.sig',base64_encode($sig).PHP_EOL);
    // The installed v1 service stays pinned to the blog. Sign a second manifest,
    // without changing its trusted code or distributing the private signing key.
    $blogManifest=$manifest;
    $blogManifest['min_updater']=1;
    $blogManifest['url']=$updateSource['baseUrl'].'pulse-'.$version.'.zip';
    $blogJson=json_encode($blogManifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
    $blogSig=base64_encode(sodium_crypto_sign_detached($blogJson,$secret)).PHP_EOL;
    $updateManifest=[
        'schemaVersion'=>1,'program'=>$updateSource['program'],'version'=>$version,
        'downloadUrl'=>$blogManifest['url'],'sha256'=>$manifest['sha256'],'size'=>$manifest['size'],
        'publishedAt'=>$manifest['published_at'],'mandatory'=>false,'minimumSupportedVersion'=>'0.2.0',
        'releaseNotes'=>['features'=>[$notes['en']],'improvements'=>[],'fixes'=>[]],
        'pulse'=>['minUpdater'=>4,'phpMin'=>'8.3.0','dbSchema'=>1,'platform'=>'linuxmint22-amd64'],
    ];
    $updateJson=json_encode($updateManifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
    $updateSig=base64_encode(sodium_crypto_sign_detached($updateJson,$secret)).PHP_EOL;
    file_put_contents($dir.'/update.json',$updateJson);file_put_contents($dir.'/update.json.sig',$updateSig);
    sodium_memzero($secret);
    file_put_contents($dir.'/blog-stable.json',$blogJson);
    file_put_contents($dir.'/blog-stable.json.sig',$blogSig);
    $text="# PULSE ".$version."\n\nAggiornamenti pubblici da ITALIANJOB97/Pulse: nessun token necessario sul Mint.\n\n";
    $text.="Mint con servizio blog v1: continuare tramite il blog usando blog-stable.json e relativa firma (relay pubblico: docs/RELAY_BLOG.txt). Il pacchetto automatico non sostituisce il servizio root.\n\nPer il passaggio DIRETTO a GitHub, estrarre **pulse-".$version.".zip** sul Mint ed eseguire una sola volta:\n\n```bash\nsudo bash deploy/upgrade-mint.sh --channel github\n```\n\n";
    $text.="Il comando aggiorna il servizio a v4, crea il backup e conserva dati, utenti e foto. Non ripetere l’installer iniziale. I successivi rilasci firmati saranno scaricati senza credenziali e installati nella finestra notturna. Gli archivi Source code generati da GitHub non sono pacchetti installabili.\n\n";
    foreach(['en'=>'English','de'=>'Deutsch','fr'=>'Français'] as $lang=>$title)$text.='## '.$title."\n\n".$notes[$lang]."\n\n";
    file_put_contents($dir.'/release-notes.md',$text);
    // This separate guide archive contains only public help pages.
    $out=$root.'/dist/PULSE-Guida-Blog-'.$version.'.zip';$archive=new ZipArchive();
    if($archive->open($out,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Cannot build guide archive.');
    $guide=$root.'/public/guide';
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($guide,FilesystemIterator::SKIP_DOTS)) as $file){
        if($file->isLink()||!$file->isFile())throw new RuntimeException('Unexpected guide entry.');
        $relative=str_replace('\\','/',substr($file->getPathname(),strlen($guide)+1));
        $archive->addFile($file->getPathname(),'Pulse/guide/'.$relative);
    }
    $archive->close();file_put_contents($out.'.sha256',hash_file('sha256',$out).'  '.basename($out).PHP_EOL);
    $relay=$root.'/dist/PULSE-Relay-Hosting-'.$version.'.zip';$relayArchive=new ZipArchive();
    if($relayArchive->open($relay,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Cannot build hosting relay archive.');
    foreach(['blog-sync.php','blog-relay-config.example.php','blog-relay-cron.php','release-public.key','update-source.json'] as $file)$relayArchive->addFile($root.'/deploy/'.$file,'privata/'.$file);
    $relayArchive->addFile($root.'/docs/RELAY_BLOG.txt','RELAY_BLOG.txt');
    $relayArchive->close();file_put_contents($relay.'.sha256',hash_file('sha256',$relay).'  '.basename($relay).PHP_EOL);
    if($command==='publish-blog'){
        $out=$root.'/dist/PULSE-Blog-'.$version.'.zip';$archive=new ZipArchive();
        if($archive->open($out,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Cannot build blog archive.');
        $archive->addFile($target,'Pulse/pulse-'.$version.'.zip');
        $archive->addFromString('Pulse/stable.json',$blogJson);
        $archive->addFromString('Pulse/stable.json.sig',$blogSig);
        $archive->addFromString('Pulse/update.json',$updateJson);
        $archive->addFromString('Pulse/update.json.sig',$updateSig);
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($guide,FilesystemIterator::SKIP_DOTS)) as $file){
            if($file->isLink()||!$file->isFile())throw new RuntimeException('Unexpected guide entry.');
            $relative=str_replace('\\','/',substr($file->getPathname(),strlen($guide)+1));
            $archive->addFile($file->getPathname(),'Pulse/guide/'.$relative);
        }
        $archive->addFile($root.'/PUBBLICAZIONE_BLOG.txt','LEGGIMI_PRIMA.txt');
        $archive->close();file_put_contents($out.'.sha256',hash_file('sha256',$out).'  '.basename($out).PHP_EOL);
        echo "Blog upload archive: ".basename($out)." (program ZIP is accessible by URL after upload).\n";
    }
    echo "Signed public GitHub assets: dist/GitHub/v".$version."\nGuide: PULSE-Guida-Blog-".$version.".zip\nHosting relay: ".basename($relay)."\nNo runtime data or private signing key packaged.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
