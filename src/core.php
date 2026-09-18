<?php
declare(strict_types=1);

final class AppError extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422) { parent::__construct($message); }
}
function fail(string $message, int $status = 422): never { throw new AppError($message, $status); }
function e(mixed $text): string { return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function today(): string { return (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d'); }
function valid_date(mixed $value, string $label = 'Datum'): string
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) fail("$label ist ungültig.");
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value || $value < '2000-01-01' || $value > '2099-12-31') fail("$label ist ungültig.");
    return $value;
}
function integer(mixed $value, string $label, int $min = 1, int $max = 2147483647): int
{
    if ((!is_int($value) && !is_string($value)) || !preg_match('/^-?\d+$/D', (string)$value)) fail("$label muss eine ganze Zahl sein.");
    $n = filter_var($value, FILTER_VALIDATE_INT);
    if ($n === false || $n < $min || $n > $max) fail("$label muss zwischen $min und $max liegen.");
    return $n;
}
function text_value(mixed $value, string $label, int $max, bool $required = true): string
{
    if (!is_string($value)) fail("$label ist ungültig.");
    $value = trim($value);
    if (!preg_match('//u', $value) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) fail("$label enthält ungültige Zeichen.");
    $length = preg_match_all('/./us', $value);
    if (($required && $value === '') || $length > $max) fail("$label: bitte " . ($required ? '1 bis ' : 'höchstens ') . "$max Zeichen eingeben.");
    return $value;
}
function bool_value(mixed $value): bool
{
    if (!in_array($value, [true,false,0,1,'0','1'], true)) fail('Ungültiger Ja/Nein-Wert.');
    return (bool)$value;
}
function cents(mixed $value, int $maximum = 100000000000): int
{
    if (!is_string($value) && !is_int($value)) fail('Bitte einen gültigen Euro-Betrag eingeben.');
    $value = str_replace(',', '.', trim((string)$value));
    if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', $value, $m)) fail('Betrag ohne Tausendertrennzeichen und mit höchstens zwei Nachkommastellen eingeben.');
    $result = (int)$m[1] * 100 + (int)str_pad($m[2] ?? '', 2, '0');
    if ($result > $maximum) fail('Der Betrag ist zu groß.');
    return $result;
}
function amount(int $minutes, int $rate): int { return intdiv($minutes * $rate + 30, 60); }
function check_version(array $entry, mixed $version): void
{
    if ((int)$entry['version'] !== integer($version, 'Version')) fail('Dieser Datensatz wurde inzwischen geändert. Bitte neu laden.', 409);
}
function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}
function json_input(): array
{
    if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) fail('JSON-Anfrage erforderlich.', 415);
    $raw = file_get_contents('php://input', false, null, 0, 1048577);
    if (strlen($raw) > 1048576) fail('Anfrage zu groß.', 413);
    try { $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); }
    catch (JsonException) { fail('Ungültige JSON-Anfrage.', 400); }
    if (!is_array($input) || array_is_list($input)) fail('Ungültige Anfrage.', 400);
    return $input;
}
function ip_in_network(string $ip, string $network): bool
{
    [$address,$bits] = array_pad(explode('/', trim($network), 2), 2, null);
    $a = @inet_pton($ip); $b = @inet_pton($address);
    if ($a === false || $b === false || strlen($a) !== strlen($b)) return false;
    $bits = $bits === null ? strlen($a)*8 : (ctype_digit($bits) ? (int)$bits : -1);
    if ($bits < 0 || $bits > strlen($a)*8) return false;
    $bytes = intdiv($bits, 8); $remaining = $bits % 8;
    return substr($a,0,$bytes) === substr($b,0,$bytes) && (!$remaining || ((ord($a[$bytes]) ^ ord($b[$bytes])) & (255 << (8-$remaining))) === 0);
}
function trusted_ip(string $ip): bool
{
    foreach (config()['trusted_proxies'] as $network) if (ip_in_network($ip, $network)) return true;
    return false;
}
function client_ip(): string
{
    $peer = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!trusted_ip($peer)) return $peer;
    $hops = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if (count($hops)>16) return $peer;
    for ($i=count($hops)-1; $i>=0; $i--) {
        if (!filter_var($hops[$i], FILTER_VALIDATE_IP)) return $peer;
        $peer = $hops[$i];
        if (!trusted_ip($peer)) break;
    }
    return $peer;
}
function secure_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
    header('Cache-Control: no-store, private');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}
function validate_host(): void
{
    $expected = parse_url(config()['app_url'], PHP_URL_HOST);
    $host = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    if (!$host || strcasecmp($host, (string)$expected)) fail('Nicht zugelassener Hostname.', 400);
}
