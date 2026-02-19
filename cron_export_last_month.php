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
$saved = false;
if (method_exists($module, 'saveReportFileToExports')) {
    $saved = $module->saveReportFileToExports($payload['filename'], $payload['content']);
}

if ($saved === false) {
    fwrite(STDERR, "Failed to write export file in exports directory.\n");
    exit(1);
}

$targetPath = $saved['path'];
$bytes = (int) $saved['bytes'];

$sent = 0;
if (method_exists($module, 'sendMonthlyReportToConfiguredEmails')) {
    $sent = (int) $module->sendMonthlyReportToConfiguredEmails($payload['filename'], $payload['content']);
}

echo "OK: {$targetPath} ({$bytes} bytes), emails sent: {$sent}\n";
