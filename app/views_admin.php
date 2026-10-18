<?php
declare(strict_types=1);
function adminOverview(): void {
    echo '<section class="welcome-banner"><span class="eyebrow">PULSE '.e(version()).'</span><h2>'.e(t('ready')).'</h2><p>'.e(t('ready_detail')).'</p><a class="button primary" href="/display" target="_blank" rel="noopener">'.e(t('open_tv')).' ↗</a></section><p class="muted">'.e(t('no_fake_data')).'</p><div class="module-grid">';
    foreach(moduleKeys() as $i=>$module)echo '<a class="module-card" href="/admin?tab='.($module==='team'?'employees':($module==='gallery'?'gallery':'content&module='.$module)).'"><span class="module-index">'.str_pad((string)($i+1),2,'0',STR_PAD_LEFT).'</span><h3>'.e(t($module)).'</h3><span>↗</span></a>';
    echo '</div>';
}
function adminEmployees(): void {
    $people=employees();$edit=null;
    foreach($people as $p)if($p['id']==($_GET['edit']??0))$edit=$p;
    echo '<div class="split"><section class="panel"><h2>'.e(t($edit?'edit':'new_employee')).'</h2>';
    formStart('employee_save','/admin?tab=employees',true);hidden('id',$edit['id']??0);
    field('name',t('name'),$edit['name']??'','text','required maxlength="100"');
    echo '<label class="field"><span>'.e(t('role')).'</span><select name="role">';
    foreach(['manager','deputy','supervisor','member'] as $role)echo '<option value="'.$role.'"'.(($edit['role']??'member')===$role?' selected':'').'>'.e(t($role)).'</option>';
    echo '</select></label><label class="check"><input type="checkbox" name="active"'.(($edit['active']??1)?' checked':'').'>'.e(t('active')).'</label>';
    field('photo',t('photo'),'','file','accept="image/jpeg,image/png,image/webp"');
    if(!empty($edit['photo']))echo '<img class="employee-preview" src="/media?id='.(int)$edit['photo'].'" alt="'.e($edit['name']).'">';
    echo '<p class="help">'.e(t('image_hint')).'</p>';saveButton($edit?'save':'add');echo '</section><section>';
    if(!$people)echo '<div class="empty">'.e(t('no_employees')).'</div>';
    foreach($people as $p){
        echo '<article class="person-row">';
        if($p['photo'])echo '<img class="avatar" src="/media?id='.(int)$p['photo'].'" alt="">';
        else echo '<div class="avatar initials">'.e(mb_substr($p['name'],0,1)).'</div>';
        echo '<div class="person-name"><strong>'.e($p['name']).'</strong><span>'.e(t($p['role'])).' · '.e(t($p['active']?'on':'off')).'</span></div><a class="button subtle" href="/admin?tab=employees&amp;edit='.$p['id'].'">'.e(t('edit')).'</a>';
        deleteButton('employee_delete',(int)$p['id'],'/admin?tab=employees');echo '</article>';
    }
    echo '</section></div>';
}
function adminGallery(): void {
    echo '<section class="panel"><h2>'.e(t('upload')).'</h2>';
    formStart('gallery_add','/admin?tab=gallery',true);echo '<div class="form-grid">';
    field('label',t('label'),'','text','maxlength="200"');field('image',t('photo'),'','file','accept="image/jpeg,image/png,image/webp" required');
    echo '</div><p class="help">'.e(t('image_hint')).'</p>';saveButton('upload');echo '</section><div class="gallery-grid">';
    $photos=db()->query("SELECT * FROM media WHERE kind='gallery' ORDER BY id DESC")->fetchAll();
    if(!$photos)echo '<div class="empty">'.e(t('no_images')).'</div>';
    foreach($photos as $photo){echo '<article class="image-card"><img src="/media?id='.$photo['id'].'" alt="'.e($photo['label']).'"><div><strong>'.e($photo['label']).'</strong>';deleteButton('gallery_delete',(int)$photo['id'],'/admin?tab=gallery');echo '</div></article>';}
    echo '</div>';
}
function adminSettings(): void {
    echo '<section class="panel">';formStart('settings','/admin?tab=settings');echo '<div class="form-grid">';
    field('store',t('store'),setting('store_name'),'text','required maxlength="100"');
    languageSelect(setting('language','de'));field('duration',t('duration'),setting('duration',20),'number','required min="10" max="120"');
    echo '<label class="check"><input type="checkbox" name="effects"'.(setting('effects',true)?' checked':'').'>'.e(t('effects')).'</label></div><h3>'.e(t('enabled_pages')).'</h3><div class="checkbox-grid">';
    foreach(moduleKeys() as $key)echo '<label class="check"><input type="checkbox" name="pages['.$key.']"'.(setting('pages',[])[$key]??true?' checked':'').'>'.e(t($key)).'</label>';
    echo '</div><h3>'.e(t('schedule_settings')).'</h3><label class="check"><input type="checkbox" name="show_sunday"'.(setting('show_sunday',true)?' checked':'').'>'.e(t('show_sunday')).'</label><p class="help">'.e(t('sunday_preserved')).'</p><h3>'.e(t('updates')).'</h3><label class="check"><input type="checkbox" name="update_auto"'.(setting('update_auto',true)?' checked':'').'>'.e(t('update_auto')).'</label>';
    field('update_hour',t('update_hour'),setting('update_hour',3),'number','required min="0" max="23" step="1"');
    echo '<p class="help">'.e(t('update_window_hint')).'</p>';
    if(updaterManaged())echo '<p class="help">'.e(t(updaterSourceLabel())).'</p>';
    else field('update_url',t('update_source'),setting('update_url',''),'url','maxlength="2000" placeholder="https://…"');
    if(!updaterManaged())echo '<p class="help">'.e(t('update_source_hint')).'</p>';saveButton();echo '</section><section class="panel"><h2>'.e(t('screen_access')).'</h2><p class="muted">'.e(t('screen_hint')).'</p>';
    echo '<a class="button" href="/display?key='.e(setting('screen_key')).'" target="_blank" rel="noopener">'.e(t('open_tv')).' ↗</a>';
    formStart('screen_key','/admin?tab=settings');echo '<button class="subtle" data-confirm="'.e(t('replace_key_confirm')).'">'.e(t('reset_screen_key')).'</button></form></section>';
}
function adminAccounts(): void {
    echo '<div class="split"><section class="panel"><h2>'.e(t('new_admin')).'</h2>';formStart('admin_add','/admin?tab=administrators');
    field('name',t('name'),'','text','required maxlength="100"');field('email',t('email'),'','email','required maxlength="200"');
    field('password',t('password'),'','password','required minlength="12" maxlength="72" autocomplete="new-password"');
    echo '<p class="help">'.e(t('password_hint')).'</p>';saveButton('create');
    echo '</section><section>';
    foreach(db()->query('SELECT id,name,email FROM admins ORDER BY name')->fetchAll() as $user){
        echo '<article class="person-row"><div class="person-name"><strong>'.e($user['name']).'</strong><span>'.e($user['email']).'</span></div>';
        if((int)$user['id']!==(int)currentAdmin()['id'])deleteButton('admin_delete',(int)$user['id'],'/admin?tab=administrators');
        echo '</article>';
    }
    echo '</section></div><section class="panel"><h2>'.e(t('password_change')).'</h2>';formStart('password_change','/admin?tab=administrators');echo '<div class="form-grid">';
    field('current_password',t('current_password'),'','password','required autocomplete="current-password" maxlength="72"');
    field('password',t('password'),'','password','required minlength="12" maxlength="72" autocomplete="new-password"');
    field('password_repeat',t('password_repeat'),'','password','required minlength="12" maxlength="72" autocomplete="new-password"');
    echo '</div>';saveButton();echo '</section>';
}
function adminUpdates(): void {
    $managed=updaterManaged();$state=$managed?managedUpdateState():setting('update_state',[]);$public=publicUpdateState();
    echo '<section class="welcome-banner"><span class="eyebrow">'.e(t('current_version')).'</span><h2>v'.e(version()).'</h2><p>'.e(t($managed?'update_detail_auto':'update_detail')).'</p></section><section class="panel">';
    $status=$managed?'update_state_'.($state['phase']??'waiting'):(!setting('update_url','')?'no_update_source':(!empty($state['error'])?'check_failed':($public['available']?'update_available':(isset($state['checked_at'])?'up_to_date':'not_checked'))));
    echo '<h2>'.e(t($status)).'</h2>';
    if($public['available']){
        echo '<p class="release-version">v'.e($public['version']).'</p><p class="preserve">'.e($state['release']['notes'][language()]?:$state['release']['notes']['en']).'</p>';
    }
    if($managed){
        echo '<p>'.e(t(setting('update_auto',true)?'update_auto_on':'update_auto_off')).'</p>';
        if(!empty($state['scheduled_for'])&&setting('update_auto',true)){
            $date=(new DateTimeImmutable($state['scheduled_for']))->setTimezone(new DateTimeZone('Europe/Zurich'));
            echo '<p><strong>'.e(t('update_scheduled')).': '.e($date->format('d.m.Y H:i')).' · Europe/Zurich</strong></p>';
        }
        if(!empty($state['error']))echo '<div class="notice error">'.e(t('update_error_help')).' <code>'.e($state['error']).'</code></div>';
        echo '<p class="help">'.e(t('update_backup_hint')).'</p><a href="/admin?tab=settings">'.e(t('settings')).' →</a>';
    }
    echo '<p class="muted">'.e(t('last_check')).': '.e($state['checked_at']??'—').'</p>';
    formStart('check_updates','/admin?tab=updates');saveButton('check_now');
    if($managed&&$public['available']){formStart('postpone_update','/admin?tab=updates');saveButton('postpone_update');}
    if($managed&&in_array($state['phase']??'',['failed','rolled_back'],true)){formStart('retry_update','/admin?tab=updates');saveButton('retry_update');}
    echo '</section>';
    $event=setting('version_event');
    if($event)echo '<div class="notice">'.e(t('installed_notice')).' · v'.e($event['version']).' · '.e($event['at']).'</div>';
}
function adminBackups(): void {
    echo '<section class="panel"><h2>'.e(t('backups')).'</h2><p class="muted">'.e(t('backup_hint')).'</p>';
    formStart('backup','/admin?tab=backups');saveButton('backup_now');
    $files=backupList();
    if(!$files)echo '<div class="empty">'.e(t('no_backups')).'</div>';
    foreach($files as $file)echo '<div class="list-row"><div><strong>'.e($file['name']).'</strong><small>'.number_format($file['size']/1024/1024,2).' MB</small></div><a class="button" href="/download-backup?file='.rawurlencode($file['name']).'">'.e(t('download')).'</a></div>';
    echo '</section><section class="panel"><h2>'.e(t('snapshots')).'</h2>';
    $rows=db()->query('SELECT kind,period,captured_at FROM snapshots ORDER BY captured_at DESC LIMIT 120')->fetchAll();
    if(!$rows)echo '<p class="muted">'.e(t('no_snapshots')).'</p>';
    foreach($rows as $row)echo '<div class="list-row"><div><strong>'.e($row['period']).' · '.e(t($row['kind'].'_label')).'</strong><small>'.e($row['captured_at']).'</small></div><a class="button" href="/archive?kind='.e($row['kind']).'&amp;period='.e($row['period']).'" target="_blank" rel="noopener">'.e(t('preview')).'</a></div>';
    echo '</section>';
}
function adminContent(): void {
    $editable=['spotlight','podium','roles','transfers','targets','daily','tasks'];
    $module=is_string($_GET['module']??null)?$_GET['module']:'spotlight';
    if(!in_array($module,$editable,true))$module='spotlight';
    $period=is_string($_GET['period']??null)?$_GET['period']:periodFor($module);
    if(!validPeriod($module,$period))$period=periodFor($module);
    $data=content($module,$period);$return='/admin?tab=content&module='.$module.'&period='.$period;
    echo '<nav class="pills">';
    foreach($editable as $key)echo '<a href="/admin?tab=content&amp;module='.$key.'"'.($key===$module?' aria-current="page"':'').'>'.e(t($key)).'</a>';
    echo '</nav><section class="panel"><div class="section-heading"><h2>'.e(t($module)).'</h2>';
    if($module!=='tasks'){
        echo '<form method="get" class="period-form">';hidden('tab','content');hidden('module',$module);
        field('period',t('period'),$period,match($module){'daily'=>'date','transfers'=>'week',default=>'month'},'required');
        echo '<button>'.e(t('preview')).'</button></form>';
    }
    echo '</div>';formStart('content',$return);hidden('module',$module);hidden('period',$period);
    if($module==='spotlight'){
        employeeSelect('employee_id',$data['employee_id']??null);
        echo '<div class="form-grid thirds">';foreach(['de','en','fr'] as $lang)area('message_'.$lang,t('message').' · '.strtoupper($lang),$data['message'][$lang]??'');echo '</div>';
    }elseif($module==='podium'){
        $counts=[];foreach($data as $row)$counts[$row['employee_id']]=$row['count'];
        echo '<div class="form-grid">';foreach(employees(true) as $person)field('counts['.$person['id'].']',$person['name'].' · '.t('count'),$counts[$person['id']]??0,'number','required min="0" max="1000000" step="1"');echo '</div>';
        if(!employees(true))echo '<p>'.e(t('no_employees')).'</p>';
    }elseif($module==='roles'){
        for($i=0;$i<8;$i++){
            echo '<div class="role-row"><span class="row-number">'.str_pad((string)($i+1),2,'0',STR_PAD_LEFT).'</span>';employeeSelect('employee_'.$i,$data[$i]['employee_id']??null);
            foreach(['de','en','fr'] as $lang)field('role_'.$i.'_'.$lang,t('assignment').' · '.strtoupper($lang),$data[$i]['title'][$lang]??'','text','maxlength="120"');echo '</div>';
        }
    }elseif($module==='transfers'){
        echo '<div class="form-grid">';
        $monday=(new DateTimeImmutable())->setISODate((int)substr($period,0,4),(int)substr($period,6,2));
        $fmt=new IntlDateFormatter(language(),IntlDateFormatter::NONE,IntlDateFormatter::NONE,'Europe/Zurich',null,'EEEE');
        for($i=0;$i<(setting('show_sunday',true)?7:6);$i++)field('day_'.$i,$fmt->format($monday->modify('+'.$i.' days')),$data['days'][$i]??0,'number','required min="0" max="100000"');
        if(!setting('show_sunday',true))echo '<p class="help">'.e(t('sunday_preserved')).'</p>';
        field('pending',t('pending'),$data['pending']??0,'number','required min="0" max="100000"');echo '</div>';
    }elseif($module==='targets'){
        echo '<div class="form-grid">';
        field('service_target',t('service_target'),$data['service_target']??'','number','min="0" max="100000000" step="1"');
        field('service_total',t('service_total'),$data['service_total']??'','number','min="0" max="100000000" step="1"');
        echo '</div><p class="help">'.e(t('service_total_hint')).'</p>';
        echo '<div class="form-grid thirds">';
        foreach(['nps','nps_target','nps_previous','waiting','waiting_green','waiting_amber','quality','quality_target','quality_previous'] as $key){
            $min=str_starts_with($key,'nps')?-100:0;$max=str_starts_with($key,'waiting')?86400:100;
            $default=match($key){'waiting_green'=>180,'waiting_amber'=>300,default=>''};
            field($key,t($key),$data[$key]??$default,'number','min="'.$min.'" max="'.$max.'" step="0.1"');
        }
        echo '</div><p class="help">'.e(t('previous_hint')).'</p>';
    }elseif($module==='daily'){
        for($i=0;$i<6;$i++){echo '<div class="form-grid thirds">';foreach(['de','en','fr'] as $lang)field('line_'.$i.'_'.$lang,t('line').' '.($i+1).' · '.strtoupper($lang),$data[$i][$lang]??'','text','maxlength="180"');echo '</div>';}
    }elseif($module==='tasks'){
        echo '<p class="muted">'.e(t('one_per_line')).'</p><div class="form-grid thirds">';
        foreach(['de','en','fr'] as $lang)area('tasks_'.$lang,strtoupper($lang),implode("\n",$data[$lang]??[]),12);echo '</div>';
    }
    saveButton();echo '</section>';
}
