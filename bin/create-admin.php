<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/../src/bootstrap.php';
try {
    $username=strtolower(trim($argv[1] ?? readline('Benutzername des ersten Admins: ')));
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,63}$/D',$username)) fail('Benutzername: 3–64 Zeichen aus a–z, 0–9, Punkt, Bindestrich, Unterstrich.');
    $name=text_value(trim($argv[2] ?? readline('Anzeigename: ')),'Anzeigename',100);
    $password=bin2hex(random_bytes(12));
    transaction(function() use($username,$name,$password) {
        query('SELECT id FROM companies WHERE id=1 FOR UPDATE');
        if (query('SELECT COUNT(*) FROM users')->fetchColumn()) fail('Es bestehen bereits Konten. Weitere Admins bitte über die Benutzerverwaltung anlegen.');
        query('UPDATE companies SET name=? WHERE id=1',[config()['company_name']]);
        query("INSERT INTO users(username,display_name,password_hash,initial_expires_at) VALUES(?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 24 HOUR))",[$username,$name,password_hash_native($password)]);
        $id=(int)db()->lastInsertId(); query('INSERT INTO user_roles VALUES(?,3)',[$id]);
        audit($id,'admin.bootstrap','user',$id,null,['username'=>$username]);
    });
    echo "\nAdmin angelegt. Einmaliges Initialpasswort (24 Stunden gültig):\n$password\nBeim ersten Login muss es geändert werden.\n";
} catch (Throwable $e) { fwrite(STDERR,$e instanceof AppError ? $e->getMessage()."\n" : "Einrichtung fehlgeschlagen. Bitte Datenbank und Konfiguration prüfen.\n"); exit(1); }
