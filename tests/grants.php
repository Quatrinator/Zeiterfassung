<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli' || getenv('TEST_MODE')!=='1') exit(1);
require __DIR__.'/../src/bootstrap.php';
foreach([
    'UPDATE audit_events SET reason=reason WHERE id=0',
    'DELETE FROM audit_events WHERE id=0',
    'CREATE TABLE permission_probe (id INT)',
] as $sql) {
    try { query($sql); throw new RuntimeException('Datenbankrecht unerwartet vorhanden.'); }
    catch(PDOException $e) { if((int)($e->errorInfo[1] ?? 0)!==1142) throw $e; echo "PASS eingeschränktes Datenbankrecht\n"; }
}
if(is_file('/run/secrets/db_root_password')) throw new RuntimeException('Root-Secret im Anwendungscontainer vorhanden.');
echo "PASS kein Root-Secret im PHP-Container\n";
