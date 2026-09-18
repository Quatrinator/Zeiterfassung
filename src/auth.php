<?php
declare(strict_types=1);

function permission_catalog(): array
{
    return ['entries.manage_team'=>'Fremde Entwürfe bearbeiten','finance.view'=>'Beträge und Stundensätze sehen','rates.manage'=>'Stundensätze verwalten','entries.release'=>'Leistungen freigeben','billing.finalize'=>'Als abgerechnet markieren','billing.correct'=>'Abgerechnete Leistungen korrigieren','audit.view'=>'Änderungshistorie lesen'];
}
function load_user(int $id): ?array
{
    $user = row('SELECT * FROM users WHERE id=?', [$id]);
    if (!$user) return null;
    $roles = query('SELECT r.* FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?',[$id])->fetchAll();
    $user['is_admin'] = $user['kind']==='internal' && (bool)array_filter($roles, fn($r)=>(bool)$r['is_admin']);
    $user['roles'] = $roles;
    $user['permissions'] = $user['kind']==='customer' ? [] : ($user['is_admin'] ? array_keys(permission_catalog()) : query('SELECT permission FROM user_permissions WHERE user_id=? UNION SELECT rp.permission FROM role_permissions rp JOIN user_roles ur ON ur.role_id=rp.role_id WHERE ur.user_id=?',[$id,$id])->fetchAll(PDO::FETCH_COLUMN));
    return $user;
}
function can(array $user, string $permission): bool { return $user['kind']==='internal' && in_array($permission,$user['permissions'],true); }
function require_permission(array $user, string $permission): void { if (!can($user,$permission)) fail('Für diese Aktion fehlt dir die Berechtigung.',403); }
function require_admin(array $user): void { if (!$user['is_admin']) fail('Nur Administratoren können diese Funktion verwenden.',403); }
function require_internal(array $user): void { if ($user['kind']!=='internal') fail('Der Kundenzugang ist schreibgeschützt.',403); }
function has_finance(array $user): bool { return $user['kind']==='customer' || can($user,'finance.view'); }
function public_user(array $user): array
{
    return array_intersect_key($user, array_flip(['id','username','display_name','kind','company_id','is_admin','permissions','must_change_password']));
}
function session_boot(): void
{
    session_name('zeitwerk_session');
    ini_set('session.use_strict_mode','1');
    ini_set('session.use_only_cookies','1');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>config()['cookie_secure'],'httponly'=>true,'samesite'=>'Lax']);
    session_start();
    if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function csrf_check(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token) || $token==='') fail('Die Sitzung ist abgelaufen. Bitte Seite neu laden.',419);
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && $origin !== config()['app_url']) fail('Unzulässiger Anfrageursprung.',403);
}
function current_user(bool $required = true): ?array
{
    $user = isset($_SESSION['user_id']) ? load_user((int)$_SESSION['user_id']) : null;
    if ($user && (!$user['active'] || (int)$user['auth_version'] !== ($_SESSION['auth_version'] ?? 0) || time()-($_SESSION['last_seen'] ?? 0)>28800)) {
        unset($_SESSION['user_id'],$_SESSION['auth_version']); $user=null;
    }
    if (!$user && $required) fail('Bitte melde dich an.',401);
    if ($user) $_SESSION['last_seen']=time();
    return $user;
}
function password_hash_native(string $password): string
{
    if (!defined('PASSWORD_ARGON2ID')) throw new RuntimeException('Argon2id fehlt im PHP-Image.');
    return password_hash($password, PASSWORD_ARGON2ID);
}
function validate_password(mixed $password): string
{
    if (!is_string($password) || strlen($password)<12 || strlen($password)>256) fail('Das Passwort muss 12 bis 256 Zeichen (Bytes) lang sein.');
    return $password;
}
function login(array $input): array
{
    $name = strtolower(trim((string)($input['username'] ?? '')));
    $password = (string)($input['password'] ?? '');
    if (strlen($name)>64 || strlen($password)>256) fail('Benutzername oder Passwort ist nicht korrekt.',401);
    $keys = [hash('sha256','user:'.$name),hash('sha256','ip:'.client_ip())];
    sort($keys);
    $result = transaction(function() use ($keys,$name,$password) {
        foreach($keys as $key) {
            query('INSERT IGNORE INTO login_attempts(bucket,window_start) VALUES(?,UTC_TIMESTAMP())',[$key]);
            $attempt = row('SELECT * FROM login_attempts WHERE bucket=? FOR UPDATE',[$key]);
            if ($attempt['blocked_until'] && strtotime($attempt['blocked_until'].' UTC')>time()) return ['blocked'=>true];
            if (strtotime($attempt['window_start'].' UTC')<time()-900) query('UPDATE login_attempts SET failures=0,window_start=UTC_TIMESTAMP(),blocked_until=NULL WHERE bucket=?',[$key]);
        }
        $user = row('SELECT * FROM users WHERE username=?',[$name]);
        // A fixed valid hash keeps missing-account responses computationally comparable.
        $dummy='$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
        $valid = password_verify($password,$user['password_hash'] ?? $dummy);
        if (!$user || !$valid || !$user['active'] || ($user['must_change_password'] && $user['initial_expires_at'] && strtotime($user['initial_expires_at'].' UTC')<time())) {
            foreach($keys as $key) query('UPDATE login_attempts SET failures=failures+1,blocked_until=IF(failures>=10,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 MINUTE),NULL) WHERE bucket=?',[$key]);
            return ['invalid'=>true];
        }
        query('DELETE FROM login_attempts WHERE bucket=?',[hash('sha256','user:'.$name)]);
        return ['user'=>$user];
    });
    if (isset($result['blocked'])) fail('Zu viele Anmeldeversuche. Bitte in 15 Minuten erneut versuchen.',429);
    if (isset($result['invalid'])) fail('Benutzername oder Passwort ist nicht korrekt oder der Zugang ist abgelaufen.',401);
    $user=$result['user'];
    session_regenerate_id(true);
    $_SESSION=['user_id'=>(int)$user['id'],'auth_version'=>(int)$user['auth_version'],'last_seen'=>time(),'csrf'=>bin2hex(random_bytes(32))];
    return ['ok'=>true];
}
function change_password(array $actor,array $input): array
{
    $new=validate_password($input['new_password'] ?? '');
    if ($new !== ($input['confirm_password'] ?? '')) fail('Die neuen Passwörter stimmen nicht überein.');
    $version=transaction(function() use($actor,$input,$new) {
        query('SELECT id FROM companies WHERE id=1 FOR UPDATE');
        $user=load_user((int)$actor['id']);
        if (!$user['active'] || $user['auth_version']!==$actor['auth_version']) fail('Sitzung ungültig.',401);
        if (!password_verify((string)($input['current_password'] ?? ''),$user['password_hash'])) fail('Das aktuelle Passwort stimmt nicht.');
        if (password_verify($new,$user['password_hash'])) fail('Bitte ein anderes neues Passwort wählen.');
        query('UPDATE users SET password_hash=?,must_change_password=0,initial_expires_at=NULL,auth_version=auth_version+1 WHERE id=?',[password_hash_native($new),$user['id']]);
        audit((int)$user['id'],'password.changed','user',(int)$user['id']);
        return (int)$user['auth_version']+1;
    });
    session_regenerate_id(true);
    $_SESSION['auth_version']=$version;
    $_SESSION['csrf']=bin2hex(random_bytes(32));
    return ['ok'=>true];
}
function confirm_admin_password(array $user,array $input): void
{
    if (!password_verify((string)($input['admin_password'] ?? ''),$user['password_hash'])) fail('Bitte dein aktuelles Administrator-Passwort korrekt eingeben.',403);
}
