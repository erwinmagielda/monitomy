<?php
/*
  Monitomy | Traffic Signal Monitor

  Purpose:
  Provides visibility into visit activity, click events, bot indicators,
  and engagement signals collected through a PHP/MySQL logging workflow.

  Data sources:
  - visits table: ip, ts, user_agent, geo
  - clicks table: ip, ts, button
  - ip_geo table: ip, country, city, last_updated
*/


// ------------------------------------------------------------
// CONTROL 01: ERROR SUPPRESSION
// ------------------------------------------------------------

ini_set('display_errors', '0');
error_reporting(E_ALL);


// ------------------------------------------------------------
// RESPONSE HELPERS
// ------------------------------------------------------------

function failRequest(int $statusCode, string $message): void {
  http_response_code($statusCode);
  exit($message);
}

function h($value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function jsonForHtml($value): string {
  return json_encode(
    $value,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
  );
}


// ------------------------------------------------------------
// REQUEST HELPERS
// ------------------------------------------------------------

function getClientIp(): string {
  return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}


// ------------------------------------------------------------
// CONTROL 02: ADMIN RATE LIMITING
// ------------------------------------------------------------

function enforceAdminRateLimit(string $ip): void {
  $limitFile = sys_get_temp_dir() . '/monitomy_admin_' . md5($ip);
  $now = time();
  $attempts = [];

  if (file_exists($limitFile)) {
    $attempts = json_decode((string)file_get_contents($limitFile), true) ?: [];
  }

  $attempts = array_filter($attempts, fn($timestamp) => $timestamp > $now - 300);

  if (count($attempts) >= 10) {
    failRequest(429, 'too many attempts');
  }

  $attempts[] = $now;
  file_put_contents($limitFile, json_encode($attempts));
}


// ------------------------------------------------------------
// CONTROL 03: BASIC AUTHENTICATION
// CONTROL 04: CONSTANT-TIME CREDENTIAL COMPARISON
// ------------------------------------------------------------

function requireBasicAuthentication(string $expectedUser, string $expectedPass): void {
  $providedUser = $_SERVER['PHP_AUTH_USER'] ?? null;
  $providedPass = $_SERVER['PHP_AUTH_PW'] ?? null;

  if ($providedUser === null || $providedPass === null) {
    header('WWW-Authenticate: Basic realm="Monitomy Traffic Signal Monitor"');
    header('HTTP/1.0 401 Unauthorized');
    exit;
  }

  $validUser = hash_equals($expectedUser, (string)$providedUser);
  $validPass = hash_equals($expectedPass, (string)$providedPass);

  if (!$validUser || !$validPass) {
    failRequest(403, 'forbidden');
  }
}


// ------------------------------------------------------------
// DATABASE CONNECTION
// ------------------------------------------------------------

function openDatabaseConnection(): mysqli {
  require __DIR__ . '/db_config.php';

  requireBasicAuthentication($ADMIN_USER, $ADMIN_PASS);

  $mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

  if ($mysqli->connect_error) {
    failRequest(500, 'database connection failed');
  }

  $mysqli->set_charset('utf8mb4');

  return $mysqli;
}


// ------------------------------------------------------------
// DATABASE READ HELPERS
// ------------------------------------------------------------

function fetchScalar(mysqli $mysqli, string $query): int {
  $result = $mysqli->query($query);

  if (!$result) {
    return 0;
  }

  $row = $result->fetch_assoc();

  return (int)($row['c'] ?? 0);
}

function fetchValue(mysqli $mysqli, string $query, string $field): ?string {
  $result = $mysqli->query($query);

  if (!$result) {
    return null;
  }

  $row = $result->fetch_assoc();

  return isset($row[$field]) ? (string)$row[$field] : null;
}

function fetchRows(mysqli $mysqli, string $query): array {
  $result = $mysqli->query($query);

  if (!$result) {
    return [];
  }

  $rows = [];

  while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
  }

  return $rows;
}


// ------------------------------------------------------------
// BOT FILTERING
// ------------------------------------------------------------

function botSqlCondition(): string {
  return "
    user_agent LIKE '%bot%'
    OR user_agent LIKE '%crawl%'
    OR user_agent LIKE '%spider%'
  ";
}

