<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'ps_pts_reporting/classes/KpiReportService.php';

class Ps_Pts_Reporting extends Module
{
    const CONFIG_DEPANNAGE_RATE = 'PTS_REPORT_DEPANNAGE_RATE';

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
            && Configuration::updateValue(self::CONFIG_DEPANNAGE_RATE, '1.06')
            && $this->installTab();
    }

    public function uninstall()
    {
        return $this->uninstallTab()
            && Configuration::deleteByName(self::CONFIG_DEPANNAGE_RATE)
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
        $depannageRate = (float) Configuration::get(self::CONFIG_DEPANNAGE_RATE);
        if ($depannageRate <= 0) {
            $depannageRate = 1.06;
        }

        $service = new KpiReportService($this->context);
        $rows = $service->getDailyKpisForLastMonth($depannageRate);

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
            'reference_commande',
            'date_commande',
            'date_facture',
            'ca_ht',
            'depannage_ht',
            'commandes_fournisseur_liees',
            'mb_ht',
            'marge_nette',
            'pct_mb_ht',
            'pct_marge_nette',
        ]);

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
            ]);
        }

        rewind($out);
        $content = stream_get_contents($out);
        fclose($out);

        return $content;
    }
}
