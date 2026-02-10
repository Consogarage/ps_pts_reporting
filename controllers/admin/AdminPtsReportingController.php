<?php

require_once _PS_MODULE_DIR_ . 'ps_pts_reporting/classes/KpiReportService.php';

class AdminPtsReportingController extends ModuleAdminController
{
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
        $export = (int) Tools::getValue('export', 0);

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

        $service = new KpiReportService($this->context);
        $rows = $service->getDailyKpisForPeriod($yearFrom, $monthFrom, $yearTo, $monthTo);

        if ($export === 1) {
            $this->exportCsv($rows, $yearFrom, $monthFrom, $yearTo, $monthTo);
        }

        $this->context->smarty->assign([
            'rows' => $rows,
            'year_from' => $yearFrom,
            'year_to' => $yearTo,
            'month_from' => $monthFrom,
            'month_to' => $monthTo,
            'months' => $months,
            'years' => $years,
            'action_url' => $this->context->link->getAdminLink('AdminPtsReporting', false),
            'token' => $this->token,
            'export_url' => $this->context->link->getAdminLink('AdminPtsReporting', true, [], [
                'year_from' => $yearFrom,
                'year_to' => $yearTo,
                'month_from' => $monthFrom,
                'month_to' => $monthTo,
                'export' => 1,
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
            'date_commande',
            'date_facture',
            'cumul_ca_ht',
            'cumul_mb_ht',
            'cumul_marge_nette',
            'cumul_pct_mb_ht',
            'cumul_pct_marge_nette',
        ]);

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['order_date'],
                $row['invoice_date'],
                $row['cumul_ca_ht'],
                $row['cumul_mb_ht'],
                $row['cumul_marge_nette'],
                $row['cumul_pct_mb_ht'],
                $row['cumul_pct_marge_nette'],
            ]);
        }

        fclose($out);
        exit;
    }
}
