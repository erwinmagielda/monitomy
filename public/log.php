<?php
/*
  Monitomy | Event Logger

  Purpose:
  Receives lightweight browser events from the demo surface and stores
  visit/click activity for review inside the Monitomy dashboard.

  Event types:
  - visit
  - click

  Storage:
  - visits table: ip, user_agent, geo
  - clicks table: ip, button
*/


// ------------------------------------------------------------
// CONTROL 01: ERROR SUPPRESSION
// ------------------------------------------------------------

ini_set('display_errors', '0');
error_reporting(E_ALL);


// ------------------------------------------------------------
// REQUEST HELPERS
// ------------------------------------------------------------

function getClientIp(): string {
  return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function failRequest(int $statusCode, string $message): void {
  http_response_code($statusCode);
  exit($message);
}

function readJsonPayload(): array {
  $rawInput = file_get_contents('php://input');
  $payload = json_decode($rawInput, true);

  if (!is_array($payload)) {
    failRequest(400, 'bad request');
  }

  return $payload;
}


// ------------------------------------------------------------
// CONTROL 02: LOGGER RATE LIMITING
// ------------------------------------------------------------

function enforceRateLimit(string $ip): void {
  $limitFile = sys_get_temp_dir() . '/monitomy_log_' . md5($ip);
  $now = time();
  $hits = [];

  if (file_exists($limitFile)) {
    $hits = json_decode((string)file_get_contents($limitFile), true) ?: [];
  }

  $hits = array_filter($hits, fn($timestamp) => $timestamp > $now - 10);
  $hits[] = $now;

  file_put_contents($limitFile, json_encode($hits));

  if (count($hits) > 25) {
    failRequest(429, 'rate limit');
  }
}


// ------------------------------------------------------------
// CONTROL 03: SAME-HOST ORIGIN / REFERER VALIDATION
// ------------------------------------------------------------

function requestCameFromSameHost(): bool {
  $host = $_SERVER['HTTP_HOST'] ?? '';
  $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
  $referer = $_SERVER['HTTP_REFERER'] ?? '';

  if ($host === '') {
    return false;
  }

  if ($origin === '' && $referer === '') {
    return false;
  }

  return (
    stripos($origin, $host) !== false ||
    stripos($referer, $host) !== false
  );
}


// ------------------------------------------------------------
// DATABASE CONNECTION
// ------------------------------------------------------------

function openDatabaseConnection(): mysqli {
  require __DIR__ . '/db_config.php';

  $mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

  if ($mysqli->connect_error) {
    failRequest(500, 'database connection failed');
  }

  $mysqli->set_charset('utf8mb4');

  return $mysqli;
}


// ------------------------------------------------------------
// CONTROL 04: PRIVATE / RESERVED IP HANDLING
// ------------------------------------------------------------

function lookupGeoLocation(string $ip): string {
  if (
    !filter_var($ip, FILTER_VALIDATE_IP) ||
    filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
  ) {
    return 'Local';
  }

  $context = stream_context_create([
    'http' => [
      'timeout' => 1
    ]
  ]);

  $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,country,city';
  $response = @file_get_contents($url, false, $context);

  if (!$response) {
    return 'Unknown';
  }

  $data = json_decode($response, true);

  if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
    return 'Unknown';
  }

  $country = $data['country'] ?? 'Unknown';
  $city = $data['city'] ?? 'Unknown';

  return $country . ', ' . $city;
}


// ------------------------------------------------------------
// CONTROL 05: EVENT TYPE NORMALISATION
// ------------------------------------------------------------

function normaliseEventType(array $payload): string {
  $type = strtolower((string)($payload['type'] ?? 'visit'));

  return in_array($type, ['visit', 'click'], true) ? $type : 'visit';
}


// ------------------------------------------------------------
// CONTROL 06: CLICK ACTION ALLOWLISTING
// ------------------------------------------------------------

function normaliseClickAction(array $payload): string {
  $allowedActions = [
    'spotify',
    'shop',
    'chains',
    'instagram',
    'bandcamp',
    'youtube',
    'subscribe',
    'contact'
  ];

  $action = trim((string)($payload['action'] ?? 'unknown'));
  $lowerAction = strtolower($action);

  if (strpos($lowerAction, 'album:') === 0) {
    return substr($action, 0, 120);
  }

  if (in_array($lowerAction, $allowedActions, true)) {
    return $lowerAction;
  }

  return 'other';
}


// ------------------------------------------------------------
// CONTROL 07: PREPARED DATABASE WRITES
// ------------------------------------------------------------

function storeClick(mysqli $mysqli, string $ip, string $action): void {
  $stmt = $mysqli->prepare("
    INSERT INTO clicks (ip, button)
    VALUES (?, ?)
  ");

  if (!$stmt) {
    failRequest(500, 'database write failed');
  }

  $stmt->bind_param('ss', $ip, $action);

  if (!$stmt->execute()) {
    failRequest(500, 'database write failed');
  }
}

function storeVisit(mysqli $mysqli, string $ip, string $userAgent, string $geo): void {
  $stmt = $mysqli->prepare("
    INSERT INTO visits (ip, user_agent, geo)
    VALUES (?, ?, ?)
  ");

  if (!$stmt) {
    failRequest(500, 'database write failed');
  }

  $stmt->bind_param('sss', $ip, $userAgent, $geo);

  if (!$stmt->execute()) {
    failRequest(500, 'database write failed');
  }
}


// ------------------------------------------------------------
// MAIN REQUEST FLOW
// ------------------------------------------------------------

$clientIp = getClientIp();

enforceRateLimit($clientIp);

if (!requestCameFromSameHost()) {
  failRequest(403, 'blocked');
}

$payload = readJsonPayload();
$eventType = normaliseEventType($payload);

$mysqli = openDatabaseConnection();
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

if ($eventType === 'click') {
  $action = normaliseClickAction($payload);
  storeClick($mysqli, $clientIp, $action);
  exit('CLICK OK');
}

$geo = lookupGeoLocation($clientIp);
storeVisit($mysqli, $clientIp, $userAgent, $geo);

exit('OK');