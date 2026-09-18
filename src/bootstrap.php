<?php
declare(strict_types=1);

const ROOT = __DIR__ . '/..';
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/entries.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/reports.php';

date_default_timezone_set('UTC');
ini_set('display_errors', '0');
error_reporting(E_ALL);

function config(): array
{
    static $config;
    if ($config !== null) return $config;
    $password = getenv('DB_PASSWORD') ?: '';
    if ($file = getenv('DB_PASSWORD_FILE')) {
        if (!is_readable($file)) throw new RuntimeException('Datenbank-Secret fehlt.');
        $password = trim((string) file_get_contents($file));
    }
    $config = [
        'app_name' => getenv('APP_NAME') ?: 'Zeitwerk',
        'app_url' => rtrim(getenv('APP_URL') ?: 'https://zeit.example.de', '/'),
        'company_name' => getenv('COMPANY_NAME') ?: 'Externe Firma',
        'environment' => getenv('APP_ENV') ?: 'production',
        'cookie_secure' => getenv('COOKIE_SECURE') !== '0',
        'trusted_proxies' => array_filter(explode(',', getenv('TRUSTED_PROXIES') ?: '')),
        'db_host' => getenv('DB_HOST') ?: 'mysql',
        'db_port' => (int) (getenv('DB_PORT') ?: 3306),
        'db_name' => getenv('DB_NAME') ?: 'zeitwerk',
        'db_user' => getenv('DB_USER') ?: 'zeitwerk',
        'db_password' => $password,
    ];
    if (is_file(ROOT . '/config/config.local.php')) {
        $config = array_replace($config, require ROOT . '/config/config.local.php');
    }
    if ($config['environment'] === 'production' && (!$config['cookie_secure'] || !str_starts_with($config['app_url'], 'https://'))) {
        throw new RuntimeException('Produktivbetrieb benötigt HTTPS und sichere Cookies.');
    }
    return $config;
}

function db(): PDO
{
    static $db;
    if (!$db) {
        $c = config();
        $db = new PDO("mysql:host={$c['db_host']};port={$c['db_port']};dbname={$c['db_name']};charset=utf8mb4", $c['db_user'], $c['db_password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        $db->exec("SET time_zone = '+00:00'");
    }
    return $db;
}

function query(string $sql, array $params = []): PDOStatement
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function row(string $sql, array $params = []): ?array
{
    return query($sql, $params)->fetch() ?: null;
}

function transaction(callable $action): mixed
{
    db()->beginTransaction();
    try {
        $result = $action();
        db()->commit();
        return $result;
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

// One company lock serializes writes for the small team, including role changes.
// This also protects empty rate ranges and the last-admin invariant.
function mutate(array $actor, callable $action): mixed
{
    return transaction(function () use ($actor, $action) {
        query('SELECT id FROM companies WHERE id=1 FOR UPDATE');
        $fresh = load_user((int) $actor['id']);
        if (!$fresh || !$fresh['active'] || $fresh['auth_version'] !== $actor['auth_version']) fail('Die Sitzung ist nicht mehr gültig.', 401);
        if ($fresh['must_change_password']) fail('Bitte zuerst das Passwort ändern.', 403);
        return $action($fresh);
    });
}

function audit(?int $actor, string $action, string $entity, ?int $id, ?array $before = null, ?array $after = null, string $reason = ''): void
{
    $redact = static function (?array $data): ?string {
        if ($data === null) return null;
        foreach (['password_hash','password','initial_password','auth_version'] as $key) unset($data[$key]);
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    };
    query('INSERT INTO audit_events(actor_id,action,entity,entity_id,before_json,after_json,reason) VALUES(?,?,?,?,?,?,?)',
        [$actor,$action,$entity,$id,$redact($before),$redact($after),$reason]);
}
