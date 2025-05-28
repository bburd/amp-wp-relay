<?php
/*
Plugin Name: AMP Server Status Relay
Description: Securely polls AMP instances locally and relays server status as JSON for external WordPress or web use.
Version: 1.7
Author: ChatGPT/bburd
Author URI: https://github.com/bburd
*/

// Load .env variables
$envFile = '/opt/amp-status/.env';
if (file_exists($envFile)) { 
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Cache-Control: no-store, no-cache, must-revalidate");

// Load relay configuration
$configFile = '/opt/amp-status/relay.json';
if (!file_exists($configFile)) {
    echo json_encode(['error' => 'Missing relay configuration file']);
    exit;
}

$config = json_decode(file_get_contents($configFile), true);
if (!is_array($config) || !isset($config['instances']) || !is_array($config['instances'])) {
    echo json_encode(['error' => 'Invalid relay configuration']);
    exit;
}

$instances = $config['instances'];
$sharedKey = $_ENV['AMP_SHARED_KEY'] ?? '';
define('LOGGING_ENABLED', isset($config['logging']) ? (bool)$config['logging'] : false);

function log_message($msg) {
    if (!LOGGING_ENABLED) return;
    $logFile = '/opt/amp-status/amp-relay.log';
    $timestamp = date('[Y-m-d H:i:s]');
    error_log("$timestamp $msg\n", 3, $logFile);
}

if (LOGGING_ENABLED) {
    log_message("Script loaded");
    log_message("Request started from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

$key = filter_input(INPUT_GET, 'key', FILTER_SANITIZE_STRING);
if (!$key || !hash_equals($sharedKey, $key)) {
    log_message("Unauthorized request attempt.");
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$cacheFile = '/opt/amp-status/cache.json';
$cacheTTL = 15; // seconds

if (file_exists($cacheFile)) {
    $fp = fopen($cacheFile, 'r');
    if ($fp && flock($fp, LOCK_SH)) {
        $cachedData = json_decode(stream_get_contents($fp), true);
        flock($fp, LOCK_UN);
        fclose($fp);
        if (isset($cachedData['time']) && (time() - $cachedData['time']) < $cacheTTL) {
            log_message("Returning file-based cache.");
            echo json_encode($cachedData['data']);
            exit;
        }
    }
}

function fetch_instance_status($name, $cfg) {
    $host = $cfg['host'];
    $username = $cfg['username'];
    $password = $cfg['password'];
    log_message("Fetching status for: $name ($host)");

    $loginPayload = json_encode([
        'username' => $username,
        'password' => $password,
        'token' => '',
        'rememberMe' => false
    ]);

    $opts = [
        'http' => [
            'header'  => "Content-type: application/json\r\nAccept: application/json",
            'method'  => 'POST',
            'content' => $loginPayload,
            'timeout' => 10
        ]
    ];

    $loginResp = @file_get_contents($host . 'API/Core/Login', false, stream_context_create($opts));
    $loginData = json_decode($loginResp, true);

    if (empty($loginData['sessionID'])) {
        log_message("Login failed for $name ($host)");
        return ['error' => 'Login failed', 'host' => $host];
    }

    $sessionID = $loginData['sessionID'];
    log_message("Login successful for $name - sessionID: $sessionID");

    $statusPayload = json_encode(['SESSIONID' => $sessionID]);

    $statusCtx = stream_context_create([
        'http' => [
            'header'  => "Content-type: application/json\r\nAccept: application/json\r\nAMP-SessionID: $sessionID",
            'method'  => 'POST',
            'content' => $statusPayload,
            'timeout' => 10
        ]
    ]);
    $statusResp = @file_get_contents($host . 'API/Core/GetStatus', false, $statusCtx);
    $statusData = json_decode($statusResp, true);

    if (!is_array($statusData)) {
        log_message("Status fetch failed for $name");
        return ['error' => 'Failed to retrieve status', 'host' => $host];
    }

    log_message("Uptime for $name: " . ($statusData['Uptime'] ?? 'N/A'));

    $appRunning = false;
    $runningCtx = stream_context_create([
        'http' => [
            'header'  => "Content-type: application/json\r\nAccept: application/json\r\nAMP-SessionID: $sessionID",
            'method'  => 'POST',
            'content' => $statusPayload,
            'timeout' => 5
        ]
    ]);
    $appRunningResp = @file_get_contents($host . 'API/Core/IsApplicationRunning', false, $runningCtx);
    if ($appRunningResp !== false) {
        $appRunningData = json_decode($appRunningResp, true);
        log_message("IsApplicationRunning response for $name: " . json_encode($appRunningData));
        if (isset($appRunningData['success']) && $appRunningData['success'] === true) {
            $appRunning = !empty($appRunningData['result']);
        }
    }

    if ($appRunning === false && !empty($statusData['Uptime']) && $statusData['Uptime'] !== '0:00:00:00') {
        log_message("Fallback detection: AppRunning=true for $name (based on Uptime)");
        $appRunning = true;
    } elseif ($appRunning === false) {
        log_message("Fallback failed: Uptime is zero for $name");
    }

    $response = [
        'Uptime'     => $statusData['Uptime'] ?? null,
        'State'      => $statusData['State'] ?? null,
        'Metrics'    => $statusData['Metrics'] ?? [],
        'AppRunning' => $appRunning
    ];

    if (!empty($cfg['ip_port']))   $response['IP_Port'] = $cfg['ip_port'];
    if (!empty($cfg['connect']))   $response['Connect'] = $cfg['connect'];
    if (!empty($cfg['alias']))     $response['Alias'] = $cfg['alias'];

    return $response;
}

$result = [];
foreach ($instances as $name => $cfg) {
    $result[$name] = fetch_instance_status($name, $cfg);
}

$fp = fopen($cacheFile, 'w');
if ($fp) {
    if (flock($fp, LOCK_EX)) {
        fwrite($fp, json_encode(['time' => time(), 'data' => $result]));
        flock($fp, LOCK_UN);
    } else {
        log_message("Failed to acquire lock for writing cache.");
    }
    fclose($fp);
} else {
    log_message("Failed to open cache file for writing.");
}

log_message("Returning response: " . json_encode($result));
echo json_encode($result);
