<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit(1);
require __DIR__.'/../src/bootstrap.php';
try {
    $name=$argv[1] ?? '';
    if(!$name) fail('Aufruf: php bin/reset-admin.php BENUTZERNAME');
    $password=bin2hex(random_bytes(12));
    transaction(function() use($name,$password) {
        query('SELECT id FROM companies WHERE id=1 FOR UPDATE');
        $record=row('SELECT id FROM users WHERE username=?',[$name]);
        $user=$record ? load_user((int)$record['id']) : null;
        if(!$user || !$user['is_admin'] || !$user['active']) fail('Aktives Administratorkonto nicht gefunden.');
        query('UPDATE users SET password_hash=?,must_change_password=1,initial_expires_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 24 HOUR),auth_version=auth_version+1 WHERE id=?',[password_hash_native($password),$user['id']]);
        audit(null,'admin.cli_password_reset','user',(int)$user['id']);
    });
    echo "Neues Initialpasswort (24 Stunden gültig, danach ändern):\n$password\n";
} catch(Throwable $e) { fwrite(STDERR,$e instanceof AppError?$e->getMessage()."\n":"Zurücksetzen fehlgeschlagen.\n"); exit(1); }
