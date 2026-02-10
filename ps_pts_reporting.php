<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

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

        $this->displayName = $this->l('PTS Reporting');
        $this->description = $this->l('Daily KPI reporting and export for current month.');
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
            $tab->name[(int) $lang['id_lang']] = 'PTS Reporting';
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
}
