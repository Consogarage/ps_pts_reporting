<?php

require_once _PS_MODULE_DIR_ . 'ps_pts_reporting/classes/KpiReportService.php';

class AdminPtsReportingController extends ModuleAdminController
{
    const CONFIG_DEPANNAGE_RATE = 'PTS_REPORT_DEPANNAGE_RATE';
    const CONFIG_REPORT_EMAILS = 'PTS_REPORT_EMAILS';

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);

        if ($this->module && method_exists($this->module, 'getPathUri')) {
            $this->addCSS($this->module->getPathUri() . 'views/css/admin/reporting.css');
        }
    }

    public function initContent()
    {
        parent::initContent();

        $downloadExport = (string) Tools::getValue('download_export', '');
        if ($downloadExport !== '') {
            $this->downloadExportFile($downloadExport);
        }

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

        $storedEmails = (string) Configuration::get(self::CONFIG_REPORT_EMAILS, '');
        $emailsInput = Tools::getValue('report_emails', null);
        $reportEmails = $emailsInput === null
            ? $storedEmails
            : $this->normalizeEmails($emailsInput);

        if ($reportEmails !== $storedEmails) {
            Configuration::updateValue(self::CONFIG_REPORT_EMAILS, $reportEmails);
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

        $lastMonthDate = new DateTime('first day of last month');
        $lastMonth = (int) $lastMonthDate->format('n');
        $lastYear = (int) $lastMonthDate->format('Y');
        $exportMonthlyLabel = sprintf('Rapport mensuel (%s %d)', $monthLabels[$lastMonth], $lastYear);

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

        $exportsDirectory = _PS_MODULE_DIR_ . 'ps_pts_reporting/exports';
        $exportHistory = $this->getExportHistory(12);

        $this->context->smarty->assign([
            'rows' => $rows,
            'year_from' => $yearFrom,
            'year_to' => $yearTo,
            'month_from' => $monthFrom,
            'month_to' => $monthTo,
            'depannage_rate' => number_format($depannageRate, 2, '.', ''),
            'report_emails' => $reportEmails,
            'months' => $months,
            'years' => $years,
            'action_url' => $this->context->link->getAdminLink('AdminPtsReporting', false),
            'token' => $this->token,
            'exports_directory' => $exportsDirectory,
            'export_history' => $exportHistory,
            'export_url' => $this->context->link->getAdminLink('AdminPtsReporting', true, [], [
                'year_from' => $yearFrom,
                'year_to' => $yearTo,
                'month_from' => $monthFrom,
                'month_to' => $monthTo,
                'depannage_rate' => number_format($depannageRate, 2, '.', ''),
                'export' => 1,
            ]),
            'export_monthly_label' => $exportMonthlyLabel,
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
            'Marge nette',
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
        $date = new DateTime('first day of last month');
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');

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
        $csvContent = (string) stream_get_contents($out);
        fclose($out);

        $module = $this->module;
        $saved = false;
        if ($module && method_exists($module, 'saveReportFileToExports')) {
            $saved = $module->saveReportFileToExports($filename, $csvContent);
        }

        $configuredEmails = [];
        $sent = 0;

        if ($module && method_exists($module, 'getReportEmails')) {
            $configuredEmails = (array) $module->getReportEmails();
        }

        if (!empty($configuredEmails) && $module && method_exists($module, 'sendMonthlyReportToConfiguredEmails')) {
            $sent = (int) $module->sendMonthlyReportToConfiguredEmails($filename, $csvContent);
        }

        if ($sent > 0) {
            $this->confirmations[] = sprintf('Rapport mensuel envoye a %d adresse(s).', $sent);
        } elseif (!empty($configuredEmails)) {
            $this->warnings[] = 'Emails trouves mais envoi echoue. Verifiez la configuration email/smtp de PrestaShop.';
        } else {
            $this->warnings[] = 'Aucun email valide configure pour l envoi du rapport mensuel.';
        }

        if ($saved !== false) {
            $this->confirmations[] = sprintf('Fichier rapport mensuel enregistre: %s', $saved['path']);
        } else {
            $this->warnings[] = 'Impossible d enregistrer le rapport mensuel dans exports.';
        }
    }

    private function normalizeEmails($rawEmails)
    {
        $module = $this->module;
        if ($module && method_exists($module, 'normalizeEmails')) {
            return (string) $module->normalizeEmails($rawEmails);
        }

        $singleEmail = trim((string) $rawEmails);
        if ($singleEmail !== '' && Validate::isEmail($singleEmail)) {
            return $singleEmail;
        }

        $parts = preg_split('/[\s,;]+/', (string) $rawEmails);
        $valid = [];

        foreach ($parts as $email) {
            $email = trim($email);
            if ($email === '' || !Validate::isEmail($email)) {
                continue;
            }
            $valid[strtolower($email)] = $email;
        }

        return implode(',', array_values($valid));
    }

    private function getExportHistory($limit = 12)
    {
        $exportsDir = _PS_MODULE_DIR_ . 'ps_pts_reporting/exports';
        if (!is_dir($exportsDir)) {
            return [];
        }

        $files = @scandir($exportsDir);
        if ($files === false) {
            return [];
        }

        $items = [];
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (strtolower((string) pathinfo($file, PATHINFO_EXTENSION)) !== 'csv') {
                continue;
            }

            $path = $exportsDir . '/' . $file;
            if (!is_file($path)) {
                continue;
            }

            $items[] = [
                'filename' => $file,
                'mtime' => (int) @filemtime($path),
                'download_url' => $this->context->link->getAdminLink('AdminPtsReporting', true, [], [
                    'download_export' => $file,
                ]),
            ];
        }

        usort($items, function ($a, $b) {
            return (int) $b['mtime'] - (int) $a['mtime'];
        });

        $items = array_slice($items, 0, (int) $limit);
        foreach ($items as &$item) {
            $item['date'] = !empty($item['mtime']) ? date('d/m/Y H:i', (int) $item['mtime']) : '-';
        }
        unset($item);

        return $items;
    }

    private function downloadExportFile($filename)
    {
        $safeName = basename((string) $filename);
        if ($safeName === '' || $safeName !== (string) $filename) {
            $this->warnings[] = 'Nom de fichier export invalide.';
            return;
        }

        $path = _PS_MODULE_DIR_ . 'ps_pts_reporting/exports/' . $safeName;
        if (!is_file($path)) {
            $this->warnings[] = 'Fichier export introuvable.';
            return;
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Content-Length: ' . filesize($path));
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($path);
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
