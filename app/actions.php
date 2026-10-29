<?php
declare(strict_types=1);
function handleAction(string $action): void {
    verifyCsrf();
    if ($action === 'language') {
        $lang = inputText('language',2);
        if (!in_array($lang,['en','de','fr'],true)) throw new DomainException(t('invalid_input'));
        $_SESSION['language'] = $lang;
        redirect(safeReturn(inputText('return',200,'/admin')));
    }
    if ($action === 'setup') {
        if (installed()) redirect('/login');
        if (inputText('password',72) !== inputText('password_repeat',72)) throw new DomainException(t('password_mismatch'));
        $lang = inputText('language',2,'de');
        if (!in_array($lang,['en','de','fr'],true)) throw new DomainException(t('invalid_input'));
        initialize([
            'token'=>inputText('token',64),'name'=>inputText('name',100),'email'=>inputText('email',200),
            'password'=>inputText('password',72),'store'=>inputText('store',100),'language'=>$lang,
            'duration'=>(int)inputNumber('duration',10,120,20),
        ]);
        $_SESSION['language']=$lang;
        login(inputText('email',200),inputText('password',72));
        redirect('/admin');
    }
    if ($action === 'login') {
        if (!login(inputText('email',200),inputText('password',72))) throw new DomainException(t('login_error'));
        redirect('/admin');
    }
    requireAdmin();
    if ($action === 'logout') { $_SESSION=[]; session_regenerate_id(true); redirect('/login'); }
    if ($action === 'employee_save') {
        $name=inputText('name',100); $role=inputText('role',20);
        if ($name==='' || !in_array($role,['manager','deputy','supervisor','member'],true)) throw new DomainException(t('invalid_input'));
        $id=(int)inputNumber('id',0,PHP_INT_MAX);
        if ($id) employeeId($id);
        $photo=uploadImage('photo','employee',$name);
        if ($id) {
            $q=db()->prepare('UPDATE employees SET name=?,role=?,active=?'.($photo?',photo=?':'').' WHERE id=?');
            $args=[$name,$role,isset($_POST['active'])?1:0]; if($photo) $args[]=$photo; $args[]=$id; $q->execute($args);
        } else {
            db()->prepare('INSERT INTO employees(name,role,photo,active) VALUES(?,?,?,?)')->execute([$name,$role,$photo,isset($_POST['active'])?1:0]);
        }
        touchRevision();
    } elseif ($action==='employee_delete') {
        db()->prepare('DELETE FROM employees WHERE id=?')->execute([employeeId($_POST['id']??0)]); touchRevision();
    } elseif ($action==='gallery_add') {
        if(!uploadImage('image','gallery',inputText('label',200))) throw new DomainException(t('upload_error')); touchRevision();
    } elseif ($action==='gallery_delete') {
        // Keep immutable files for historical snapshots and backups.
        db()->prepare("UPDATE media SET kind='archived' WHERE id=? AND kind='gallery'")->execute([(int)inputNumber('id',1,PHP_INT_MAX)]);
        touchRevision();
    } elseif ($action==='settings') {
        $name=inputText('store',100); $lang=inputText('language',2); $url=setting('update_url','');
        if($name==='' || !in_array($lang,['en','de','fr'],true)) throw new DomainException(t('invalid_input'));
        $pages=[]; foreach(moduleKeys() as $key) $pages[$key]=isset($_POST['pages'][$key]);
        if(!in_array(true,$pages,true)) throw new DomainException(t('invalid_input'));
        $duration=(int)inputNumber('duration',10,120,20);
        $hour=(int)inputNumber('update_hour',0,23,3);
        db()->beginTransaction();
        try {
            setSetting('store_name',$name); setSetting('language',$lang); setSetting('duration',$duration);
            setSetting('effects',isset($_POST['effects'])); setSetting('pages',$pages);
            setSetting('show_sunday',isset($_POST['show_sunday']));
            setSetting('seasonal_decorations',isset($_POST['seasonal_decorations']));
            setSetting('update_auto',isset($_POST['update_auto'])); setSetting('update_hour',$hour);
            if($url!==setting('update_url','')) setSetting('update_state',[]);
            setSetting('update_url',$url); touchRevision(); db()->commit();
        } catch(Throwable $ex) {db()->rollBack();throw $ex;}
    } elseif ($action==='screen_key') {
        setSetting('screen_key',bin2hex(random_bytes(32))); flash(t('key_changed')); return;
    } elseif ($action==='admin_add') {
        $name=inputText('name',100); $email=inputText('email',200); $password=inputText('password',72);
        if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||!passwordValid($password)) throw new DomainException(t('invalid_input'));
        try {
            db()->prepare('INSERT INTO admins(name,email,password_hash,created_at) VALUES(?,?,?,?)')->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),gmdate('c')]);
        } catch(PDOException $ex) {if($ex->getCode()==='23000') throw new DomainException(t('invalid_input'));throw $ex;}
    } elseif ($action==='admin_delete') {
        $id=(int)inputNumber('id',1,PHP_INT_MAX); db()->exec('BEGIN IMMEDIATE');
        try {
            if($id===(int)currentAdmin()['id']||(int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn()<=1) throw new DomainException(t('last_admin'));
            db()->prepare('DELETE FROM admins WHERE id=?')->execute([$id]); db()->exec('COMMIT');
        } catch(Throwable $ex) {db()->exec('ROLLBACK');throw $ex;}
    } elseif ($action==='password_change') {
        $id=currentAdmin()['id'];
        $q=db()->prepare('SELECT password_hash FROM admins WHERE id=?');$q->execute([$id]);
        $password=inputText('password',72);
        if(!password_verify(inputText('current_password',72),$q->fetchColumn())||!passwordValid($password)) throw new DomainException(t('invalid_input'));
        if($password!==inputText('password_repeat',72)) throw new DomainException(t('password_mismatch'));
        db()->prepare('UPDATE admins SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
        session_regenerate_id(true);
    } elseif ($action==='backup') {
        createBackup();flash(t('backup_done'));return;
    } elseif ($action==='check_updates') {
        $state=checkUpdates();flash(t($state['error']??(!empty($state['queued'])?'update_queued':'check_done')),isset($state['error'])?'error':'success');return;
    } elseif (in_array($action,['install_update','skip_update','cancel_update'],true)) {
        updateAction($action);flash(t('update_queued'));return;
    } elseif ($action==='postpone_update') {
        setSetting('update_postpone_until',time()+86400);setSetting('update_install',[]); flash(t('update_postponed')); return;
    } elseif ($action==='retry_update') {
        setSetting('update_retry',bin2hex(random_bytes(12))); setSetting('update_requested',bin2hex(random_bytes(12))); flash(t('update_queued')); return;
    } elseif ($action==='content') {
        saveContentForm();
    } else {throw new DomainException(t('invalid_input'));}
    flash(t('saved'));
}
function safeReturn(string $path): string {
    return preg_match('~^/(?:admin(?:\?[^#\r\n]*)?|setup|login)$~D',$path)?$path:'/admin';
}
function saveContentForm(): void {
    $module=inputText('module',20);$period=inputText('period',20);
    if(!in_array($module,moduleKeys(),true)||!validPeriod($module,$period)) throw new DomainException(t('invalid_input'));
    $payload=[];
    if($module==='spotlight') {
        $payload=['employee_id'=>employeeId($_POST['employee_id']??null,true),'message'=>translationsFromPost('message')];
    } elseif($module==='podium') {
        $counts=$_POST['counts']??[];
        if(!is_array($counts)) throw new DomainException(t('invalid_input'));
        foreach($counts as $id=>$count) $payload[]=['employee_id'=>employeeId($id),'count'=>numberValue(is_string($count)?str_replace(',','.',trim($count)):$count,0,1000000)];
        // Retain recorded contributions when a person is later made inactive or deleted.
        foreach(content('podium',$period) as $row)if(!array_key_exists((string)$row['employee_id'],$counts))$payload[]=$row;
    } elseif($module==='roles') {
        for($i=0;$i<8;$i++){
            $id=employeeId($_POST['employee_'.$i]??null,true);$title=translationsFromPost('role_'.$i,120);
            if($id) $payload[]=['employee_id'=>$id,'title'=>$title];
        }
    } elseif($module==='transfers') {
        $previous=content('transfers',$period);
        $days=[];for($i=0;$i<7;$i++)$days[]=($i===6&&!setting('show_sunday',true))?(int)($previous['days'][6]??0):(int)inputNumber('day_'.$i,0,100000);
        $payload=['days'=>$days,'pending'=>(int)inputNumber('pending',0,100000)];
    } elseif($module==='targets') {
        $payload=[
            'service_target'=>optionalNumber('service_target',0,100000000),
            'service_total'=>optionalNumber('service_total',0,100000000),
            'nps'=>optionalNumber('nps',-100,100),'nps_target'=>optionalNumber('nps_target',-100,100),'nps_previous'=>optionalNumber('nps_previous',-100,100),
            'waiting'=>optionalNumber('waiting',0,86400),'waiting_green'=>inputNumber('waiting_green',0,86400,180),'waiting_amber'=>inputNumber('waiting_amber',0,86400,300),
            'quality'=>optionalNumber('quality',0,100),'quality_target'=>optionalNumber('quality_target',0,100),'quality_previous'=>optionalNumber('quality_previous',0,100),
        ];
        if($payload['waiting_amber']<$payload['waiting_green'])throw new DomainException(t('invalid_input'));
    } elseif($module==='motto') {
        $payload=['text'=>translationsFromPost('motto',280),'author'=>inputText('motto_author',80)];
    } elseif($module==='daily') {
        for($i=0;$i<6;$i++)$payload[]=translationsFromPost('line_'.$i,180);
    } elseif($module==='tasks') {
        foreach(['en','de','fr'] as $lang)$payload[$lang]=array_slice(array_map('trim',preg_split('/\R/u',inputText('tasks_'.$lang,5000))),0,30);
    } else {throw new DomainException(t('invalid_input'));}
    saveContent($module,$period,$payload);
}

