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

// Règle du 08/10/2026 : une ligne par facture, une ligne négative par avoir.
// Valeurs attendues sur juillet 2026 (règles validées par Ramazan le 14/10/2026).
$expected = [
    'C3959|commande' => ['ca_ht' => 366.04, 'mb_ht' => 139.01, 'marge_nette' => 151.86],
    'C3959|avoir' => ['ca_ht' => -366.04, 'mb_ht' => -139.01, 'marge_nette' => -151.86],
    'C3986|commande' => ['ca_ht' => 121.32, 'mb_ht' => 56.52, 'marge_nette' => 60.19],
    'C3986|avoir' => ['ca_ht' => -121.32, 'mb_ht' => -56.52, 'marge_nette' => -60.19],
    'C3921|avoir' => ['ca_ht' => -35.23, 'mb_ht' => -10.82, 'marge_nette' => -12.20],
    'C4035|commande' => ['ca_ht' => 304.79, 'mb_ht' => 0.00, 'marge_nette' => 121.50],
];

$service = new KpiReportService(Context::getContext());
$rows = $service->getInvoicedKpisForMonth($year, $month, $rate);
$actual = [];
$failed = false;

foreach ($rows as $row) {
    if ($row['document_number'] === '') {
        fwrite(STDERR, "FAIL {$row['order_reference']} ({$row['line_type']}): numéro de facture / avoir manquant\n");
        $failed = true;
    }
    if ($row['line_type'] === 'avoir' && (float) $row['ca_ht'] > 0) {
        fwrite(STDERR, "FAIL {$row['order_reference']}: CA positif sur une ligne d'avoir\n");
        $failed = true;
    }

    $key = $row['order_reference'] . '|' . $row['line_type'];
    if (isset($expected[$key])) {
        $actual[$key] = [
            'ca_ht' => (float) $row['ca_ht'],
            'mb_ht' => (float) $row['mb_ht'],
            'marge_nette' => (float) $row['marge_nette'],
        ];
    }
}

foreach ($expected as $key => $values) {
    if (!isset($actual[$key])) {
        fwrite(STDERR, "FAIL {$key}: ligne absente de l'export\n");
        $failed = true;
        continue;
    }

    foreach ($values as $field => $expectedValue) {
        $actualValue = $actual[$key][$field];
        if (abs($actualValue - $expectedValue) > 0.01) {
            fwrite(STDERR, sprintf(
                "FAIL %s %s: attendu %.2f, obtenu %.2f\n",
                $key,
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

echo "OK: lignes commande / avoir conformes (" . count($expected) . " cas).\n";
