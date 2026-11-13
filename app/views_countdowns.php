<?php
declare(strict_types=1);

function adminCountdowns(?DateTimeImmutable $now=null): void {
    $now = ($now ?? new DateTimeImmutable())->setTimezone(new DateTimeZone('Europe/Zurich'));
    $items = customCountdowns(); $edit = null;
    $editId = is_string($_GET['edit'] ?? null) ? $_GET['edit'] : '';
    foreach ($items as $item) if ($item['id'] === $editId) $edit = $item;
    if ($editId !== '' && !$edit) {
        echo '<div class="notice error">'.e(t('countdown_not_found')).'</div><a class="button" href="/admin?tab=countdowns">'.e(t('back')).'</a>';return;
    }
    $preferences = setting('countdowns',[]);
    $date = $edit ? (new DateTimeImmutable('@'.$edit['ends_at']))->setTimezone(new DateTimeZone('Europe/Zurich')) : null;
    $values = ['id'=>$editId,'title'=>$edit['title'] ?? '', 'caption'=>$edit['caption'] ?? '',
        'date'=>$date?->format('Y-m-d') ?? '', 'time'=>$date?->format('H:i') ?? '00:00',
        'enabled'=>$edit ? ($edit['ends_at']>$now->getTimestamp() && ($preferences[$editId] ?? true)) : true];
    $draft = $_SESSION['countdown_form'] ?? null;
    if (is_array($draft) && ($draft['id'] ?? '') === $editId) { $values=array_replace($values,$draft);unset($_SESSION['countdown_form']); }
    echo '<p class="muted">'.e(t('countdown_manage_hint')).'</p><div class="split countdown-manager"><section class="panel"><h2>'.e(t($edit?'edit_countdown':'new_countdown')).'</h2>';
    formStart('countdown_save','/admin?tab=countdowns'.($edit?'&edit='.$editId:''),true);hidden('id',$editId);
    field('title',t('countdown_title'),$values['title'],'text','required maxlength="100"');
    echo '<label class="field"><span>'.e(t('countdown_caption')).'</span><textarea name="caption" rows="3" maxlength="280">'.e($values['caption']).'</textarea></label>';
    echo '<p class="help">'.e(t('countdown_text_hint')).'</p><div class="form-grid">';
    field('date',t('countdown_date'),$values['date'],'date','required min="'.$now->format('Y-m-d').'" max="2099-12-31"');
    field('time',t('countdown_time'),$values['time'],'time','step="60"');
    echo '</div><p class="help">'.e(t('countdown_time_hint')).'</p>';
    field('image',t('countdown_image'),'','file','accept="image/jpeg,image/png,image/webp"'.($edit?'':' required'));
    if ($edit) echo '<img class="countdown-admin-preview" src="/media?id='.(int)$edit['photo'].'" alt="'.e($edit['title']).'">';
    echo '<p class="help">'.e(t('countdown_image_hint')).'</p><label class="check"><input type="checkbox" name="enabled" value="1"'.($values['enabled']?' checked':'').'>'.e(t('countdown_show')).'</label>';
    saveButton($edit?'save':'create');
    if ($edit) echo '<a class="button subtle" href="/admin?tab=countdowns">'.e(t('new_countdown')).'</a>';
    echo '</section><section data-countdown-manager data-server-time="'.$now->getTimestamp().'" data-expired-label="'.e(t('countdown_expired')).'"><h2>'.e(t('your_countdowns')).' <small>'.count($items).' / 20</small></h2>';
    if (!$items) echo '<div class="empty">'.e(t('countdown_empty')).'</div>';
    usort($items,static fn($a,$b)=>(($a['ends_at']<=$now->getTimestamp())<=>($b['ends_at']<=$now->getTimestamp())) ?: ($a['ends_at']<=>$b['ends_at']));
    foreach ($items as $item) {
        $expired=$item['ends_at']<=$now->getTimestamp();$enabled=($preferences[$item['id']] ?? true)!==false;
        $date=(new DateTimeImmutable('@'.$item['ends_at']))->setTimezone(new DateTimeZone('Europe/Zurich'));
        echo '<article class="countdown-admin-card" data-countdown-card data-ends-at="'.$item['ends_at'].'"><img src="/media?id='.(int)$item['photo'].'" alt="" loading="lazy"><div><span class="countdown-admin-state" data-countdown-state>'.e(t($expired?'countdown_expired':($enabled?'countdown_visible':'countdown_hidden'))).'</span><h3>'.e($item['title']).'</h3><p class="countdown-admin-caption">'.e($item['caption']).'</p><p class="help">'.e($date->format('d.m.Y · H:i')).' · Europe/Zurich</p><div class="countdown-admin-actions"><a class="button subtle" href="/admin?tab=countdowns&amp;edit='.e($item['id']).'">'.e(t('edit')).'</a>';
        if (!$expired) {
            echo '<div data-countdown-toggle>';formStart('countdown_toggle','/admin?tab=countdowns');hidden('id',$item['id']);hidden('enabled',$enabled?'0':'1');echo '<button class="subtle">'.e(t($enabled?'countdown_hide':'countdown_show')).'</button></form></div>';
        }
        formStart('countdown_delete','/admin?tab=countdowns');hidden('id',$item['id']);echo '<button class="danger subtle" data-confirm="'.e(t('countdown_delete_confirm')).'">'.e(t('delete')).'</button></form></div></div></article>';
    }
    echo '</section></div>';
}
