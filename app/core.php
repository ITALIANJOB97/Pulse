<?php
declare(strict_types=1);

function storage(string $path = ''): string {
    return rtrim($GLOBALS['pulse_config']['storage'], '/\\') . ($path === '' ? '' : '/' . $path);
}
function e(mixed $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function jsonValue(mixed $value): string {
    return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
function version(): string {
    $v = trim((string) file_get_contents(PULSE_ROOT . '/VERSION'));
    if (!preg_match('/^\d+\.\d+\.\d+$/D', $v)) throw new RuntimeException('Invalid VERSION file.');
    return $v;
}
function db(): PDO {
    static $db;
    if ($db instanceof PDO) return $db;
    $db = new PDO('sqlite:' . storage('pulse.sqlite'), null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
    $db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version INTEGER PRIMARY KEY, applied_at TEXT NOT NULL)');
    if (!(int) $db->query('SELECT COUNT(*) FROM schema_migrations WHERE version=1')->fetchColumn()) {
        $db->exec('BEGIN IMMEDIATE');
        try {
            $db->exec('
                CREATE TABLE IF NOT EXISTS admins (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE COLLATE NOCASE, password_hash TEXT NOT NULL, created_at TEXT NOT NULL);
                CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
                CREATE TABLE IF NOT EXISTS employees (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, role TEXT NOT NULL, photo INTEGER, active INTEGER NOT NULL DEFAULT 1);
                CREATE TABLE IF NOT EXISTS media (id INTEGER PRIMARY KEY AUTOINCREMENT, filename TEXT NOT NULL UNIQUE, label TEXT NOT NULL, kind TEXT NOT NULL, created_at TEXT NOT NULL);
                CREATE TABLE IF NOT EXISTS content (module TEXT NOT NULL, period TEXT NOT NULL, payload TEXT NOT NULL, updated_at TEXT NOT NULL, PRIMARY KEY(module,period));
                CREATE TABLE IF NOT EXISTS snapshots (kind TEXT NOT NULL, period TEXT NOT NULL, payload TEXT NOT NULL, captured_at TEXT NOT NULL, PRIMARY KEY(kind,period));
                CREATE TABLE IF NOT EXISTS login_attempts (ip_hash TEXT PRIMARY KEY, failures INTEGER NOT NULL, locked_until INTEGER NOT NULL, last_attempt INTEGER NOT NULL);
            ');
            $q = $db->prepare('INSERT OR IGNORE INTO schema_migrations VALUES(1,?)');
            $q->execute([gmdate('c')]);
            $db->exec('COMMIT');
        } catch (Throwable $ex) { $db->exec('ROLLBACK'); throw $ex; }
    }
    return $db;
}
function setting(string $key, mixed $default = null): mixed {
    $q = db()->prepare('SELECT value FROM settings WHERE key=?');
    $q->execute([$key]);
    $v = $q->fetchColumn();
    return $v === false ? $default : json_decode($v, true, 512, JSON_THROW_ON_ERROR);
}
function setSetting(string $key, mixed $value): void {
    $q = db()->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value');
    $q->execute([$key, jsonValue($value)]);
}
function installed(): bool {
    return (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
}
function startSession(): void {
    session_name('pulse_session');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_save_path(storage('sessions'));
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax',
    ]);
    session_start();
    if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > $GLOBALS['pulse_config']['session_seconds']) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_seen'] = time();
    if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function csrf(): string { return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">'; }
function verifyCsrf(): void {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        throw new DomainException(t('csrf_error'));
    }
}
function currentAdmin(): ?array {
    if (!isset($_SESSION['admin_id'])) return null;
    $q = db()->prepare('SELECT id,name,email FROM admins WHERE id=?');
    $q->execute([$_SESSION['admin_id']]);
    return $q->fetch() ?: null;
}
function requireAdmin(): array {
    $user = currentAdmin();
    if (!$user) redirect('/login');
    return $user;
}
function redirect(string $path): never {
    header('Location: ' . $path, true, 303);
    exit;
}
function flash(string $message, string $type = 'success'): void {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}
function inputText(string $key, int $max = 200, string $default = ''): string {
    $v = $_POST[$key] ?? $default;
    if (!is_string($v) || mb_strlen($v) > $max) throw new DomainException(t('invalid_input'));
    return trim($v);
}
function numberValue(mixed $value, float $min, float $max): float {
    if (!is_scalar($value) || !is_numeric($value) || !is_finite((float) $value) || (float) $value < $min || (float) $value > $max) {
        throw new DomainException(t('invalid_input'));
    }
    return (float) $value;
}
function inputNumber(string $key, float $min, float $max, float $default = 0): float {
    return numberValue($_POST[$key] ?? $default, $min, $max);
}
function optionalNumber(string $key, float $min, float $max): ?float {
    return ($_POST[$key] ?? '') === '' ? null : inputNumber($key, $min, $max);
}
function passwordValid(string $password): bool {
    return strlen($password) >= 12 && strlen($password) <= 72;
}
function employeeId(mixed $value, bool $optional = false): ?int {
    if ($optional && ($value === '' || $value === null || (string)$value === '0')) return null;
    $id = (int) numberValue($value, 1, PHP_INT_MAX);
    $q = db()->prepare('SELECT id FROM employees WHERE id=?');
    $q->execute([$id]);
    if (!$q->fetchColumn()) throw new DomainException(t('invalid_input'));
    return $id;
}
function login(string $email, string $password): bool {
    $ip = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $q = db()->prepare('SELECT * FROM login_attempts WHERE ip_hash=?');
    $q->execute([$ip]); $attempt = $q->fetch();
    if ($attempt && (int)$attempt['locked_until'] > time()) throw new DomainException(t('login_locked'));
    $q = db()->prepare('SELECT * FROM admins WHERE email=?');
    $q->execute([$email]); $user = $q->fetch();
    // A real hash is always checked, including for unknown users.
    $hash = $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    $valid = password_verify($password, $hash);
    if (!$user || !$valid) {
        $failures = $attempt && time() - (int)$attempt['last_attempt'] < 900 ? (int)$attempt['failures'] + 1 : 1;
        $q = db()->prepare('INSERT INTO login_attempts VALUES(?,?,?,?) ON CONFLICT(ip_hash) DO UPDATE SET failures=excluded.failures,locked_until=excluded.locked_until,last_attempt=excluded.last_attempt');
        $q->execute([$ip, $failures, $failures >= 5 ? time() + 900 : 0, time()]);
        return false;
    }
    db()->prepare('DELETE FROM login_attempts WHERE ip_hash=?')->execute([$ip]);
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $user['id'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return true;
}
function setupToken(): string {
    $file = storage('setup-token');
    if (is_file($file)) return trim((string)file_get_contents($file));
    $token = bin2hex(random_bytes(16));
    $handle = @fopen($file, 'x');
    if (!$handle) {
        if (is_file($file)) return trim((string)file_get_contents($file));
        throw new RuntimeException('Cannot create setup token.');
    }
    fwrite($handle, $token); fclose($handle); @chmod($file, 0640);
    return $token;
}
function initialize(array $data): void {
    if (!is_file(storage('setup-token')) || !hash_equals(trim((string)file_get_contents(storage('setup-token'))), $data['token'])) {
        throw new DomainException(t('setup_token_error'));
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || !passwordValid($data['password']) || $data['name'] === '' || $data['store'] === '') {
        throw new DomainException(t('invalid_input'));
    }
    db()->exec('BEGIN IMMEDIATE');
    try {
        if (installed()) throw new DomainException(t('already_installed'));
        $q = db()->prepare('INSERT INTO admins(name,email,password_hash,created_at) VALUES(?,?,?,?)');
        $q->execute([$data['name'], $data['email'], password_hash($data['password'], PASSWORD_DEFAULT), gmdate('c')]);
        setSetting('store_name', $data['store']);
        setSetting('language', $data['language']);
        setSetting('duration', $data['duration']);
        setSetting('effects', true);
        setSetting('pages', array_fill_keys(moduleKeys(), true));
        setSetting('screen_key', bin2hex(random_bytes(32)));
        setSetting('installed_version', version());
        setSetting('installed_at', gmdate('c'));
        setSetting('revision', bin2hex(random_bytes(8)));
        setSetting('update_url', '');
        db()->exec('COMMIT');
    } catch (Throwable $ex) { db()->exec('ROLLBACK'); throw $ex; }
    @unlink(storage('setup-token'));
}
function moduleKeys(): array {
    return ['spotlight','podium','team','roles','transfers','targets','daily','gallery','tasks'];
}
function employees(bool $activeOnly = false): array {
    return db()->query('SELECT * FROM employees' . ($activeOnly ? ' WHERE active=1' : '') . ' ORDER BY name COLLATE NOCASE,id')->fetchAll();
}
function content(string $module, string $period, mixed $default = []): mixed {
    $q = db()->prepare('SELECT payload FROM content WHERE module=? AND period=?');
    $q->execute([$module, $period]);
    $value = $q->fetchColumn();
    return $value === false ? $default : json_decode($value, true, 512, JSON_THROW_ON_ERROR);
}
function saveContent(string $module, string $period, array $payload): void {
    $q = db()->prepare('INSERT INTO content VALUES(?,?,?,?) ON CONFLICT(module,period) DO UPDATE SET payload=excluded.payload,updated_at=excluded.updated_at');
    $q->execute([$module, $period, jsonValue($payload), gmdate('c')]);
    touchRevision();
}
function touchRevision(): void { setSetting('revision', bin2hex(random_bytes(8))); }
function periodFor(string $module, ?DateTimeImmutable $date = null): string {
    $date ??= new DateTimeImmutable('now');
    return match($module) {
        'daily' => $date->format('Y-m-d'),
        'transfers' => $date->format('o-\WW'),
        'tasks', 'gallery', 'team' => 'all',
        default => $date->format('Y-m'),
    };
}
function validPeriod(string $module, string $period): bool {
    if (in_array($module, ['tasks','gallery','team'], true)) return $period === 'all';
    if ($module === 'daily') {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $period);
        return $d && $d->format('Y-m-d') === $period;
    }
    if ($module === 'transfers') {
        if (!preg_match('/^(\d{4})-W(\d{2})$/D', $period, $m)) return false;
        return (new DateTimeImmutable())->setISODate((int)$m[1], (int)$m[2])->format('o-\WW') === $period;
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m', $period);
    return $d && $d->format('Y-m') === $period;
}
function translationsFromPost(string $prefix, int $max = 500): array {
    $out = [];
    foreach (['en','de','fr'] as $lang) $out[$lang] = inputText($prefix . '_' . $lang, $max);
    return $out;
}
function screenAllowed(): bool {
    if (in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true) || currentAdmin()) return true;
    $key = $_COOKIE['pulse_screen'] ?? '';
    return is_string($key) && strlen($key) === 64 && hash_equals(setting('screen_key',''), $key);
}
function screenData(): array {
    $now = new DateTimeImmutable();
    $modules = [];
    foreach (moduleKeys() as $key) $modules[$key] = content($key, periodFor($key, $now));
    $photos = db()->query("SELECT id,label FROM media WHERE kind='gallery' ORDER BY id")->fetchAll();
    return [
        'version' => version(), 'revision' => setting('revision', ''), 'date' => $now->format('Y-m-d'),
        'month' => $now->format('Y-m'), 'week' => $now->format('o-\WW'), 'timezone' => 'Europe/Zurich',
        'store' => setting('store_name', 'PULSE · Dietikon'), 'language' => setting('language', 'de'),
        'duration' => setting('duration', 20), 'effects' => setting('effects', true),
        'show_sunday' => setting('show_sunday', true),
        'pages' => setting('pages', array_fill_keys(moduleKeys(), true)),
        'employees' => employees(true), 'content' => $modules, 'gallery' => $photos,
        'updates' => publicUpdateState(false),
    ];
}
function uploadImage(string $field, string $kind, string $label): ?int {
    $file = $_FILES[$field] ?? null;
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > $GLOBALS['pulse_config']['max_upload_bytes']) throw new DomainException(t('upload_error'));
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !in_array($info['mime'], ['image/jpeg','image/png','image/webp'], true) || $info[0] * $info[1] > 12000000) throw new DomainException(t('upload_error'));
    $bytes = file_get_contents($file['tmp_name']);
    $image = @imagecreatefromstring($bytes);
    if (!$image) throw new DomainException(t('upload_error'));
    // Normalize EXIF orientation before stripping metadata and resizing.
    if ($info['mime'] === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file['tmp_name']);
        $orientation = (int)($exif['Orientation'] ?? 1);
        if (in_array($orientation, [2,4,5,7], true)) imageflip($image, IMG_FLIP_HORIZONTAL);
        $angle = match($orientation) {3,4 => 180,5,6 => -90,7,8 => 90,default => 0};
        if ($angle) $image = imagerotate($image, $angle, 0);
    }
    $max = $kind === 'employee' ? 900 : 1920;
    $ratio = min(1, $max / imagesx($image), 1080 / imagesy($image));
    $out = imagecreatetruecolor(max(1,(int)round(imagesx($image)*$ratio)), max(1,(int)round(imagesy($image)*$ratio)));
    $white = imagecolorallocate($out, 255,255,255);
    imagefill($out,0,0,$white);
    imagecopyresampled($out,$image,0,0,0,0,imagesx($out),imagesy($out),imagesx($image),imagesy($image));
    $filename = bin2hex(random_bytes(20)) . '.jpg';
    if (!imagejpeg($out, storage('media/' . $filename), 86)) throw new RuntimeException('Image could not be saved.');
    @chmod(storage('media/' . $filename), 0640);
    $q = db()->prepare('INSERT INTO media(filename,label,kind,created_at) VALUES(?,?,?,?)');
    $q->execute([$filename, $label, $kind, gmdate('c')]);
    return (int)db()->lastInsertId();
}
function mediaFile(int $id): ?string {
    $q = db()->prepare('SELECT filename FROM media WHERE id=?');
    $q->execute([$id]); $name = $q->fetchColumn();
    return is_string($name) && preg_match('/^[a-f0-9]{40}\.jpg$/D', $name) ? storage('media/' . $name) : null;
}

