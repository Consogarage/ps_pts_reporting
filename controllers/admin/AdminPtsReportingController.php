<?php

require_once _PS_MODULE_DIR_ . 'ps_pts_reporting/classes/KpiReportService.php';

class AdminPtsReportingController extends ModuleAdminController
{
    const CONFIG_DEPANNAGE_RATE = 'PTS_REPORT_DEPANNAGE_RATE';

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function initContent()
    {
        parent::initContent();

        $currentYear = (int) date('Y');
        $yearFrom = (int) Tools::getValue('year_from', $currentYear);
        $yearTo = (int) Tools::getValue('year_to', $yearFrom);
        $monthFrom = (int) Tools::getValue('month_from', (int) date('n'));
        $monthTo = (int) Tools::getValue('month_to', $monthFrom);
        $storedRate = $this->normalizeRate(Configuration::get(self::CONFIG_DEPANNAGE_RATE, '1.06'));
        $depannageRateInput = Tools::getValue('depannage_rate', null);
        $depannageRate = $depannageRateInput === null
            ? $storedRate
            : $this->normalizeRate($depannageRateInput);

        if ($depannageRate !== $storedRate) {
            Configuration::updateValue(self::CONFIG_DEPANNAGE_RATE, number_format($depannageRate, 2, '.', ''));
        }
        $export = (int) Tools::getValue('export', 0);
        $exportMonthly = (int) Tools::getValue('export_monthly', 0);

        $monthFrom = max(1, min(12, $monthFrom));
        $monthTo = max(1, min(12, $monthTo));

        $startDate = DateTime::createFromFormat('Y-n-j', $yearFrom . '-' . $monthFrom . '-1');
        $endDate = DateTime::createFromFormat('Y-n-j', $yearTo . '-' . $monthTo . '-1');
        if ($startDate && $endDate && $endDate < $startDate) {
            $tmp = $yearFrom;
            $yearFrom = $yearTo;
            $yearTo = $tmp;

            $tmp = $monthFrom;
            $monthFrom = $monthTo;
            $monthTo = $tmp;
        }

        $monthLabels = [
            1 => 'janvier',
            2 => 'fevrier',
            3 => 'mars',
            4 => 'avril',
            5 => 'mai',
            6 => 'juin',
            7 => 'juillet',
            8 => 'aout',
            9 => 'septembre',
            10 => 'octobre',
            11 => 'novembre',
            12 => 'decembre',
        ];

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[] = [
                'value' => $m,
                'label' => $monthLabels[$m],
            ];
        }

        $years = [];
        for ($y = $currentYear - 4; $y <= $currentYear; $y++) {
            $years[] = $y;
        }

        if ($exportMonthly === 1) {
            $this->exportMonthlyCsv($depannageRate);
        }

        $service = new KpiReportService($this->context);
        $rows = $service->getDailyKpisForPeriod($yearFrom, $monthFrom, $yearTo, $monthTo, $depannageRate);

        if ($export === 1) {
            $this->exportCsv($rows, $yearFrom, $monthFrom, $yearTo, $monthTo);
        }

        $this->context->smarty->assign([
            'rows' => $rows,
            'year_from' => $yearFrom,
            'year_to' => $yearTo,
            'month_from' => $monthFrom,
            'month_to' => $monthTo,
            'depannage_rate' => number_format($depannageRate, 2, '.', ''),
            'months' => $months,
            'years' => $years,
            'action_url' => $this->context->link->getAdminLink('AdminPtsReporting', false),
            'token' => $this->token,
            'export_url' => $this->context->link->getAdminLink('AdminPtsReporting', true, [], [
                'year_from' => $yearFrom,
                'year_to' => $yearTo,
                'month_from' => $monthFrom,
                'month_to' => $monthTo,
                'depannage_rate' => number_format($depannageRate, 2, '.', ''),
                'export' => 1,
            ]),
            'export_monthly_url' => $this->context->link->getAdminLink('AdminPtsReporting', true, [], [
                'depannage_rate' => number_format($depannageRate, 2, '.', ''),
                'export_monthly' => 1,
            ]),
        ]);

        $this->setTemplate('reporting.tpl');
    }

    private function exportCsv(array $rows, $yearFrom, $monthFrom, $yearTo, $monthTo)
    {
        if ($yearFrom === $yearTo && $monthFrom === $monthTo) {
            $filename = sprintf('pts_kpi_%04d_%02d.csv', $yearFrom, $monthFrom);
        } else {
            $filename = sprintf('pts_kpi_%04d_%02d-%04d_%02d.csv', $yearFrom, $monthFrom, $yearTo, $monthTo);
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'reference commande',
            'date commande',
            'date facture',
            'ca',
            'depannage',
            'commandes fournisseur liees',
            'marge brute',
            'marge nette',
            '% marge brute',
            '% marge nette',
        ], ';');

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['order_reference'],
                $row['order_date'],
                $row['invoice_date'],
                $row['ca_ht'],
                $row['depannage_ht'],
                $row['supplier_order_refs'],
                $row['mb_ht'],
                $row['marge_nette'],
                $row['pct_mb_ht'],
                $row['pct_marge_nette'],
            ], ';');
        }

        fclose($out);
        exit;
    }

    private function exportMonthlyCsv($depannageRate)
    {
        $year = (int) date('Y');
        $month = (int) date('n');

        $service = new KpiReportService($this->context);
        $rows = $service->getDailyKpisForPeriod($year, $month, $year, $month, $depannageRate);

        $totalCaHt = 0.0;
        $totalDepannageHt = 0.0;
        $totalMbHt = 0.0;
        $totalMargeNette = 0.0;

        foreach ($rows as $row) {
            $totalCaHt += (float) $row['ca_ht'];
            $totalDepannageHt += (float) $row['depannage_ht'];
            $totalMbHt += (float) $row['mb_ht'];
            $totalMargeNette += (float) $row['marge_nette'];
        }

        $pctMbHt = $totalCaHt > 0 ? ($totalMbHt * 100 / $totalCaHt) : 0;
        $pctMargeNette = $totalCaHt > 0 ? ($totalMargeNette * 100 / $totalCaHt) : 0;

        $filename = sprintf('pts_rapport_mensuel_%04d_%02d.csv', $year, $month);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'ca',
            'depannage',
            'marge brute',
            'marge nette',
            '% marge brute',
            '% marge nette',
        ], ';');

        fputcsv($out, [
            number_format($totalCaHt, 2, '.', ''),
            number_format($totalDepannageHt, 2, '.', ''),
            number_format($totalMbHt, 2, '.', ''),
            number_format($totalMargeNette, 2, '.', ''),
            number_format($pctMbHt, 2, '.', ''),
            number_format($pctMargeNette, 2, '.', ''),
        ], ';');

        fclose($out);
        exit;
    }

    private function normalizeRate($rate)
    {
        $normalized = (float) str_replace(',', '.', (string) $rate);
        if ($normalized <= 0) {
            return 1.06;
        }

        return $normalized;
    }
}
