<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script doit être exécuté en ligne de commande.\n");
    exit(2);
}

$rootDir = dirname(__DIR__, 3);
require_once $rootDir . '/config/config.inc.php';
require_once dirname(__DIR__) . '/classes/KpiReportService.php';

$year = isset($argv[1]) ? (int) $argv[1] : 2026;
$month = isset($argv[2]) ? (int) $argv[2] : 7;
$rate = isset($argv[3]) ? (float) $argv[3] : 1.06;

$expected = [
    'C3921' => ['mb_ht' => 0.00, 'marge_nette' => 0.00],
    'C3959' => ['mb_ht' => -227.03, 'marge_nette' => -214.18],
    'C3986' => ['mb_ht' => -64.80, 'marge_nette' => -61.13],
];

$service = new KpiReportService(Context::getContext());
$rows = $service->getInvoicedKpisForMonth($year, $month, $rate);
$actual = [];

foreach ($rows as $row) {
    $reference = (string) $row['order_reference'];
    if (isset($expected[$reference])) {
        $actual[$reference] = [
            'mb_ht' => (float) $row['mb_ht'],
            'marge_nette' => (float) $row['marge_nette'],
        ];
    }
}

$failed = false;
foreach ($expected as $reference => $values) {
    if (!isset($actual[$reference])) {
        fwrite(STDERR, "FAIL {$reference}: commande absente de l'export\n");
        $failed = true;
        continue;
    }

    foreach ($values as $field => $expectedValue) {
        $actualValue = $actual[$reference][$field];
        if (abs($actualValue - $expectedValue) > 0.01) {
            fwrite(STDERR, sprintf(
                "FAIL %s %s: attendu %.2f, obtenu %.2f\n",
                $reference,
                $field,
                $expectedValue,
                $actualValue
            ));
            $failed = true;
        }
    }
}

if ($failed) {
    exit(1);
}

echo "OK: marges KPI conformes pour C3921, C3959 et C3986.\n";