<?php
declare(strict_types=1);

/** One-off release dates: never recur in subsequent years. */
function countdownCatalog(): array {
    static $images;
    $images ??= json_decode(file_get_contents(__DIR__.'/countdown-images.json'), true, 512, JSON_THROW_ON_ERROR);
    $events = [
        'gta' => ['title'=>'Grand Theft Auto VI', 'release_date'=>'2026-11-19'],
        'doomsday' => ['title'=>'Avengers: Doomsday', 'release_date'=>'2026-12-16'],
    ];
    foreach ($events as $id => &$event) {
        $event['id'] = $id;
        $event['ends_at'] = (new DateTimeImmutable($event['release_date'].' 00:00:00', new DateTimeZone('Europe/Zurich')))->getTimestamp();
        $event['images'] = $images[$id];
    }
    unset($event);
    return $events;
}

function activeCountdowns(?DateTimeImmutable $now = null, ?array $preferences = null): array {
    $timestamp = ($now ?? new DateTimeImmutable())->getTimestamp();
    $preferences ??= setting('countdowns', []);
    $result = [];
    $catalog = countdownCatalog();
    foreach (customCountdowns() as $custom) {
        $event = $custom;
        $event['kind'] = 'custom';
        $event['images'] = ['/media?id='.(int)$custom['photo']];
        $event['release_date'] = (new DateTimeImmutable('@'.$custom['ends_at']))->setTimezone(new DateTimeZone('Europe/Zurich'))->format('Y-m-d');
        $catalog[$event['id']] = $event;
    }
    foreach ($catalog as $id => $event) {
        if ($timestamp >= $event['ends_at']) continue;
        $event['enabled'] = ($preferences[$id] ?? true) !== false;
        $result[] = $event;
    }
    return $result;
}

function countdownPreferences(mixed $input, ?DateTimeImmutable $now = null, ?array $present = null): array {
    $input = is_array($input) ? $input : [];
    $result = [];
    $previous = setting('countdowns', []);
    foreach (activeCountdowns($now, []) as $event) {
        $id = $event['id'];
        // An older open form must not disable a countdown created by another admin.
        $result[$id] = $present === null || in_array($id, $present, true)
            ? ($input[$id] ?? null) === '1' : ($previous[$id] ?? true) !== false;
    }
    return $result;
}

function countdownSettingsHtml(?DateTimeImmutable $now = null): string {
    $now ??= new DateTimeImmutable();
    $events = activeCountdowns($now);
    if (!$events) return '';
    $html = '<section data-countdown-settings data-server-time="'.$now->getTimestamp().'"><h3>'.e(t('temporary_countdowns')).'</h3><p class="help">'.e(t('countdown_hint')).'</p><div class="checkbox-grid">';
    foreach ($events as $event) {
        $html .= '<label class="check" data-countdown-expires="'.$event['ends_at'].'"><input type="hidden" name="countdown_ids[]" value="'.$event['id'].'"><input type="checkbox" name="countdowns['.$event['id'].']" value="1"'.($event['enabled']?' checked':'').'><span>'.e($event['title']).' · '.e((new DateTimeImmutable('@'.$event['ends_at']))->setTimezone(new DateTimeZone('Europe/Zurich'))->format('d.m.Y H:i')).'</span></label>';
    }
    return $html.'</div></section><input type="hidden" name="countdowns_present" value="1">';
}

function customCountdowns(): array { return array_values(content('countdowns','all')); }

function countdownDeadline(string $date, string $time, ?DateTimeImmutable $now = null): DateTimeImmutable {
    $time = $time === '' ? '00:00' : $time;
    if (!preg_match('/^20\d{2}-\d{2}-\d{2}$/D', $date) || !preg_match('/^\d{2}:\d{2}$/D', $time)) throw new DomainException(t('countdown_invalid_date'));
    $deadline = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date.' '.$time, new DateTimeZone('Europe/Zurich'));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$deadline || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $deadline->format('Y-m-d H:i') !== $date.' '.$time || $deadline <= ($now ?? new DateTimeImmutable())) throw new DomainException(t('countdown_invalid_date'));
    return $deadline;
}

function rememberCountdownForm(): void {
    $draft = [];
    foreach (['id'=>40,'title'=>101,'caption'=>281,'date'=>10,'time'=>5] as $key=>$limit) {
        $value = $_POST[$key] ?? '';
        $draft[$key] = is_string($value) ? mb_substr($value,0,$limit) : '';
    }
    $draft['enabled'] = isset($_POST['enabled']);
    $_SESSION['countdown_form'] = $draft;
}

function customCountdownAction(string $action, ?DateTimeImmutable $now = null): void {
    $now ??= new DateTimeImmutable();
    $id = inputText('id',40);
    if ($id !== '' && !preg_match('/^custom_[a-f0-9]{16}$/D',$id)) throw new DomainException(t('invalid_input'));
    if (!in_array($action,['countdown_save','countdown_toggle','countdown_delete'],true) || ($action !== 'countdown_save' && $id === '')) throw new DomainException(t('invalid_input'));
    if ($action === 'countdown_save') {
        $title = inputText('title',100); $caption = inputText('caption',280);
        if ($title === '') throw new DomainException(t('invalid_input'));
        $deadline = countdownDeadline(inputText('date',10),inputText('time',5),$now);
    }
    $newFile = null;
    db()->exec('BEGIN IMMEDIATE');
    try {
        $items = customCountdowns(); $index = null;
        foreach ($items as $i=>$item) if ($item['id'] === $id) { $index=$i; break; }
        if ($id !== '' && $index === null) throw new DomainException(t('countdown_not_found'));
        $preferences = setting('countdowns',[]);
        if ($action === 'countdown_delete') {
            db()->prepare("UPDATE media SET kind='archived' WHERE id=? AND kind='countdown'")->execute([$items[$index]['photo']]);
            array_splice($items,$index,1);unset($preferences[$id]);
        } elseif ($action === 'countdown_toggle') {
            if ($items[$index]['ends_at'] <= $now->getTimestamp()) throw new DomainException(t('countdown_expired'));
            $preferences[$id] = inputText('enabled',1) === '1';
        } else {
            if ($index === null && count($items) >= 20) throw new DomainException(t('countdown_limit'));
            $photo = uploadImage('image','countdown',$title);
            if ($photo) $newFile = mediaFile($photo);
            if (!$photo && $index === null) throw new DomainException(t('countdown_image_required'));
            $old = $index === null ? null : $items[$index];
            if ($photo && $old) db()->prepare("UPDATE media SET kind='archived' WHERE id=? AND kind='countdown'")->execute([$old['photo']]);
            if ($id === '') $id = 'custom_'.bin2hex(random_bytes(8));
            $record = ['id'=>$id,'title'=>$title,'caption'=>$caption,'photo'=>$photo ?? $old['photo'],
                'ends_at'=>$deadline->getTimestamp(),'created_at'=>$old['created_at'] ?? $now->getTimestamp()];
            if ($index === null) $items[]=$record; else $items[$index]=$record;
            $preferences[$id] = isset($_POST['enabled']);
        }
        setSetting('countdowns',$preferences);
        saveContent('countdowns','all',array_values($items));
        db()->exec('COMMIT');
    } catch (Throwable $error) {
        db()->exec('ROLLBACK');
        // Only the newly generated image belongs to this failed write.
        if ($newFile !== null && is_file($newFile)) @unlink($newFile);
        throw $error;
    }
}
