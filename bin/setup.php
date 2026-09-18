<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit(1);
$root=dirname(__DIR__);
if(!is_dir($root.'/secrets')) mkdir($root.'/secrets',0700,true);
chmod($root.'/secrets',0700);
foreach(['db_password','db_root_password'] as $name) {
    $path=$root.'/secrets/'.$name.'.txt';
    if(file_exists($path)) { echo "$name vorhanden, unverändert.\n"; continue; }
    $file=fopen($path,'x'); if(!$file) throw new RuntimeException('Datei konnte nicht erstellt werden.');
    fwrite($file,bin2hex(random_bytes(32))."\n"); fclose($file);
    // Official MySQL and unprivileged PHP must both read these bind-mounted secrets.
    chmod($path,0644); echo "$name erzeugt.\n";
}
if(!file_exists($root.'/.env')) {
    $env=file_get_contents($root.'/.env.example');
    if(in_array('--local',$argv,true)) $env=str_replace(['https://zeit.example.de','APP_ENV=production','COOKIE_SECURE=1'],['http://localhost:8080','APP_ENV=development','COOKIE_SECURE=0'],$env);
    file_put_contents($root.'/.env',$env); echo ".env angelegt.\n";
}
echo "Einrichtung vorbereitet. Vor dem Produktivstart .env prüfen.\n";
