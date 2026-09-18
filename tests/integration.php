<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit(1);
$name='zeitwerk_test_'.bin2hex(random_bytes(6));
$rootPassword=trim((string)file_get_contents('/run/secrets/test_db_root'));
$root=new PDO('mysql:host=mysql;charset=utf8mb4','root',$rootPassword,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$root->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
putenv('DB_NAME='.$name); putenv('DB_USER=root'); putenv('DB_PASSWORD_FILE=/run/secrets/test_db_root');
require __DIR__.'/../src/bootstrap.php';
$count=0;
function expect(bool $value,string $message): void { global $count; if(!$value) throw new RuntimeException('FAIL: '.$message); ++$count; echo "PASS $message\n"; }
function denied(callable $action,int $status,string $message): void {
    try { $action(); } catch(AppError $e) { expect($e->status===$status,$message.' (HTTP '.$e->status.')'); return; }
    throw new RuntimeException('FAIL: '.$message.' was accepted');
}
function input_entry(array $extra=[]): array { return array_replace(['service_date'=>'2026-01-15','minutes'=>30,'description'=>'VPN eingerichtet','billable'=>true,'category'=>'Support','request_key'=>bin2hex(random_bytes(16))],$extra); }
function transition_input(int $id,string $transition): array {
    return ['transition'=>$transition,'items'=>[['id'=>$id,'version'=>row('SELECT version FROM time_entries WHERE id=?',[$id])['version']]],'reason'=>'Testkorrektur'];
}
try {
    db()->exec(file_get_contents(ROOT.'/database/schema.sql'));
    $hash=password_hash_native('Testpassphrase!2026');
    foreach([['administrator','Admin','internal',3],['anna','Anna','internal',1],['ben','Ben','internal',1],['kunde','Kunde','customer',4]] as [$username,$display,$kind,$role]) {
        query('INSERT INTO users(username,display_name,password_hash,kind,must_change_password) VALUES(?,?,?,?,0)',[$username,$display,$hash,$kind]);
        query('INSERT INTO user_roles VALUES(?,?)',[(int)db()->lastInsertId(),$role]);
    }
    $admin=load_user(1); $anna=load_user(2); $ben=load_user(3); $customer=load_user(4);
    expect(can($admin,'rates.manage')&&!can($anna,'finance.view'),'admin and member permission boundary');
    expect(!can($customer,'entries.release')&&has_finance($customer),'customer finance limited to report context');
    expect(amount(45,8000)===6000&&amount(1,10000)*3===501,'integer cent rounding');
    expect(cents('80,50')===8050,'German money input');
    denied(fn()=>cents('1e3'),422,'scientific money input rejected');
    expect(ip_in_network('192.168.1.2','192.168.1.0/24')&&!ip_in_network('192.168.2.2','192.168.1.0/24'),'IPv4 proxy trust boundaries');
    expect(ip_in_network('2001:db8::1','2001:db8::/32')&&!ip_in_network('::1','2001:db8::/32'),'IPv6 proxy trust boundaries');
    expect(csv_text(' =HYPERLINK("x")')[0]==="'",'spreadsheet formula mitigation');
    denied(fn()=>save_entry($customer,input_entry()),403,'customer cannot create time');
    denied(fn()=>save_entry($anna,input_entry(['minutes'=>0])),422,'zero time rejected');
    denied(fn()=>save_entry($anna,input_entry(['minutes'=>1.5])),422,'fractional time rejected');
    denied(fn()=>save_entry($anna,input_entry(['service_date'=>'2099-01-01'])),422,'future work rejected');
    $input=input_entry(); $first=save_entry($anna,$input)['id'];
    expect(save_entry($anna,$input)['id']===$first,'retry is idempotent');
    denied(fn()=>save_entry($anna,array_replace($input,['minutes'=>99])),409,'same key with changed payload rejected');
    expect(save_entry($anna,input_entry())['id']!==$first,'intentional identical entry allowed');
    $entry=entry_record($first);
    expect($entry['amount_cents']===null,'missing rate remains unpriced');
    denied(fn()=>transition_entries($admin,transition_input($first,'release')),422,'unpriced release rejected');
    denied(fn()=>save_entry($ben,input_entry(['id'=>$first,'version'=>1])),403,'foreign edit rejected');
    $team=report($ben,['from'=>'2026-01-01','to'=>'2026-01-31']);
    expect(count($team['entries'])===2,'other team member sees all time drafts');
    expect(!array_key_exists('amount_cents',$team['entries'][0])&&!array_key_exists('amount_cents',$team['summary']),'money fields absent from member output');
    expect(report($customer,['from'=>'2026-01-01','to'=>'2026-01-31'])['summary']['count']===0,'customer cannot see drafts');
    save_rate($admin,['valid_from'=>'2026-01-01','valid_until'=>'2026-02-01','rate'=>'80,00']);
    save_rate($admin,['valid_from'=>'2026-02-01','rate'=>'100,00']);
    denied(fn()=>save_rate($admin,['valid_from'=>'2026-01-15','rate'=>'90,00']),422,'overlapping rate rejected');
    expect(select_rate(2,'2026-01-31')===8000&&select_rate(2,'2026-02-01')===10000,'rate end date exclusive');
    save_rate($admin,['user_id'=>2,'valid_from'=>'2026-01-01','rate'=>'120,00']);
    expect(select_rate(2,'2026-01-15')===12000&&select_rate(3,'2026-01-15')===8000,'personal rate takes precedence');
    transition_entries($admin,transition_input($first,'reprice'));
    expect(entry_record($first)['amount_cents']===6000,'explicit repricing applies personal rate');
    $second=save_entry($ben,input_entry(['minutes'=>45]))['id'];
    expect(entry_record($second)['amount_cents']===6000,'45 minutes at 80 EUR');
    save_rate($admin,['id'=>1,'version'=>1,'valid_from'=>'2026-01-01','valid_until'=>'2026-02-01','rate'=>'90,00','reason'=>'Test']);
    expect(entry_record($second)['rate_cents']===8000,'historical snapshot survives rate change');
    $input=input_entry(['id'=>$second,'version'=>1,'minutes'=>50]); save_entry($ben,$input);
    denied(fn()=>save_entry($ben,input_entry(['id'=>$second,'version'=>1])),409,'stale edit rejected');
    expect(entry_record($second)['rate_cents']===8000,'duration edit preserves historical snapshot');
    transition_entries($admin,transition_input($first,'release'));
    denied(fn()=>delete_entry($anna,['id'=>$first,'version'=>3]),409,'released time cannot be deleted');
    expect(report($customer,['from'=>'2026-01-01','to'=>'2026-01-31'])['summary']['count']===1,'customer sees only released entries');
    $batch=['transition'=>'release','items'=>[['id'=>$second,'version'=>2],['id'=>$first,'version'=>3]]];
    denied(fn()=>transition_entries($admin,$batch),409,'batch fails atomically');
    expect(entry_record($second)['status']==='draft','failed batch rolls back first entry');
    transition_entries($admin,transition_input($first,'recall'));
    expect(report($customer,['from'=>'2026-01-01','to'=>'2026-01-31'])['summary']['count']===0,'recalled item removed from customer report');
    transition_entries($admin,transition_input($first,'release'));
    transition_entries($admin,transition_input($first,'bill'));
    denied(fn()=>transition_entries($admin,transition_input($first,'recall')),409,'billed original immutable');
    $adjust=create_adjustment($admin,['original_id'=>$first,'target_minutes'=>15,'target_amount'=>'30','description'=>'Zeit berichtigt','reason'=>'Doppelte Zeit','request_key'=>bin2hex(random_bytes(16))])['id'];
    denied(fn()=>create_adjustment($admin,['original_id'=>$first,'target_minutes'=>10,'target_amount'=>'20','description'=>'Korrektur','reason'=>'Grund','request_key'=>bin2hex(random_bytes(16))]),409,'one pending correction per original');
    transition_entries($admin,transition_input($adjust,'release'));
    expect(correction_base($first)===['minutes'=>15,'amount_cents'=>3000],'published correction adjusts totals');
    expect(entry_record($first)['minutes']===30&&entry_record($first)['amount_cents']===6000,'correction preserves original');
    $nonbill=save_entry($ben,input_entry(['billable'=>false,'service_date'=>'2025-01-01']))['id'];
    transition_entries($admin,transition_input($nonbill,'release'));
    expect(entry_record($nonbill)['amount_cents']===0,'nonbillable entry releases without rate');
    $edit=['id'=>1,'auth_version'=>1,'username'=>'administrator','display_name'=>'Admin','kind'=>'internal','role_ids'=>[1],'permissions'=>[],'active'=>true,'admin_password'=>'Testpassphrase!2026'];
    denied(fn()=>save_user($admin,$edit),422,'last admin cannot lose admin role');
    expect(load_user(1)['is_admin'],'last-admin rollback intact');
    denied(fn()=>save_user($admin,['username'=>'external','display_name'=>'External','kind'=>'customer','role_ids'=>[3],'permissions'=>[],'active'=>true,'admin_password'=>'Testpassphrase!2026']),422,'customer cannot receive admin role');
    $memberChange=['id'=>2,'auth_version'=>1,'username'=>'anna','display_name'=>'Anna','kind'=>'internal','role_ids'=>[1],'permissions'=>['entries.release'],'active'=>true,'admin_password'=>'Testpassphrase!2026'];
    save_user($admin,$memberChange);
    expect(can(load_user(2),'entries.release')&&can(load_user(2),'finance.view'),'rights granted with finance dependency');
    denied(fn()=>save_entry($anna,input_entry()),401,'previous session revoked on user change');
    $anna=load_user(2); $memberChange['auth_version']=2; $memberChange['permissions']=[]; save_user($admin,$memberChange);
    expect(!can(load_user(2),'entries.release'),'rights revoked immediately');
    save_role($admin,['name'=>'Prüfung','permissions'=>['rates.manage'],'admin_password'=>'Testpassphrase!2026']);
    expect(query("SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id=rp.role_id WHERE r.name='Prüfung'")->fetchColumn()===2,'custom role includes finance dependency');
    $allAudit=implode('',query('SELECT COALESCE(before_json,\'\'),COALESCE(after_json,\'\') FROM audit_events')->fetchAll(PDO::FETCH_COLUMN));
    expect(!str_contains($allAudit,'password_hash')&&!str_contains($allAudit,$hash),'audit does not store password hashes');
    expect((int)query('SELECT COUNT(*) FROM audit_events')->fetchColumn()>10,'mutations create audit trail');
    expect(count(activity_templates($ben))===6,'default activity templates available to members');
    denied(fn()=>activity_templates($customer),403,'customer cannot read template catalog');
    denied(fn()=>create_activity_template($ben,['label'=>'Drucker einrichten']),403,'template management requires permission');
    $template=create_activity_template($admin,['label'=>'Drucker einrichten'])['id'];
    denied(fn()=>create_activity_template($admin,['label'=>'Drucker einrichten']),422,'duplicate template rejected');
    denied(fn()=>create_activity_template($admin,['label'=>'   ']),422,'empty template rejected');
    $templateInput=input_entry(['template_id'=>$template,'description'=>'']);
    $templateEntry=save_entry($ben,$templateInput)['id'];
    expect(entry_record($templateEntry)['description']==='Drucker einrichten','template selection alone produces complete description');
    $withNote=save_entry($ben,input_entry(['template_id'=>$template,'description'=>'Im Empfang']))['id'];
    expect(entry_record($withNote)['description']==='Drucker einrichten – Im Empfang','template and optional note combined');
    denied(fn()=>save_entry($ben,input_entry(['description'=>''])),422,'description required without template');
    denied(fn()=>save_entry($ben,input_entry(['template_id'=>$template,'description'=>str_repeat('a',500)])),422,'combined description length validated');
    denied(fn()=>delete_activity_template($ben,['id'=>$template]),403,'member cannot delete shared template without permission');
    query('INSERT INTO user_permissions VALUES(?,?)',[$ben['id'],'templates.manage']);
    delete_activity_template(load_user((int)$ben['id']),['id'=>$template]);
    expect(entry_record($templateEntry)['description']==='Drucker einrichten','deleting template preserves historical entry text');
    expect(save_entry($ben,$templateInput)['id']===$templateEntry,'retry remains idempotent after template deletion');
    denied(fn()=>save_entry($ben,input_entry(['template_id'=>$template,'description'=>''])),409,'concurrently deleted template is rejected');
    expect((int)query("SELECT COUNT(*) FROM audit_events WHERE action IN ('template.created','template.deleted')")->fetchColumn()===2,'template changes audited');
    echo "\n$count integration checks passed against MySQL ".$root->query('SELECT VERSION()')->fetchColumn().".\n";
} finally {
    if(db()->inTransaction()) db()->rollBack();
    // Only the random database created by this test is removed.
    if(preg_match('/^zeitwerk_test_[a-f0-9]{12}$/D',$name)) $root->exec("DROP DATABASE `$name`");
}
