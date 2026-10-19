<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script doit être exécuté en ligne de commande.\n");
    exit(2);
}

$rootDir = dirname(__DIR__, 3);
require_once $rootDir . '/config/config.inc.php';
require_once dirname(__DIR__) . '/classes/KpiReportService.php';

// Usage : php test_kpi_margin.php [année mois] [coef]  (sans argument : tous les mois de référence)
$rate = isset($argv[3]) ? (float) $argv[3] : 1.06;

// Règle du 08/10/2026 : une ligne par facture, une ligne négative par avoir.
// Valeurs attendues (règles validées par Ramazan le 14/10/2026).
$expectedByMonth = [
    '2026-07' => [
        'C3959|commande' => ['ca_ht' => 366.04, 'mb_ht' => 139.01, 'marge_nette' => 151.86],
        'C3959|avoir' => ['ca_ht' => -366.04, 'mb_ht' => -139.01, 'marge_nette' => -151.86],
        'C3986|commande' => ['ca_ht' => 121.32, 'mb_ht' => 56.52, 'marge_nette' => 60.19],
        'C3986|avoir' => ['ca_ht' => -121.32, 'mb_ht' => -56.52, 'marge_nette' => -60.19],
        'C3921|avoir' => ['ca_ht' => -35.23, 'mb_ht' => -10.82, 'marge_nette' => -12.20],
        'C4035|commande' => ['ca_ht' => 304.79, 'mb_ht' => 0.00, 'marge_nette' => 121.50],
    ],
    '2026-09' => [
        // Produits de dépannage sans fournisseur par défaut, achetés chez ITAL EXPRESS : coef 0 en marge brute.
        'C4244|commande' => ['ca_ht' => 726.00, 'mb_ht' => 0.00, 'marge_nette' => 153.80],
        // Commande mixte ITAL EXPRESS / autre fournisseur.
        'C4251|commande' => ['ca_ht' => 148.63, 'mb_ht' => 31.15, 'marge_nette' => 58.61],
        // Avoirs mêlant pièce remboursée et consigne seule : la consigne ne réintègre aucun coût.
        'C4272|avoir' => ['ca_ht' => -79.43, 'mb_ht' => 0.00, 'marge_nette' => -32.20],
        'C4290|avoir' => ['ca_ht' => -150.00, 'mb_ht' => 0.00, 'marge_nette' => -73.44],
        // Avoir de consigne dont le montant est inférieur à la consigne x quantité : CA ramené à 0.
        'C4356|avoir' => ['ca_ht' => 0.00, 'mb_ht' => 0.00, 'marge_nette' => 0.00],
    ],
];

if (isset($argv[1], $argv[2])) {
    $key = sprintf('%04d-%02d', (int) $argv[1], (int) $argv[2]);
    $expectedByMonth = [$key => isset($expectedByMonth[$key]) ? $expectedByMonth[$key] : []];
}

$service = new KpiReportService(Context::getContext());
$failed = false;
$checked = 0;

foreach ($expectedByMonth as $period => $expected) {
    list($year, $month) = array_map('intval', explode('-', $period));
    $rows = $service->getInvoicedKpisForMonth($year, $month, $rate);
    $actual = [];

    foreach ($rows as $row) {
        if ($row['document_number'] === '') {
            fwrite(STDERR, "FAIL {$period} {$row['order_reference']} ({$row['line_type']}): numéro de facture / avoir manquant\n");
            $failed = true;
        }
        if ($row['line_type'] === 'avoir' && (float) $row['ca_ht'] > 0) {
            fwrite(STDERR, "FAIL {$period} {$row['order_reference']}: CA positif sur une ligne d'avoir\n");
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
        $checked++;
        if (!isset($actual[$key])) {
            fwrite(STDERR, "FAIL {$period} {$key}: ligne absente de l'export\n");
            $failed = true;
            continue;
        }

        foreach ($values as $field => $expectedValue) {
            $actualValue = $actual[$key][$field];
            if (abs($actualValue - $expectedValue) > 0.01) {
                fwrite(STDERR, sprintf(
                    "FAIL %s %s %s: attendu %.2f, obtenu %.2f\n",
                    $period,
                    $key,
                    $field,
                    $expectedValue,
                    $actualValue
                ));
                $failed = true;
            }
        }
    }
}

if ($failed) {
    exit(1);
}

echo "OK: lignes commande / avoir conformes ({$checked} cas).\n";
