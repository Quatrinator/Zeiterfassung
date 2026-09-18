<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
try {
    if (!defined('PASSWORD_ARGON2ID') || !extension_loaded('pdo_mysql')) exit(1);
    if (!query("SELECT version FROM schema_versions WHERE version='001_initial'")->fetchColumn()) exit(1);
    exit(0);
} catch (Throwable) { exit(1); }
