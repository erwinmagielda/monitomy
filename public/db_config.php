<?php
/*
  Monitomy | Configuration Loader

  Purpose:
  Loads database and dashboard credentials from credentials.json.

  Deployment note:
  - public/credentials.json contains dummy/example values in the public repo.
  - For a real deployment, replace those values with private server credentials.
  - Do not commit real production credentials.
*/


// ------------------------------------------------------------
// CONTROL 01: ERROR SUPPRESSION
// ------------------------------------------------------------

ini_set('display_errors', '0');
error_reporting(E_ALL);


// ------------------------------------------------------------
// CONFIGURATION PATH
// ------------------------------------------------------------

$credentialsPath = __DIR__ . '/credentials.json';


// ------------------------------------------------------------
// CONTROL 02: CONFIGURATION FILE LOADING
// ------------------------------------------------------------

$rawCredentials = @file_get_contents($credentialsPath);

if ($rawCredentials === false) {
  http_response_code(500);
  exit('configuration file missing');
}


// ------------------------------------------------------------
// CONTROL 03: JSON CONFIGURATION VALIDATION
// ------------------------------------------------------------

$credentials = json_decode($rawCredentials, true);

if (!is_array($credentials)) {
  http_response_code(500);
  exit('configuration file invalid');
}


// ------------------------------------------------------------
// CONTROL 04: REQUIRED KEY VALIDATION
// ------------------------------------------------------------

$requiredKeys = [
  'db_host',
  'db_name',
  'db_user',
  'db_pass',
  'admin_user',
  'admin_pass'
];

foreach ($requiredKeys as $key) {
  if (!array_key_exists($key, $credentials)) {
    http_response_code(500);
    exit('configuration value missing');
  }
}


// ------------------------------------------------------------
// EXPORT CONFIGURATION VALUES
// ------------------------------------------------------------

$DB_HOST = (string)$credentials['db_host'];
$DB_NAME = (string)$credentials['db_name'];
$DB_USER = (string)$credentials['db_user'];
$DB_PASS = (string)$credentials['db_pass'];

$ADMIN_USER = (string)$credentials['admin_user'];
$ADMIN_PASS = (string)$credentials['admin_pass'];