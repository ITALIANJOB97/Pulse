<?php
declare(strict_types=1);
function pageStart(string $title,string $class=''): void {
    echo '<!doctype html><html lang="'.e(language()).'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' · PULSE</title><link rel="stylesheet" href="/assets/app.css?v='.e(version()).'"></head><body class="'.e($class).'">';
}
function authorCredit(): string { return '<a class="author-credit" href="https://www.luigipranzo.com/" target="_blank" rel="noopener noreferrer">By Luigi Juan Pranzo</a>'; }
function pageEnd(): void { echo '<footer class="app-footer">'.authorCredit().'</footer><script src="/assets/admin.js?v='.e(version()).'" defer></script></body></html>'; }
function brand(): void { echo '<a class="brand" href="/">PULSE<span></span></a>'; }
function languagePicker(): void {
    echo '<form class="languages" method="post" action="/action">'.csrf().'<input type="hidden" name="action" value="language"><input type="hidden" name="return" value="'.e($_SERVER['REQUEST_URI']).'">';
    foreach(['de','en','fr'] as $lang) echo '<button name="language" value="'.$lang.'" class="'.($lang===language()?'selected':'').'" aria-label="'.strtoupper($lang).'">'.strtoupper($lang).'</button>';
    echo '</form>';
}
function showFlash(): void {
    if(!isset($_SESSION['flash']))return;
    $f=$_SESSION['flash'];unset($_SESSION['flash']);
    echo '<div class="notice '.e($f['type']).'" role="'.($f['type']==='error'?'alert':'status').'">'.e($f['message']).'</div>';
}
function formStart(string $action,string $return,bool $upload=false): void {
    echo '<form method="post" action="/action"'.($upload?' enctype="multipart/form-data"':'').'>'.csrf().'<input type="hidden" name="action" value="'.e($action).'"><input type="hidden" name="return" value="'.e($return).'">';
}
function hidden(string $key,mixed $value): void {echo '<input type="hidden" name="'.e($key).'" value="'.e($value).'">';}
function field(string $key,string $label,mixed $value='',string $type='text',string $attrs=''): void {
    echo '<label class="field"><span>'.e($label).'</span><input type="'.e($type).'" name="'.e($key).'" value="'.e($value??'').'" '.$attrs.'></label>';
}
function area(string $key,string $label,mixed $value='',int $rows=3): void {
    echo '<label class="field"><span>'.e($label).'</span><textarea name="'.e($key).'" rows="'.$rows.'">'.e($value).'</textarea></label>';
}
function languageSelect(string $value): void {
    echo '<label class="field"><span>'.e(t('language')).'</span><select name="language">';
    foreach(['de'=>'Deutsch','en'=>'English','fr'=>'Français'] as $key=>$label)echo '<option value="'.$key.'"'.($key===$value?' selected':'').'>'.$label.'</option>';
    echo '</select></label>';
}
function employeeSelect(string $key,mixed $selected=null): void {
    echo '<label class="field"><span>'.e(t('employee')).'</span><select name="'.e($key).'"><option value="">— '.e(t('choose_employee')).' —</option>';
    foreach(employees() as $person)echo '<option value="'.$person['id'].'"'.((string)$person['id']===(string)$selected?' selected':'').'>'.e($person['name']).'</option>';
    echo '</select></label>';
}
function saveButton(string $label='save'): void {echo '<div class="form-actions"><button class="primary">'.e(t($label)).'</button></div></form>';}
function deleteButton(string $action,int $id,string $return): void {
    formStart($action,$return);hidden('id',$id);
    echo '<button class="danger subtle" data-confirm="'.e(t('confirm_delete')).'">'.e(t('delete')).'</button></form>';
}
function renderSetup(): void {
    pageStart(t('setup'),'auth-page');
    echo '<header class="auth-top">';brand();languagePicker();echo '</header><main class="auth-layout"><section class="auth-intro"><span class="eyebrow">GALAXUS · DIETIKON</span><h1>'.e(t('setup_intro')).'</h1><p>'.e(t('setup_detail')).'</p><div class="orbit-art" aria-hidden="true"><span>P.</span></div></section><section class="auth-card"><div class="eyebrow">PULSE / '.e(t('setup')).'</div>';
    showFlash();
    formStart('setup','/setup');echo '<div class="wizard" data-wizard><div class="wizard-progress" aria-label="'.e(t('setup')).'"><span class="current">01</span><span>02</span><span>03</span></div>';
    echo '<fieldset data-step><legend>'.e(t('requirements')).'</legend><ul class="checks">';
    foreach(requirements() as $label=>$ok)echo '<li><span>✓</span>'.e($label).'</li>';
    echo '</ul>';field('token',t('setup_token'),'','text','required autocomplete="off" maxlength="64"');
    echo '<p class="help">'.e(t('setup_token_hint')).'</p></fieldset>';
    echo '<fieldset data-step><legend>'.e(t('store')).'</legend>';
    field('store',t('store'),'PULSE · Dietikon','text','required maxlength="100"');
    languageSelect(language());field('duration',t('duration'),20,'number','required min="10" max="120"');
    echo '</fieldset><fieldset data-step><legend>'.e(t('administrator')).'</legend>';
    field('name',t('name'),'','text','required maxlength="100" autocomplete="name"');
    field('email',t('email'),'','email','required maxlength="200" autocomplete="username"');
    field('password',t('password'),'','password','required minlength="12" maxlength="72" autocomplete="new-password"');
    field('password_repeat',t('password_repeat'),'','password','required minlength="12" maxlength="72" autocomplete="new-password"');
    echo '<p class="help">'.e(t('password_hint')).'</p></fieldset><div class="wizard-actions"><button type="button" data-back hidden>'.e(t('back')).'</button><button type="button" class="primary" data-next hidden>'.e(t('next')).'</button><button class="primary" data-finish>'.e(t('finish_setup')).'</button></div></div></form><p class="help">'.e(t('setup_only')).'</p></section></main>';
    pageEnd();
}
function renderLogin(): void {
    pageStart(t('login'),'auth-page');echo '<header class="auth-top">';brand();languagePicker();
    echo '</header><main class="auth-layout"><section class="auth-intro"><span class="eyebrow">GALAXUS · DIETIKON</span><h1>'.e(t('welcome')).'</h1><p>'.e(t('copyright')).'</p><div class="orbit-art" aria-hidden="true"><span>P.</span></div></section><section class="auth-card"><div class="eyebrow">PULSE / '.e(t('administrator')).'</div><h2>'.e(t('login')).'</h2><p class="muted">'.e(t('login_detail')).'</p>';
    showFlash();formStart('login','/login');
    field('email',t('email'),'','email','required autocomplete="username" maxlength="200"');
    field('password',t('password'),'','password','required autocomplete="current-password" maxlength="72"');
    saveButton('login');echo '</section></main>';pageEnd();
}
function renderDisplay(array $data,bool $archive=false): void {
    $data['labels']=dictionary($data['language']);$data['archive']=$archive;
    echo '<!doctype html><html lang="'.e($data['language']).'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($data['store']).'</title><link rel="stylesheet" href="/assets/screen.css?v='.e(version()).'"></head><body>';
    echo '<div id="screen"><header><div class="brand">PULSE<span></span></div><div class="store" id="store"></div><div class="clock" id="clock"></div></header><main id="slide"></main><footer><span id="period"></span><nav id="pages" aria-label="Pages"></nav><div class="screen-footer-right"><span id="page-count"></span>'.authorCredit().'</div></footer><div class="timeline"><div id="timeline"></div></div><aside id="offline" role="status" hidden></aside></div>';
    echo '<script id="screen-data" type="application/json">'.json_encode($data,JSON_THROW_ON_ERROR|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script><script src="/assets/screen.js?v='.e(version()).'" defer></script></body></html>';
}
function renderAdmin(): void {
    require __DIR__.'/views_admin.php';
    $tabs=['overview','content','employees','gallery','settings','administrators','backups','updates'];
    $tab=is_string($_GET['tab']??null)?$_GET['tab']:'overview';if(!in_array($tab,$tabs,true))$tab='overview';
    pageStart(t($tab),'admin-page');
    echo '<div data-update-background data-csrf="'.e($_SESSION['csrf']).'" hidden></div>';
    echo '<aside class="sidebar"><div class="sidebar-brand">';brand();echo '<small>'.e(setting('store_name')).'</small></div><nav>';
    foreach($tabs as $i=>$item)echo '<a href="/admin?tab='.$item.'"'.($item===$tab?' aria-current="page"':'').'><span>'.str_pad((string)($i+1),2,'0',STR_PAD_LEFT).'</span>'.e(t($item)).'</a>';
    echo '</nav><div class="sidebar-bottom"><a href="/guide/'.e(language()).'/index.html" target="_blank" rel="noopener">'.e(t('help_guide')).' ↗</a><a href="/display" target="_blank" rel="noopener">'.e(t('open_tv')).' ↗</a><small>v'.e(version()).'</small></div></aside><div class="workspace"><header class="admin-top"><span>'.e(currentAdmin()['name']).'</span><div class="top-actions">';
    languagePicker();formStart('logout','/login');echo '<button class="subtle">'.e(t('logout')).'</button></form></div></header><main class="admin-main"><div class="page-heading"><span class="eyebrow">PULSE / DIETIKON</span><h1>'.e(t($tab)).'</h1></div>';
    showFlash();$update=publicUpdateState();
    if($update['available']&&$tab!=='updates')echo '<a class="notice update-link" href="/admin?tab=updates">'.e(t('update_available')).' · v'.e($update['version']).' →</a>';
    match($tab){
        'overview'=>adminOverview(),'content'=>adminContent(),'employees'=>adminEmployees(),'gallery'=>adminGallery(),
        'settings'=>adminSettings(),'administrators'=>adminAccounts(),'backups'=>adminBackups(),'updates'=>adminUpdates(),
    };
    echo '</main></div>';pageEnd();
}
