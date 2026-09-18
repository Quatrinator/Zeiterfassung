<?php
declare(strict_types=1);

function permission_list(mixed $input): array
{
    if (!is_array($input)) fail('Ungültige Rechteauswahl.');
    $result=[];
    foreach($input as $permission) {
        if (!is_string($permission) || !isset(permission_catalog()[$permission])) fail('Unbekannte Berechtigung.');
        $result[]=$permission;
    }
    if (array_intersect($result,['rates.manage','entries.release','billing.finalize','billing.correct','audit.view'])) $result[]='finance.view';
    return array_values(array_unique($result));
}
function save_user(array $actor,array $input): array
{
    return mutate($actor,function($actor) use($input) {
        require_admin($actor); confirm_admin_password($actor,$input);
        $id=isset($input['id']) ? integer($input['id'],'Benutzer') : null;
        $old=$id ? load_user($id) : null;
        if ($id && !$old) fail('Benutzer nicht gefunden.',404);
        if ($old && (int)$old['auth_version']!==integer($input['auth_version'] ?? null,'Benutzerversion')) fail('Benutzer wurde inzwischen geändert. Bitte neu laden.',409);
        $username=strtolower(text_value($input['username'] ?? null,'Benutzername',64));
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,63}$/D',$username)) fail('Benutzername: 3–64 Zeichen, Buchstaben a–z, Zahlen, Punkt, Bindestrich oder Unterstrich.');
        $name=text_value($input['display_name'] ?? null,'Anzeigename',100);
        $kind=$input['kind'] ?? 'internal';
        if (!in_array($kind,['internal','customer'],true)) fail('Ungültiger Kontotyp.');
        $active=bool_value($input['active'] ?? true);
        $roleIds=$input['role_ids'] ?? [];
        if (!is_array($roleIds) || !$roleIds) fail('Bitte mindestens eine Rolle auswählen.');
        $roleIds=array_values(array_unique(array_map(fn($id)=>integer($id,'Rolle'),$roleIds)));
        foreach($roleIds as $roleId) {
            $role=row('SELECT * FROM roles WHERE id=?',[$roleId]);
            if (!$role || (($role['system_key']==='customer') !== ($kind==='customer'))) fail('Diese Rolle passt nicht zum Kontotyp.');
        }
        $permissions=permission_list($input['permissions'] ?? []);
        if ($kind==='customer' && $permissions) fail('Kundenkonten dürfen keine internen Rechte erhalten.');
        if (row('SELECT id FROM users WHERE username=? AND id<>?',[$username,$id ?? 0])) fail('Dieser Benutzername ist bereits vergeben.');
        $initial=null;
        if ($id) query('UPDATE users SET username=?,display_name=?,kind=?,active=?,auth_version=auth_version+1 WHERE id=?',[$username,$name,$kind,(int)$active,$id]);
        else {
            $initial=bin2hex(random_bytes(12));
            query('INSERT INTO users(username,display_name,password_hash,kind,active,initial_expires_at) VALUES(?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 24 HOUR))',[$username,$name,password_hash_native($initial),$kind,(int)$active]);
            $id=(int)db()->lastInsertId();
        }
        query('DELETE FROM user_roles WHERE user_id=?',[$id]);
        query('DELETE FROM user_permissions WHERE user_id=?',[$id]);
        foreach($roleIds as $roleId) query('INSERT INTO user_roles VALUES(?,?)',[$id,$roleId]);
        foreach($permissions as $permission) query('INSERT INTO user_permissions VALUES(?,?)',[$id,$permission]);
        if (!(int)query("SELECT COUNT(DISTINCT u.id) FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE u.active=1 AND u.kind='internal' AND r.is_admin=1")->fetchColumn()) fail('Der letzte aktive Administrator muss erhalten bleiben.');
        $new=load_user($id);
        audit((int)$actor['id'],$old?'user.updated':'user.created','user',$id,$old,$new);
        return ['ok'=>true,'id'=>$id,'initial_password'=>$initial,'self_changed'=>$id===(int)$actor['id']];
    });
}
function reset_password(array $actor,array $input): array
{
    return mutate($actor,function($actor) use($input) {
        require_admin($actor); confirm_admin_password($actor,$input);
        $id=integer($input['id'] ?? null,'Benutzer');
        if (!load_user($id)) fail('Benutzer nicht gefunden.',404);
        $password=bin2hex(random_bytes(12));
        query('UPDATE users SET password_hash=?,must_change_password=1,initial_expires_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 24 HOUR),auth_version=auth_version+1 WHERE id=?',[password_hash_native($password),$id]);
        audit((int)$actor['id'],'user.password_reset','user',$id);
        return ['ok'=>true,'initial_password'=>$password,'self_changed'=>$id===(int)$actor['id']];
    });
}
function save_role(array $actor,array $input): array
{
    return mutate($actor,function($actor) use($input) {
        require_admin($actor); confirm_admin_password($actor,$input);
        $id=isset($input['id']) ? integer($input['id'],'Rolle') : null;
        $old=$id ? row('SELECT * FROM roles WHERE id=?',[$id]) : null;
        if ($id && !$old) fail('Rolle nicht gefunden.',404);
        if ($old) {
            check_version($old,$input['version'] ?? null);
            if (in_array($old['system_key'],['admin','customer'],true)) fail('Administrator- und Kundenrolle sind geschützt.');
        }
        $name=text_value($input['name'] ?? null,'Rollenname',80);
        $permissions=permission_list($input['permissions'] ?? []);
        if (row('SELECT id FROM roles WHERE name=? AND id<>?',[$name,$id ?? 0])) fail('Dieser Rollenname existiert bereits.');
        if ($id) query('UPDATE roles SET name=?,version=version+1 WHERE id=?',[$name,$id]);
        else { query('INSERT INTO roles(name) VALUES(?)',[$name]); $id=(int)db()->lastInsertId(); }
        $before=$old ? query('SELECT permission FROM role_permissions WHERE role_id=?',[$id])->fetchAll(PDO::FETCH_COLUMN) : [];
        query('DELETE FROM role_permissions WHERE role_id=?',[$id]);
        foreach($permissions as $permission) query('INSERT INTO role_permissions VALUES(?,?)',[$id,$permission]);
        audit((int)$actor['id'],'role.saved','role',$id,$old ? ['name'=>$old['name'],'permissions'=>$before] : null,['name'=>$name,'permissions'=>$permissions]);
        return ['ok'=>true];
    });
}
function save_rate(array $actor,array $input): array
{
    return mutate($actor,function($actor) use($input) {
        require_permission($actor,'rates.manage'); require_permission($actor,'finance.view');
        $id=isset($input['id']) ? integer($input['id'],'Stundensatz') : null;
        $old=$id ? row('SELECT * FROM hourly_rates WHERE id=?',[$id]) : null;
        if ($id && !$old) fail('Stundensatz nicht gefunden.',404);
        if ($old) check_version($old,$input['version'] ?? null);
        $userId=empty($input['user_id']) ? null : integer($input['user_id'],'Person');
        if ($userId && !row("SELECT id FROM users WHERE id=? AND kind='internal'",[$userId])) fail('Bitte ein internes Teammitglied wählen.');
        $from=valid_date($input['valid_from'] ?? null,'Gültig ab');
        $until=empty($input['valid_until']) ? null : valid_date($input['valid_until'],'Gültig bis');
        if ($until && $until<=$from) fail('Das exklusive Enddatum muss nach dem Startdatum liegen.');
        $rate=cents($input['rate'] ?? '',10000000);
        if (row('SELECT id FROM hourly_rates WHERE company_id=1 AND user_id <=> ? AND id<>? AND valid_from<? AND (valid_until IS NULL OR valid_until>?)',[$userId,$id ?? 0,$until ?? '9999-12-31',$from])) fail('Dieser Zeitraum überschneidet sich mit einem vorhandenen Stundensatz. Bitte zuerst dessen Enddatum anpassen.');
        $reason=text_value($input['reason'] ?? '', 'Änderungsgrund',500,false);
        if ($old && !$reason) fail('Bitte einen Änderungsgrund angeben.');
        if ($id) query('UPDATE hourly_rates SET user_id=?,valid_from=?,valid_until=?,cents_per_hour=?,version=version+1 WHERE id=?',[$userId,$from,$until,$rate,$id]);
        else { query('INSERT INTO hourly_rates(user_id,valid_from,valid_until,cents_per_hour) VALUES(?,?,?,?)',[$userId,$from,$until,$rate]); $id=(int)db()->lastInsertId(); }
        audit((int)$actor['id'],'rate.saved','rate',$id,$old,row('SELECT * FROM hourly_rates WHERE id=?',[$id]),$reason);
        return ['ok'=>true];
    });
}
function admin_data(array $actor): array
{
    require_admin($actor);
    $users=query('SELECT id,username,display_name,kind,active,auth_version,must_change_password,initial_expires_at FROM users ORDER BY display_name')->fetchAll();
    foreach($users as &$user) {
        $user['role_ids']=array_map('intval',query('SELECT role_id FROM user_roles WHERE user_id=?',[$user['id']])->fetchAll(PDO::FETCH_COLUMN));
        $user['permissions']=query('SELECT permission FROM user_permissions WHERE user_id=?',[$user['id']])->fetchAll(PDO::FETCH_COLUMN);
        $user['effective_permissions']=load_user((int)$user['id'])['permissions'];
    }
    unset($user);
    $roles=query('SELECT * FROM roles ORDER BY id')->fetchAll();
    foreach($roles as &$role) $role['permissions']=query('SELECT permission FROM role_permissions WHERE role_id=?',[$role['id']])->fetchAll(PDO::FETCH_COLUMN);
    return ['users'=>$users,'roles'=>$roles,'permissions'=>permission_catalog()];
}
