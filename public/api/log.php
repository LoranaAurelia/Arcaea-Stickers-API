<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$siteRoot = realpath(__DIR__ . '/..');
if ($siteRoot === false) send_json(['error' => 'site_root_not_found'], 500);

$key = arcst_get_key();
$store = arcst_storage_dir($siteRoot);

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: 'null', true);
$type = is_array($data) && isset($data['type']) ? (string)$data['type'] : 'unknown';

// Count only meaningful actions (copy/download), ignore unknown spam.
$allow = ['copy' => true, 'download' => true];
if (!isset($allow[$type])) {
    send_json(['ok' => true, 'key' => $key]);
}

$globalPath = $store . DIRECTORY_SEPARATOR . 'global.count';
$userPath = $store . DIRECTORY_SEPARATOR . 'user_' . $key . '.count';

$global = arcst_inc_int($globalPath, 1);
$total = arcst_inc_int($userPath, 1);

send_json([
    'ok' => true,
    'key' => $key,
    'global' => $global,
    'total' => $total,
]);
