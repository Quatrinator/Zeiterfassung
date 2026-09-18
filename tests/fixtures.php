<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' || getenv('TEST_MODE')!=='1') exit(1);
require __DIR__.'/../src/bootstrap.php';
if(query('SELECT COUNT(*) FROM users')->fetchColumn()) { fwrite(STDERR,"Testdatenbank muss leer sein.\n"); exit(1); }
$hash=password_hash_native('LocalTestPassphrase!2026');
foreach([['qa_admin','Alex Weber','internal',3],['qa_member','Mia Schneider','internal',1],['qa_customer','Kundenansicht','customer',4]] as [$username,$name,$kind,$role]) {
    query('INSERT INTO users(username,display_name,password_hash,kind,must_change_password) VALUES(?,?,?,?,0)',[$username,$name,$hash,$kind]);
    query('INSERT INTO user_roles VALUES(?,?)',[(int)db()->lastInsertId(),$role]);
}
$admin=load_user(1); save_rate($admin,['valid_from'=>'2020-01-01','rate'=>'85,00']);
foreach([[1,45,'VPN-Zugang eingerichtet','Support'],[2,90,'Server-Updates und Backup geprüft','Wartung'],[1,30,'Abstimmung zur neuen Firewall','Beratung']] as [$id,$minutes,$description,$category]) {
    save_entry(load_user($id),['service_date'=>today(),'minutes'=>$minutes,'description'=>$description,'category'=>$category,'billable'=>true,'request_key'=>bin2hex(random_bytes(16))]);
}
transition_entries($admin,['transition'=>'release','items'=>[['id'=>1,'version'=>1]]]);
echo "Testkonten und Beispieldaten angelegt.\n";
