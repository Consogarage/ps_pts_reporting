<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'ps_pts_reporting/classes/KpiReportService.php';

class Ps_Pts_Reporting extends Module
{
    const CONFIG_DEPANNAGE_RATE = 'PTS_REPORT_DEPANNAGE_RATE';
    const CONFIG_REPORT_EMAILS = 'PTS_REPORT_EMAILS';

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
            && Configuration::updateValue(self::CONFIG_REPORT_EMAILS, '')
            && $this->installTab();
    }

    public function uninstall()
    {
        return $this->uninstallTab()
            && Configuration::deleteByName(self::CONFIG_DEPANNAGE_RATE)
            && Configuration::deleteByName(self::CONFIG_REPORT_EMAILS)
            && parent::uninstall();
    }

    public function normalizeEmails($rawEmails)
    {
        $singleEmail = trim((string) $rawEmails);
        if ($singleEmail !== '' && Validate::isEmail($singleEmail)) {
            return $singleEmail;
        }

        $parts = preg_split('/[\s,;]+/', (string) $rawEmails);
        $valid = [];

        foreach ($parts as $email) {
            $email = trim($email);
            if ($email === '') {
                continue;
            }
            if (!Validate::isEmail($email)) {
                continue;
            }
            $valid[strtolower($email)] = $email;
        }

        return implode(',', array_values($valid));
    }

    public function getReportEmails()
    {
        $normalized = $this->normalizeEmails(Configuration::get(self::CONFIG_REPORT_EMAILS, ''));
        if ($normalized === '') {
            return [];
        }

        return explode(',', $normalized);
    }

    public function sendMonthlyReportToConfiguredEmails($filename, $csvContent)
    {
        $emails = $this->getReportEmails();
        if (empty($emails)) {
            return 0;
        }

        $shopName = (string) Configuration::get('PS_SHOP_NAME');
        $subject = sprintf('Rapport mensuel %s', $shopName !== '' ? $shopName : 'PTS');
        $body = 'Veuillez trouver en piece jointe le rapport mensuel.';
        $sent = 0;

        foreach ($emails as $email) {
            if ($this->sendEmailWithAttachment($email, $subject, $body, (string) $filename, (string) $csvContent)) {
                $sent++;
            }
        }

        return $sent;
    }

    public function saveReportFileToExports($filename, $csvContent)
    {
        $exportsDir = _PS_MODULE_DIR_ . $this->name . '/exports';
        if (!is_dir($exportsDir) && !mkdir($exportsDir, 0755, true)) {
            return false;
        }

        $targetPath = $exportsDir . '/' . ltrim((string) $filename, '/');
        $bytes = file_put_contents($targetPath, (string) $csvContent, LOCK_EX);
        if ($bytes === false) {
            return false;
        }

        return [
            'path' => $targetPath,
            'bytes' => (int) $bytes,
        ];
    }

    private function sendEmailWithAttachment($to, $subject, $body, $filename, $content)
    {
        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        if (!empty($this->context->language->id)) {
            $idLang = (int) $this->context->language->id;
        }

        $fileAttachment = [
            'content' => $content,
            'name' => $filename,
            'mime' => 'text/csv',
        ];

        return (bool) Mail::Send(
            $idLang,
            'report_monthly',
            $subject,
            [
                '{shop_name}' => (string) Configuration::get('PS_SHOP_NAME'),
                '{message_body}' => $body,
            ],
            $to,
            null,
            null,
            null,
            $fileAttachment,
            null,
            _PS_MODULE_DIR_ . $this->name . '/mails/',
            false,
            isset($this->context->shop->id) ? (int) $this->context->shop->id : null
        );
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

        $start = new DateTime('first day of last month');
        $year = (int) $start->format('Y');
        $month = (int) $start->format('n');

        $service = new KpiReportService($this->context);
        $rows = $service->getDailyKpisForPeriod($year, $month, $year, $month, $depannageRate);

        $filename = sprintf('pts_rapport_mensuel_%04d_%02d.csv', $year, $month);
        $content = $this->buildMonthlyCsvContent($rows);

        return [
            'filename' => $filename,
            'content' => $content,
        ];
    }

    private function buildMonthlyCsvContent(array $rows)
    {
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

        $out = fopen('php://temp', 'r+');
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

        rewind($out);
        $content = stream_get_contents($out);
        fclose($out);

        return $content;
    }
}
