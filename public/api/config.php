<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$siteRoot = realpath(__DIR__ . '/..');
if ($siteRoot === false) send_json(['error' => 'site_root_not_found'], 500);

$key = arcst_get_key();
$store = arcst_storage_dir($siteRoot);

$globalPath = $store . DIRECTORY_SEPARATOR . 'global.count';
$userPath = $store . DIRECTORY_SEPARATOR . 'user_' . $key . '.count';

$global = arcst_read_int($globalPath, 0);
$total = arcst_read_int($userPath, 0);

send_json([
    'key' => $key,
    'global' => $global,
    'total' => $total,
]);
