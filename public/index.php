<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'; form-action 'self'");
header('Cache-Control: no-store');
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/';
try {
    if($path==='/health'){
        if(!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)){http_response_code(404);exit;}
        header('Content-Type: application/json');echo jsonValue(applicationHealth());exit;
    }
    if(is_file(updaterPath('maintenance'))){
        http_response_code(503);header('Retry-After: 10');
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta http-equiv="refresh" content="10"><title>PULSE</title><body style="background:#00559D;color:white;font:24px system-ui;padding:10vh"><h1>PULSE</h1><p>Update in progress · Aktualisierung läuft · Mise à jour en cours</p><p>Please wait · Bitte warten · Veuillez patienter</p></body></html>';exit;
    }
    boot();startSession();
    if(!installed()&&!in_array($path,['/setup','/action'],true))redirect('/setup');
    if($path==='/')redirect(installed()?'/admin':'/setup');
    if($path==='/action'){
        if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit;}
        require PULSE_ROOT.'/app/actions.php';
        $return=installed()?'/admin':'/setup';
        try{
            $return=safeReturn(inputText('return',200,$return));
            handleAction(inputText('action',30));
        }catch(DomainException $ex){flash($ex->getMessage(),'error');}
        catch(Throwable $ex){error_log('[PULSE] '.$ex);flash(t('operation_failed'),'error');}
        redirect($return);
    }
    if($path==='/setup'){if(installed())redirect('/login');require PULSE_ROOT.'/app/views.php';renderSetup();exit;}
    if($path==='/login'){if(currentAdmin())redirect('/admin');require PULSE_ROOT.'/app/views.php';renderLogin();exit;}
    if($path==='/display'&&isset($_GET['key'])){
        $key=$_GET['key'];
        if(!is_string($key)||strlen($key)!==64||!hash_equals(setting('screen_key',''),$key)){http_response_code(403);exit('Forbidden');}
        setcookie('pulse_screen',$key,['expires'=>time()+31536000,'path'=>'/','secure'=>isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Strict']);
        redirect('/display');
    }
    if(in_array($path,['/display','/api/screen','/media'],true)&&!screenAllowed()){
        if($path==='/display')redirect('/login');
        http_response_code(401);exit;
    }
    if($path==='/api/screen'){
        $data=screenData();$data['labels']=dictionary($data['language']);session_write_close();
        header('Content-Type: application/json; charset=utf-8');echo jsonValue($data);exit;
    }
    if($path==='/media'){
        $file=mediaFile((int)($_GET['id']??0));if(!$file||!is_file($file)){http_response_code(404);exit;}
        session_write_close();header('Content-Type: image/jpeg');header('Cache-Control: private, max-age=86400');
        header('Content-Length: '.filesize($file));readfile($file);exit;
    }
    if($path==='/download-backup'){
        requireAdmin();$name=$_GET['file']??'';
        if(!is_string($name)||!preg_match('/^(daily|weekly|manual|preupdate)-\d{8}-\d{6}-[a-f0-9]{6}\.zip$/D',$name)){http_response_code(404);exit;}
        $backupFile=str_starts_with($name,'preupdate-')&&is_file(updaterPath('backups/'.$name))?updaterPath('backups/'.$name):storage('backups/'.$name);
        if(!is_file($backupFile)){http_response_code(404);exit;}
        session_write_close();header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="'.$name.'"');
        header('Content-Length: '.filesize($backupFile));readfile($backupFile);exit;
    }
    if($path==='/archive'){
        requireAdmin();$kind=$_GET['kind']??'';$period=$_GET['period']??'';
        if(!is_string($kind)||!is_string($period)){http_response_code(400);exit;}
        $q=db()->prepare('SELECT payload FROM snapshots WHERE kind=? AND period=?');$q->execute([$kind,$period]);$row=$q->fetch();
        if(!$row){http_response_code(404);exit;}
        require PULSE_ROOT.'/app/views.php';renderDisplay(json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR),true);exit;
    }
    require PULSE_ROOT.'/app/views.php';
    if($path==='/display'){renderDisplay(screenData());exit;}
    if($path==='/admin'){requireAdmin();renderAdmin();exit;}
    http_response_code(404);echo 'Not found';
}catch(Throwable $ex){
    error_log('[PULSE] '.$ex);http_response_code(503);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>PULSE — System check</title><link rel="stylesheet" href="/assets/app.css"><main class="auth-card"><h1>PULSE — System check</h1><p>Follow INSTALLAZIONE_RASPBERRY.txt to complete the server configuration.</p><ul>';
    foreach(requirements() as $name=>$ok)echo '<li>'.e($name).' — '.($ok?'OK':'Missing').'</li>';
    echo '</ul><p>Details are recorded in the PHP server log.</p></main></html>';
}