function realUserSqlCondition(): string {
  return "
    user_agent NOT LIKE '%bot%'
    AND user_agent NOT LIKE '%crawl%'
    AND user_agent NOT LIKE '%spider%'
  ";
}


// ------------------------------------------------------------
// CORE METRICS
// ------------------------------------------------------------

function getCoreMetrics(mysqli $mysqli): array {
  $totalVisits = fetchScalar($mysqli, "
    SELECT COUNT(*) AS c
    FROM visits
  ");

  $totalClicks = fetchScalar($mysqli, "
    SELECT COUNT(*) AS c
    FROM clicks
  ");

  $uniqueVisitors = fetchScalar($mysqli, "
    SELECT COUNT(DISTINCT ip) AS c
    FROM visits
  ");

  $shopClicks = fetchScalar($mysqli, "
    SELECT COUNT(*) AS c
    FROM clicks
    WHERE button = 'shop' OR button = 'Shop'
  ");

  $realUsers = fetchScalar($mysqli, "
    SELECT COUNT(DISTINCT ip) AS c
    FROM visits
    WHERE " . realUserSqlCondition()
  );

  $botVisitors = fetchScalar($mysqli, "
    SELECT COUNT(DISTINCT ip) AS c
    FROM visits
    WHERE " . botSqlCondition()
  );

  $conversionRate = $uniqueVisitors > 0
    ? round(100 * $shopClicks / $uniqueVisitors, 1)
    : 0;

  return [
    'totalVisits' => $totalVisits,
    'totalClicks' => $totalClicks,
    'uniqueVisitors' => $uniqueVisitors,
    'shopClicks' => $shopClicks,
    'realUsers' => $realUsers,
    'botVisitors' => $botVisitors,
    'conversionRate' => $conversionRate
  ];
}


// ------------------------------------------------------------
// CHART DATA
// ------------------------------------------------------------

function getLatestVisitDate(mysqli $mysqli): string {
  $latestTimestamp = fetchValue($mysqli, "
    SELECT MAX(ts) AS latest_ts
    FROM visits
  ", 'latest_ts');

  if (!$latestTimestamp) {
    return date('Y-m-d');
  }

  return date('Y-m-d', strtotime($latestTimestamp));
}

function getVisitChartData(mysqli $mysqli): array {
  $latestDate = getLatestVisitDate($mysqli);

  $labels = [];
  $total = [];
  $real = [];
  $bots = [];

  for ($i = 13; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime($latestDate . " -{$i} days"));
    $start = $date . ' 00:00:00';

    $labels[] = $date;

    $total[] = fetchScalar($mysqli, "
      SELECT COUNT(*) AS c
      FROM visits
      WHERE ts >= '{$start}'
        AND ts < DATE_ADD('{$start}', INTERVAL 1 DAY)
    ");

    $real[] = fetchScalar($mysqli, "
      SELECT COUNT(*) AS c
      FROM visits
      WHERE ts >= '{$start}'
        AND ts < DATE_ADD('{$start}', INTERVAL 1 DAY)
        AND " . realUserSqlCondition()
    );

    $bots[] = fetchScalar($mysqli, "
      SELECT COUNT(*) AS c
      FROM visits
      WHERE ts >= '{$start}'
        AND ts < DATE_ADD('{$start}', INTERVAL 1 DAY)
        AND (" . botSqlCondition() . ")
    ");
  }

  return [
    'labels' => $labels,
    'total' => $total,
    'real' => $real,
    'bots' => $bots
  ];
}


// ------------------------------------------------------------
// CONTROL 05: PRIVATE / RESERVED IP HANDLING
// ------------------------------------------------------------

function getGeo(mysqli $mysqli, string $ip): array {
  static $memoryCache = [];

  if (isset($memoryCache[$ip])) {
    return $memoryCache[$ip];
  }

  if (
    !filter_var($ip, FILTER_VALIDATE_IP) ||
    filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
  ) {
    return $memoryCache[$ip] = [
      'country' => 'local',
      'city' => '-'
    ];
  }

  $select = $mysqli->prepare("
    SELECT country, city
    FROM ip_geo
    WHERE ip = ?
    LIMIT 1
  ");

  if ($select) {
    $select->bind_param('s', $ip);
    $select->execute();

    $cachedGeo = $select->get_result()->fetch_assoc();

    if ($cachedGeo) {
      return $memoryCache[$ip] = [
        'country' => $cachedGeo['country'] ?? '-',
        'city' => $cachedGeo['city'] ?? '-'
      ];
    }
  }

  $context = stream_context_create([
    'http' => [
      'timeout' => 1
    ]
  ]);

  $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=country,city';
  $response = @file_get_contents($url, false, $context);

  if (!$response) {
    return $memoryCache[$ip] = [
      'country' => '-',
      'city' => '-'
    ];
  }

  $data = json_decode($response, true);

  $country = $data['country'] ?? '-';
  $city = $data['city'] ?? '-';

  $insert = $mysqli->prepare("
    INSERT INTO ip_geo (ip, country, city)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE
      country = VALUES(country),
      city = VALUES(city),
      last_updated = CURRENT_TIMESTAMP
  ");

  if ($insert) {
    $insert->bind_param('sss', $ip, $country, $city);
    $insert->execute();
  }

  return $memoryCache[$ip] = [
    'country' => $country,
    'city' => $city
  ];
}


// ------------------------------------------------------------
// CLICK LABELS
// ------------------------------------------------------------

function formatClickAction(string $button): string {
  $raw = trim($button);
  $normalised = strtolower($raw);

  if (strpos($normalised, 'album:') === 0) {
    return 'Album → ' . substr($raw, 6);
  }

  $labels = [
    'shop' => 'Shop',
    'spotify' => 'Spotify',
    'youtube' => 'YouTube',
    'bandcamp' => 'Bandcamp',
    'instagram' => 'Instagram',
    'chains' => 'Chains',
    'subscribe' => 'Subscribe',
    'contact' => 'Contact'
  ];

  return $labels[$normalised] ?? 'Other → ' . $raw;
}


// ------------------------------------------------------------
// IMPLEMENTED CONTROL REGISTER
// ------------------------------------------------------------

function getImplementedControls(): array {
  return [
    [
      'id' => 'C-01',
      'control' => 'Credential Separation',
      'scope' => 'Config',
      'purpose' => 'Keeps real database and admin credentials outside Git-tracked source code.',
      'state' => 'Implemented'
    ],
    [
      'id' => 'C-02',
      'control' => 'Web Access Protection',
      'scope' => 'Apache',
      'purpose' => 'Blocks direct browser access to credential and configuration files.',
      'state' => 'Implemented'
    ],
    [
      'id' => 'C-03',
      'control' => 'Admin Rate Limiting',
      'scope' => 'Monitor',
      'purpose' => 'Limits repeated dashboard access attempts from the same IP address.',
      'state' => 'Implemented'
    ],
    [
      'id' => 'C-04',
      'control' => 'Basic Authentication',
      'scope' => 'Monitor',
      'purpose' => 'Requires admin credentials before private traffic data is displayed.',
      'state' => 'Implemented'
    ],
    [
      'id' => 'C-05',
      'control' => 'Constant-Time Comparison',
      'scope' => 'Monitor',
      'purpose' => 'Uses hash_equals() when comparing submitted admin credentials.',
      'state' => 'Implemented'
    ],
    [
      'id' => 'C-06',
      'control' => 'Logger Rate Limiting',
      'scope' => 'Logger',
      'purpose' => 'Limits repeated event submissions from the same IP address.',
      'state' => 'Implemented'
    ],
    [
      'id' => 'C-07',
      'control' => 'Same-Host Request Validation',
      'scope' => 'Logger',
      'purpose' => 'Rejects logging requests that do not appear to originate from the deployed host.',
      'state' => 'Implemented'
    ],
    [
      'id' => 'C-08',
      'control' => 'JSON Payload Validation',
      'scope' => 'Logger',
      'purpose' => 'Rejects malformed event payloads before processing.',
      'state' => 'Implemented'
    ],
    [
      'id' => 'C-09',
      'control' => 'Event Normalisation',
      'scope' => 'Logger',
      'purpose' => 'Restricts accepted event types to known visit and click events.',
      'state' => 'Implemented'
    ],
    [
      'id' => 'C-10',
      'control' => 'Click Action Allowlisting',
      'scope' => 'Logger',
      'purpose' => 'Stores unknown click actions as other instead of trusting arbitrary input.',
      'state' => 'Implemented'
    ],
    [
      'id' => 'C-11',
      'control' => 'Prepared Database Writes',
      'scope' => 'Database',
      'purpose' => 'Uses prepared statements when inserting visit and click records.',
      'state' => 'Implemented'
    ],
    [
      'id' => 'C-12',
      'control' => 'Output Encoding',
      'scope' => 'Monitor',
      'purpose' => 'Escapes dashboard values before rendering them into HTML.',
      'state' => 'Implemented'
    ],
    [
      'id' => 'C-13',
      'control' => 'Safe JSON Serialisation',
      'scope' => 'Monitor',
      'purpose' => 'Encodes chart data safely before injecting it into inline JavaScript.',
      'state' => 'Implemented'
    ],
    [
      'id' => 'C-14',
      'control' => 'Private IP Handling',
      'scope' => 'Geolocation',
      'purpose' => 'Avoids external geolocation lookups for private, reserved, or invalid IPs.',
      'state' => 'Implemented'
    ],
    [
      'id' => 'C-15',
      'control' => 'Database Error Suppression',
      'scope' => 'System',
      'purpose' => 'Prevents raw database errors from being displayed to visitors.',
      'state' => 'Implemented'
    ]
  ];
}


// ------------------------------------------------------------
// DASHBOARD DATA
// ------------------------------------------------------------

$clientIp = getClientIp();

enforceAdminRateLimit($clientIp);

$mysqli = openDatabaseConnection();

$metrics = getCoreMetrics($mysqli);
$chart = getVisitChartData($mysqli);
$implementedControls = getImplementedControls();

$topVisitors = fetchRows($mysqli, "
  SELECT ip, COUNT(*) AS c
  FROM visits
  GROUP BY ip
  ORDER BY c DESC
  LIMIT 10
");

$botActivity = fetchRows($mysqli, "
  SELECT ip, user_agent, COUNT(*) AS c
  FROM visits
  WHERE " . botSqlCondition() . "
  GROUP BY ip, user_agent
  ORDER BY c DESC
  LIMIT 10
");

$recentClicks = fetchRows($mysqli, "
  SELECT ts, button, ip
  FROM clicks
  ORDER BY ts DESC
  LIMIT 20
");

$recentVisits = fetchRows($mysqli, "
  SELECT ts, ip, geo
  FROM visits
  ORDER BY ts DESC
  LIMIT 20
");
?>

<!doctype html>
<html lang="en">
<head>
  <!-- ----------------------------------------------------------
       DOCUMENT METADATA
  ----------------------------------------------------------- -->

  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">

  <title>Monitomy | Traffic Signal Monitor</title>


  <!-- ----------------------------------------------------------
       DASHBOARD STYLES
  ----------------------------------------------------------- -->

  <style>
    :root {
      --colour-background: #0b0b0b;
      --colour-card: #151515;
      --colour-panel: #101010;
      --colour-line: #2a2a2a;
      --colour-text: #eee;
      --colour-muted: #aaa;
      --colour-accent: #fed002;
      --colour-axis: #444;
      --colour-real: #4caf50;
      --colour-bot: #f44336;

      --radius-card: 12px;
      --radius-panel: 10px;
      --radius-pill: 999px;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding: 24px 24px 24px 12px;
      background: var(--colour-background);
      color: var(--colour-text);
      font: 14px/1.4 system-ui, Arial, sans-serif;
    }

    .grid {
      display: grid;
      gap: 16px;
      max-width: 1100px;
      margin: 0 auto;
    }

    .card {
      padding: 16px;
      border: 1px solid var(--colour-line);
      border-radius: var(--radius-card);
      background: var(--colour-card);
    }

    .full {
      grid-column: 1 / -1;
    }

    .dashboard-hero {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 10px 18px 18px;
      text-align: center;
    }

    .dashboard-logo {
      width: 160px;
      height: auto;
      max-height: 160px;
      object-fit: contain;
      margin-bottom: 10px;
    }

    .dashboard-subtitle {
      color: var(--colour-text);
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1.8px;
    }

    .dashboard-description {
      max-width: 620px;
      margin: 8px auto 0;
      color: var(--colour-muted);
      font-size: 13px;
      line-height: 1.5;
    }

    .kpis {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 12px;
    }

    .kpi {
      padding: 12px;
      border: 1px solid var(--colour-line);
      border-radius: var(--radius-panel);
      background: var(--colour-panel);
      text-align: center;
    }

    .kpi b {
      display: block;
      margin-top: 6px;
      font-size: 20px;
      text-align: center;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th,
    td {
      padding: 6px;
      border-bottom: 1px solid var(--colour-line);
      vertical-align: top;
    }

    th {
      color: var(--colour-accent);
      font-weight: 600;
      text-align: left;
    }

    h1 {
      margin: 0 0 12px;
    }

    h2 {
      margin: 0 0 10px;
      color: var(--colour-accent);
    }

    .muted {
      color: var(--colour-muted);
      font-size: 12px;
    }

    .chart-title {
      padding-top: 50px;
      text-align: center;
      text-transform: uppercase;
      font-size: 11px;
      letter-spacing: 1px;
    }

    .chart-legend {
      margin-top: 6px;
    }

    .legend-item {
      margin-right: 10px;
    }

    .legend-total {
      color: var(--colour-accent);
    }

    .legend-real {
      color: var(--colour-real);
    }

    .legend-bots {
      color: var(--colour-bot);
    }

    .status-implemented {
      display: inline-block;
      padding: 3px 8px;
      border: 1px solid rgba(76, 175, 80, 0.5);
      border-radius: var(--radius-pill);
      color: var(--colour-real);
      font-size: 11px;
      font-weight: 700;
      line-height: 1;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      white-space: nowrap;
    }

    .control-id {
      color: var(--colour-muted);
      font-size: 12px;
      white-space: nowrap;
    }

    .control-scope {
      color: var(--colour-muted);
      white-space: nowrap;
    }

    summary {
      cursor: pointer;
      font-weight: 600;
      transition: opacity 0.2s ease;
    }

    summary h2 {
      display: inline;
    }

    details[open] summary {
      margin-bottom: 10px;
    }

    summary::-webkit-details-marker {
      display: none;
    }

    canvas {
      width: 100%;
      margin-top: 16px;
    }

    .site-footer {
      width: 100%;
      max-width: 1100px;
      margin: 24px auto 0;
      border-top: 1px solid rgba(255, 255, 255, 0.06);
      color: var(--colour-accent);
    }

    .footer-inner {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 16px 20px;
      text-align: center;
    }

    .footer-link,
    .footer-link:visited {
      color: var(--colour-accent);
      font-size: 11px;
      line-height: 1.6;
      letter-spacing: 0.3px;
      text-decoration: none;
      opacity: 0.85;
    }

    .footer-link:hover,
    .footer-link:active {
      color: var(--colour-accent);
      text-decoration: underline;
      opacity: 1;
    }

    @media (max-width: 720px) {
      body {
        padding: 16px 12px;
      }

      .dashboard-logo {
        width: 120px;
        max-height: 120px;
      }

      table {
        font-size: 12px;
      }

      th,
      td {
        padding: 6px 4px;
      }
    }
  </style>
</head>

<body>

  <div class="grid">

    <!-- --------------------------------------------------------
         MONITOMY HEADER
    --------------------------------------------------------- -->

    <section class="full dashboard-hero" aria-label="Monitomy Traffic Signal Monitor">
      <img
        src="monitomy.webp"
        alt="Monitomy logo"
        class="dashboard-logo"
        width="160"
        decoding="async"
      >

      <div class="dashboard-subtitle">
        Traffic Signal Monitor
      </div>

      <p class="dashboard-description">
        PHP/MySQL traffic monitor for visits, clicks, bot indicators, and engagement signals.
      </p>
    </section>


    <!-- --------------------------------------------------------
         TRAFFIC OVERVIEW
    --------------------------------------------------------- -->

    <details class="card full" open>
      <summary><h2>Traffic Overview</h2></summary>

      <div class="kpis">
        <div class="kpi">
          <div>Total Visits</div>
          <b><?= (int)$metrics['totalVisits'] ?></b>
        </div>

        <div class="kpi">
          <div>Unique Visitors</div>
          <b><?= (int)$metrics['uniqueVisitors'] ?></b>
        </div>

        <div class="kpi">
          <div>Real Users</div>
          <b><?= (int)$metrics['realUsers'] ?></b>
        </div>

        <div class="kpi">
          <div>Bot Visitors</div>
          <b><?= (int)$metrics['botVisitors'] ?></b>
        </div>

        <div class="kpi">
          <div>Total Clicks</div>
          <b><?= (int)$metrics['totalClicks'] ?></b>
        </div>

        <div class="kpi">
          <div>Shop Clicks</div>
          <b><?= (int)$metrics['shopClicks'] ?></b>
        </div>

        <div class="kpi">
          <div>Conversion Rate</div>
          <b><?= h($metrics['conversionRate']) ?>%</b>
        </div>
      </div>

      <div class="muted chart-title">
        Visits over last 14 days
      </div>

      <div class="muted chart-legend">
        <span class="legend-item legend-total">■ Total</span>
        <span class="legend-item legend-real">■ Real</span>
        <span class="legend-item legend-bots">■ Bots</span>
      </div>

      <canvas id="visitsChart"></canvas>
    </details>


    <!-- --------------------------------------------------------
         TOP VISITORS
    --------------------------------------------------------- -->

    <details class="card">
      <summary><h2>Top Visitors</h2></summary>

      <table>
        <thead>
          <tr>
            <th>IP</th>
            <th>Visits</th>
          </tr>
        </thead>

        <tbody>
          <?php foreach ($topVisitors as $visitor): ?>
            <?php $geo = getGeo($mysqli, (string)$visitor['ip']); ?>
            <tr>
              <td>
                <?= h($visitor['ip']) ?><br>
                <span class="muted"><?= h($geo['country']) ?>, <?= h($geo['city']) ?></span>
              </td>
              <td><?= (int)$visitor['c'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </details>


    <!-- --------------------------------------------------------
         BOT TRAFFIC
    --------------------------------------------------------- -->

    <details class="card">
      <summary><h2>Bot Activity</h2></summary>

      <table>
        <thead>
          <tr>
            <th>IP</th>
            <th>Hits</th>
            <th>Agent</th>
          </tr>
        </thead>

        <tbody>
          <?php foreach ($botActivity as $bot): ?>
            <?php $geo = getGeo($mysqli, (string)$bot['ip']); ?>
            <tr>
              <td>
                <?= h($bot['ip']) ?><br>
                <span class="muted"><?= h($geo['country']) ?>, <?= h($geo['city']) ?></span>
              </td>
              <td><?= (int)$bot['c'] ?></td>
              <td class="muted"><?= h($bot['user_agent']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </details>


    <!-- --------------------------------------------------------
         RECENT CLICKS
    --------------------------------------------------------- -->

    <details class="card">
      <summary><h2>Recent Clicks</h2></summary>

      <table>
        <thead>
          <tr>
            <th>Time</th>
            <th>Action</th>
            <th>IP</th>
          </tr>
        </thead>

        <tbody>
          <?php foreach ($recentClicks as $click): ?>
            <?php $geo = getGeo($mysqli, (string)$click['ip']); ?>
            <tr>
              <td><?= h($click['ts']) ?></td>
              <td><?= h(formatClickAction((string)$click['button'])) ?></td>
              <td>
                <?= h($click['ip']) ?><br>
                <span class="muted"><?= h($geo['country']) ?>, <?= h($geo['city']) ?></span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </details>


    <!-- --------------------------------------------------------
         RECENT VISITS
    --------------------------------------------------------- -->

    <details class="card">
      <summary><h2>Recent Visits</h2></summary>

      <table>
        <thead>
          <tr>
            <th>Time</th>
            <th>IP</th>
          </tr>
        </thead>

        <tbody>
          <?php foreach ($recentVisits as $visit): ?>
            <tr>
              <td><?= h($visit['ts']) ?></td>
              <td>
                <?= h($visit['ip']) ?><br>
                <span class="muted"><?= h($visit['geo'] ?? '') ?></span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </details>


    <!-- --------------------------------------------------------
         IMPLEMENTED CONTROLS
    --------------------------------------------------------- -->

    <details class="card full">
      <summary><h2>Implemented Controls</h2></summary>

      <p class="muted" style="margin-bottom:10px;">
        Security and reliability controls implemented across the monitor, public logger,
        configuration loader, and Apache access rules.
      </p>

      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Control</th>
            <th>Scope</th>
            <th>Purpose</th>
            <th>State</th>
          </tr>
        </thead>

        <tbody>
          <?php foreach ($implementedControls as $control): ?>
            <tr>
              <td class="control-id"><?= h($control['id']) ?></td>
              <td><?= h($control['control']) ?></td>
              <td class="control-scope"><?= h($control['scope']) ?></td>
              <td class="muted"><?= h($control['purpose']) ?></td>
              <td><span class="status-implemented"><?= h($control['state']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </details>

  </div>


  <!-- ----------------------------------------------------------
       FOOTER
  ----------------------------------------------------------- -->

  <footer class="site-footer" role="contentinfo">
    <div class="footer-inner">
      <a
        href="https://www.linkedin.com/in/erwinmagielda"
        class="footer-link"
        target="_blank"
        rel="noopener noreferrer"
      >
        Built by Erwin Magielda © 2026 — All Rights Reserved
      </a>
    </div>
  </footer>


  <!-- ----------------------------------------------------------
       DASHBOARD CHART
  ----------------------------------------------------------- -->

  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const labels = <?= jsonForHtml($chart['labels']) ?>;
      const total = <?= jsonForHtml($chart['total']) ?>;
      const real = <?= jsonForHtml($chart['real']) ?>;
      const bots = <?= jsonForHtml($chart['bots']) ?>;

      const canvas = document.getElementById("visitsChart");

      if (!canvas) {
        return;
      }

      const ctx = canvas.getContext("2d");

      canvas.width = canvas.offsetWidth;
      canvas.height = 180;

      const width = canvas.width;
      const height = canvas.height;
      const padding = 30;
      const maxValue = Math.max(...total, ...real, ...bots, 1);

      function drawLine(data, colour) {
        ctx.strokeStyle = colour;
        ctx.lineWidth = 2;
        ctx.beginPath();

        data.forEach((value, index) => {
          const ratio = data.length > 1 ? index / (data.length - 1) : 0.5;
          const x = padding + ratio * (width - padding * 2);
          const y = height - padding - (value / maxValue) * (height - padding * 2);

          if (index === 0) {
            ctx.moveTo(x, y);
            return;
          }

          ctx.lineTo(x, y);
        });

        ctx.stroke();
      }

      function drawGrid() {
        ctx.strokeStyle = "#2a2a2a";

        for (let index = 0; index <= 4; index++) {
          const y = padding + ((height - padding * 2) / 4) * index;

          ctx.beginPath();
          ctx.moveTo(padding, y);
          ctx.lineTo(width, y);
          ctx.stroke();
        }
      }

      function drawAxes() {
        ctx.strokeStyle = "#444";

        ctx.beginPath();
        ctx.moveTo(padding, padding);
        ctx.lineTo(padding, height - padding);
        ctx.stroke();

        ctx.beginPath();
        ctx.moveTo(padding, height - padding);
        ctx.lineTo(width, height - padding);
        ctx.stroke();
      }

      function drawYAxisLabels() {
        ctx.fillStyle = "#aaa";
        ctx.font = "8px system-ui";

        for (let index = 0; index <= 4; index++) {
          const value = Math.round((maxValue / 4) * (4 - index));
          const y = padding + ((height - padding * 2) / 4) * index;

          ctx.fillText(value, 5, y + 3);
        }
      }

      function drawXAxisLabels() {
        ctx.fillStyle = "#aaa";
        ctx.font = "8px system-ui";

        labels.forEach((label, index) => {
          const ratio = labels.length > 1 ? index / (labels.length - 1) : 0.5;
          const x = padding + ratio * (width - padding * 2);
          const date = new Date(label);

          const formatted = date.toLocaleDateString("en-GB", {
            day: "2-digit",
            month: "2-digit"
          });

          ctx.fillText(formatted, x - 15, height - 5);
        });
      }

      drawGrid();
      drawAxes();

      drawLine(total, "#fed002");
      drawLine(real, "#4caf50");
      drawLine(bots, "#f44336");

      drawYAxisLabels();
      drawXAxisLabels();
    });
  </script>

</body>
</html>