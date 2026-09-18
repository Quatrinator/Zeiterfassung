<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' || getenv('TEST_MODE')!=='1') exit(1);
require __DIR__.'/../src/bootstrap.php';
if(row("SELECT id FROM users WHERE username='load_1'")) { echo "Lastdaten schon vorhanden.\n"; exit; }
$hash=password_hash_native('LocalTestPassphrase!2026');
transaction(function() use($hash) {
    $ids=[];
    for($i=1;$i<=10;$i++) {
        query('INSERT INTO users(username,display_name,password_hash,must_change_password) VALUES(?,?,?,0)',['load_'.$i,'Lasttest '.$i,$hash]);
        $id=(int)db()->lastInsertId(); $ids[]=$id; query('INSERT INTO user_roles VALUES(?,1)',[$id]);
    }
    for($batch=0;$batch<500;$batch++) {
        $params=[];$tuples=[];
        for($i=0;$i<100;$i++) {
            $id=$ids[$i%10]; $tuples[]="(?, '2026-01-15', 30, ?, 1, 8500, 4250, 'released', UTC_TIMESTAMP(), ?, ?)";
            array_push($params,$id,'Lasttest '.($batch*100+$i),$id,$id);
        }
        query('INSERT INTO time_entries(user_id,service_date,minutes,description,billable,rate_cents,amount_cents,status,released_at,created_by,updated_by) VALUES '.implode(',',$tuples),$params);
    }
});
echo "50.000 Zeiteinträge und zehn separate Testbenutzer erzeugt.\n";
