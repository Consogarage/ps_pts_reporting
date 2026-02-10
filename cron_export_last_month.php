<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Forbidden\n";
    exit(1);
}

require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';

$module = Module::getInstanceByName('ps_pts_reporting');
if (!$module || !$module->active) {
    fwrite(STDERR, "Module ps_pts_reporting is not available or inactive.\n");
    exit(1);
}

$payload = $module->getLastMonthCsvPayload();
$exportsDir = _PS_MODULE_DIR_ . 'ps_pts_reporting/exports';

if (!is_dir($exportsDir)) {
    if (!mkdir($exportsDir, 0755, true)) {
        fwrite(STDERR, "Failed to create exports directory.\n");
        exit(1);
    }
}

$targetPath = $exportsDir . '/' . $payload['filename'];
$bytes = file_put_contents($targetPath, $payload['content'], LOCK_EX);
if ($bytes === false) {
    fwrite(STDERR, "Failed to write export file.\n");
    exit(1);
}

echo "OK: {$targetPath} ({$bytes} bytes)\n";
