<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'ps_pts_reporting/classes/KpiReportService.php';

class Ps_Pts_Reporting extends Module
{
    public function __construct()
    {
        $this->name = 'ps_pts_reporting';
        $this->tab = 'analytics_stats';
        $this->version = '1.0.0';
        $this->author = 'PTS';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Reporting PTS');
        $this->description = $this->l('Reporting des KPI journaliers et export CSV.');
    }

    public function install()
    {
        return parent::install()
            && $this->installTab();
    }

    public function uninstall()
    {
        return $this->uninstallTab()
            && parent::uninstall();
    }

    private function installTab()
    {
        $idParent = (int) Tab::getIdFromClassName('AdminStats');
        if ($idParent <= 0) {
            $idParent = 0;
        }

        $tab = new Tab();
        $tab->class_name = 'AdminPtsReporting';
        $tab->module = $this->name;
        $tab->id_parent = $idParent;
        $tab->active = 1;

        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = 'Reporting PTS';
        }

        return $tab->add();
    }

    private function uninstallTab()
    {
        $idTab = (int) Tab::getIdFromClassName('AdminPtsReporting');
        if ($idTab <= 0) {
            return true;
        }

        $tab = new Tab($idTab);

        return $tab->delete();
    }

    public function getLastMonthCsvPayload()
    {
        $service = new KpiReportService($this->context);
        $rows = $service->getDailyKpisForLastMonth();

        $date = new DateTime('first day of last month');
        $filename = sprintf('pts_kpi_%s.csv', $date->format('Y_m'));
        $content = $this->buildCsvContent($rows);

        return [
            'filename' => $filename,
            'content' => $content,
        ];
    }

    private function buildCsvContent(array $rows)
    {
        $out = fopen('php://temp', 'r+');
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

        rewind($out);
        $content = stream_get_contents($out);
        fclose($out);

        return $content;
    }
}
