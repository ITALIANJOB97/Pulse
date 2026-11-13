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
    foreach (countdownCatalog() as $id => $event) {
        if ($timestamp >= $event['ends_at']) continue;
        $event['enabled'] = ($preferences[$id] ?? true) !== false;
        $result[] = $event;
    }
    return $result;
}

function countdownPreferences(mixed $input, ?DateTimeImmutable $now = null): array {
    $input = is_array($input) ? $input : [];
    $result = [];
    foreach (activeCountdowns($now, []) as $event) $result[$event['id']] = ($input[$event['id']] ?? null) === '1';
    return $result;
}

function countdownSettingsHtml(?DateTimeImmutable $now = null): string {
    $now ??= new DateTimeImmutable();
    $events = activeCountdowns($now);
    if (!$events) return '';
    $html = '<section data-countdown-settings data-server-time="'.$now->getTimestamp().'"><h3>'.e(t('temporary_countdowns')).'</h3><p class="help">'.e(t('countdown_hint')).'</p><div class="checkbox-grid">';
    foreach ($events as $event) {
        $html .= '<label class="check" data-countdown-expires="'.$event['ends_at'].'"><input type="checkbox" name="countdowns['.$event['id'].']" value="1"'.($event['enabled']?' checked':'').'><span>'.e($event['title']).' · '.e((new DateTimeImmutable($event['release_date']))->format('d.m.Y')).'</span></label>';
    }
    return $html.'</div></section><input type="hidden" name="countdowns_present" value="1">';
}
