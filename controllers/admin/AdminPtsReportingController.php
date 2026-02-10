<?php

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

        $year = (int) Tools::getValue('year', (int) date('Y'));
        $month = (int) Tools::getValue('month', (int) date('n'));
        $export = (int) Tools::getValue('export', 0);

        $service = new KpiReportService($this->context);
        $rows = $service->getDailyKpis($year, $month);

        if ($export === 1) {
            $this->exportCsv($rows, $year, $month);
        }

        $this->context->smarty->assign([
            'rows' => $rows,
            'year' => $year,
            'month' => $month,
            'export_url' => $this->context->link->getAdminLink('AdminPtsReporting', true, [], [
                'year' => $year,
                'month' => $month,
                'export' => 1,
            ]),
        ]);

        $this->setTemplate('reporting.tpl');
    }

    private function exportCsv(array $rows, $year, $month)
    {
        $filename = sprintf('pts_kpi_%04d_%02d.csv', $year, $month);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'date',
            'cumul_ca_ht',
            'cumul_mb_ht',
            'cumul_marge_nette',
            'cumul_pct_mb_ht',
            'cumul_pct_marge_nette',
        ]);

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['date'],
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
