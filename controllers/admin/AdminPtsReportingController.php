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
        $defaultMonthDate = new DateTime('first day of last month');
        $defaultMonth = (int) $defaultMonthDate->format('n');
        $defaultYear = (int) $defaultMonthDate->format('Y');
        $yearFrom = (int) Tools::getValue('year_from', $defaultYear);
        $yearTo = (int) Tools::getValue('year_to', $yearFrom);
        $monthFrom = (int) Tools::getValue('month_from', $defaultMonth);
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
        $defaultReportMonth = (int) $lastMonthDate->format('n');
        $defaultReportYear = (int) $lastMonthDate->format('Y');
        $reportMonthlyMonth = max(1, min(12, (int) Tools::getValue('report_monthly_month', $defaultReportMonth)));
        $reportMonthlyYear = (int) Tools::getValue('report_monthly_year', $defaultReportYear);
        $exportMonthlyLabel = sprintf('Rapport mensuel (%s %d)', $monthLabels[$reportMonthlyMonth], $reportMonthlyYear);

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
            $this->exportMonthlyCsv($depannageRate, $reportMonthlyYear, $reportMonthlyMonth);
        }

        $service = new KpiReportService($this->context);
        $rows = $service->getDailyKpisForPeriod($yearFrom, $monthFrom, $yearTo, $monthTo, $depannageRate);

        if ($export === 1) {
            $this->exportCsv($rows, $yearFrom, $monthFrom, $yearTo, $monthTo);
        }

        // Onglet KPI clients
        $activeTab = (string) Tools::getValue('active_tab', 'tab-ca-marge');
        $kpiMonth = max(1, min(12, (int) Tools::getValue('kpi_month', $defaultMonth)));
        $kpiYear = (int) Tools::getValue('kpi_year', $defaultYear);
        $kpiViewMode = in_array(Tools::getValue('kpi_view_mode'), ['detail', 'cumul']) ? Tools::getValue('kpi_view_mode') : 'detail';
        $monthLabelsDisplay = [
            1 => 'janvier',
            2 => 'février',
            3 => 'mars',
            4 => 'avril',
            5 => 'mai',
            6 => 'juin',
            7 => 'juillet',
            8 => 'août',
            9 => 'septembre',
            10 => 'octobre',
            11 => 'novembre',
            12 => 'décembre',
        ];
        $isYtd = ($kpiViewMode === 'cumul');
        $kpiPeriodN = $isYtd
            ? 'janvier à ' . ($monthLabelsDisplay[$kpiMonth] ?? $kpiMonth) . ' ' . $kpiYear
            : ($monthLabelsDisplay[$kpiMonth] ?? $kpiMonth) . ' ' . $kpiYear;
        $kpiPeriodN1 = $isYtd
            ? 'janvier à ' . ($monthLabelsDisplay[$kpiMonth] ?? $kpiMonth) . ' ' . ($kpiYear - 1)
            : ($monthLabelsDisplay[$kpiMonth] ?? $kpiMonth) . ' ' . ($kpiYear - 1);
        $customerKpiRows = [];
        $customerKpiSummary = [];
        $exportKpiClients = (int) Tools::getValue('export_kpi_clients', 0);

        if ($activeTab === 'tab-kpi-clients' || Tools::getValue('kpi_month') !== false || $exportKpiClients === 1) {
            $kpiResult = $service->getCustomerKpis($kpiYear, $kpiMonth, $isYtd);
            $customerKpiRows = $kpiResult['rows'] ?? [];
            $customerKpiSummary = $kpiResult['summary'] ?? [];
        }

        if ($exportKpiClients === 1) {
            if ($isYtd) {
                $this->exportCustomerKpisCumulXlsx($customerKpiRows, $customerKpiSummary, $kpiYear, $kpiMonth);
            } else {
                $this->exportCustomerKpisXlsx($customerKpiRows, $customerKpiSummary, $kpiYear, $kpiMonth);
            }
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
            'report_monthly_month' => $reportMonthlyMonth,
            'report_monthly_year' => $reportMonthlyYear,
            // Onglet KPI clients
            'active_tab' => $activeTab,
            'kpi_month' => $kpiMonth,
            'kpi_year' => $kpiYear,
            'kpi_view_mode' => $kpiViewMode,
            'kpi_period_n' => $kpiPeriodN,
            'kpi_period_n1' => $kpiPeriodN1,
            'customer_kpi_rows' => $customerKpiRows,
            'customer_kpi_summary' => $customerKpiSummary,
            'kpi_export_url' => $this->context->link->getAdminLink('AdminPtsReporting', true, [], [
                'active_tab' => 'tab-kpi-clients',
                'kpi_month' => $kpiMonth,
                'kpi_year' => $kpiYear,
                'kpi_view_mode' => $kpiViewMode,
                'export_kpi_clients' => 1,
            ]),
        ]);

        $this->setTemplate('reporting.tpl');
    }

    private function exportCustomerKpisCumulXlsx(array $rows, array $summary, $year, $month)
    {
        $monthLabels = [
            1 => 'janvier',
            2 => 'février',
            3 => 'mars',
            4 => 'avril',
            5 => 'mai',
            6 => 'juin',
            7 => 'juillet',
            8 => 'août',
            9 => 'septembre',
            10 => 'octobre',
            11 => 'novembre',
            12 => 'décembre',
        ];
        $moisLabel = $monthLabels[$month] ?? $month;
        $periodN = 'janvier à ' . $moisLabel . ' ' . $year;
        $periodN1 = 'janvier à ' . $moisLabel . ' ' . ($year - 1);
        $filename = sprintf('kpi_clients_cumul_%04d_%02d.xlsx', $year, $month);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('KPI Clients Cumul');

        // Référence de la dernière ligne de données
        $dataStart = 6;
        $dataEnd = $dataStart + max(0, count($rows) - 1);

        // ---- Styles (identiques à l'export détail) ----
        $styleBoldGray = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD9D9D9']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
        ];
        $styleSummaryLabel = [
            'font' => ['bold' => true, 'color' => ['argb' => 'FF555555']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEFF3FB']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];
        $styleSummaryValue = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDCE6F1']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];
        $styleHeader = [
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2E6DA4']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ];
        $styleDataEven = ['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF2F2F2']]];
        $styleNew = ['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFDE9D9']]];

        // ===== LIGNE 1 : Titre + indicateurs objectif =====
        // A1 : % réalisation objectif CA  (objectif annuel à adapter)
        $sheet->setCellValue('A1', '% objectif CA');
        $sheet->setCellValue('B1', '=(SUBTOTAL(9,G' . $dataStart . ':G' . $dataEnd . ')/715000)*100');
        $sheet->getStyle('B1')->getNumberFormat()->setFormatCode('0.00"%"');
        // C1 : % réalisation objectif MB
        $sheet->setCellValue('C1', '% objectif MB');
        $sheet->setCellValue('D1', '=(SUBTOTAL(9,H' . $dataStart . ':H' . $dataEnd . ')/260975)*100');
        $sheet->getStyle('D1')->getNumberFormat()->setFormatCode('0.00"%"');
        // F1 : label période
        $sheet->setCellValue('F1', 'Période : ' . $periodN);
        // Titre principal
        $sheet->setCellValue('H1', 'KPI Clients CUMUL – ' . $periodN . ' vs ' . $periodN1);
        $sheet->mergeCells('H1:U1');
        $sheet->getStyle('A1:U1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FF2E6DA4']],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        // ===== LIGNE 2 : Labels résumé clients =====
        $l2Labels = [
            'F' => 'Évol. clients',
            'G' => 'Nb actifs ' . $periodN,
            'H' => 'Évol. CA',
            'I' => 'Évol. MB',
            'J' => 'Nb actifs ' . $periodN1,
            'N' => 'Évol. CA %',
            'O' => 'Nb Devis',
            'P' => 'Nb Cdes',
            'Q' => 'Nb Transfo',
        ];
        foreach ($l2Labels as $col => $label) {
            $sheet->setCellValue($col . '2', $label);
        }
        $sheet->getStyle('A2:U2')->applyFromArray($styleSummaryLabel);

        // ===== LIGNE 3 : Valeurs résumé clients (SUBTOTAL + formules) =====
        // Nb actifs : valeurs pré-calculées (COUNTIF filtrable non natif)
        $sheet->setCellValue('F3', '=IF(J3>0,G3/J3-1,"N/A")');
        $sheet->setCellValue('G3', !empty($summary) ? $summary['nb_actifs_n'] : 0);
        $sheet->setCellValue('H3', '=IF(J4>0,G4/J4-1,"N/A")');
        $sheet->setCellValue('I3', '=IF(K4>0,H4/K4-1,"N/A")');
        $sheet->setCellValue('J3', !empty($summary) ? $summary['nb_actifs_n1'] : 0);
        $sheet->setCellValue('N3', '=IF(J4>0,(G4-J4)/J4*100,"N/A")');
        $sheet->getStyle('N3')->getNumberFormat()->setFormatCode('0.00"%"');
        $sheet->setCellValue('O3', '=SUBTOTAL(9,O' . $dataStart . ':O' . $dataEnd . ')');
        $sheet->setCellValue('P3', '=SUBTOTAL(9,P' . $dataStart . ':P' . $dataEnd . ')');
        $sheet->setCellValue('Q3', '=SUBTOTAL(9,Q' . $dataStart . ':Q' . $dataEnd . ')');
        $sheet->getStyle('A3:U3')->applyFromArray($styleSummaryValue);

        // ===== LIGNE 4 : Valeurs CA / MB (SUBTOTAL) =====
        $l4Labels = [
            'F' => 'Taux CA N/N-1',
            'G' => 'CA ' . $periodN,
            'H' => 'MB ' . $periodN,
            'I' => '% MB N',
            'J' => 'CA ' . $periodN1,
            'K' => 'MB ' . $periodN1,
            'L' => '% MB N-1',
            'M' => '% MB vs N-1',
            'N' => '% Évol. CA',
            'R' => 'Taux transfo',
            'S' => 'Panier moyen',
            'T' => 'Avoirs',
        ];
        foreach ($l4Labels as $col => $label) {
            $sheet->setCellValue($col . '4', $label);
        }
        // Formules SUBTOTAL ligne 5 (valeurs réelles de la ligne 4)
        // On utilise ligne 4 pour les labels et on insère une ligne 5 de valeurs
        // → en fait on décale tout : ligne 4 = labels, ligne 5 = valeurs SUBTOTAL, ligne 6 = en-têtes colonnes, ligne 7+ = données
        // Pour rester sur le modèle existant (5 lignes avant données), on met labels+valeurs ensemble sur la même ligne
        // Valeurs CA / MB dans ligne 4 directement
        $sheet->setCellValue('F4', '=IF(J4>0,G4/J4,"N/A")');
        $sheet->setCellValue('G4', '=SUBTOTAL(9,G' . $dataStart . ':G' . $dataEnd . ')');
        $sheet->setCellValue('H4', '=SUBTOTAL(9,H' . $dataStart . ':H' . $dataEnd . ')');
        $sheet->setCellValue('I4', '=IF(G4>0,H4/G4*100,"N/A")');
        $sheet->setCellValue('J4', '=SUBTOTAL(9,J' . $dataStart . ':J' . $dataEnd . ')');
        $sheet->setCellValue('K4', '=SUBTOTAL(9,K' . $dataStart . ':K' . $dataEnd . ')');
        $sheet->setCellValue('L4', '=IF(J4>0,K4/J4*100,"N/A")');
        $sheet->setCellValue('M4', '=IF(K4>0,(H4-K4)/K4*100,"N/A")');
        $sheet->setCellValue('N4', '=IF(J4>0,(G4-J4)/J4*100,"N/A")');
        $sheet->setCellValue('R4', '=IF(P3>0,Q3/P3*100,"N/A")');
        $sheet->setCellValue('S4', '=IF(P3>0,G4/P3,"N/A")');
        $sheet->setCellValue('T4', '=SUBTOTAL(9,T' . $dataStart . ':T' . $dataEnd . ')');
        // Formats numériques lignes 3-4
        foreach (['G4', 'H4', 'J4', 'K4', 'S4'] as $cell) {
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
        }
        foreach (['I4', 'L4', 'M4', 'N4', 'R4'] as $cell) {
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('0.00"%"');
        }
        $sheet->getStyle('A4:U4')->applyFromArray($styleSummaryLabel);

        // Centrer les 4 premières lignes
        $sheet->getStyle('A1:U4')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        // ===== LIGNE 5 : En-têtes colonnes =====
        $headers = [
            'A' => 'Client',
            'B' => 'Raison sociale',
            'C' => 'Activité',
            'D' => 'Dept',
            'E' => 'Pays',
            'F' => 'Écart CA',
            'G' => 'CA ' . $periodN,
            'H' => 'MB ' . $periodN,
            'I' => '% MB N',
            'J' => 'CA ' . $periodN1,
            'K' => 'MB ' . $periodN1,
            'L' => '% MB N-1',
            'M' => '% MB vs N-1',
            'N' => '% CA vs N-1',
            'O' => 'Devis',
            'P' => 'Cdes',
            'Q' => 'Transformés',
            'R' => 'Taux transfo',
            'S' => 'Panier moyen',
            'T' => 'Avoirs',
            'U' => 'SIRET',
        ];
        foreach ($headers as $col => $label) {
            $sheet->setCellValue($col . '5', $label);
        }
        $sheet->getStyle('A5:U5')->applyFromArray($styleHeader);
        $sheet->getRowDimension(5)->setRowHeight(30);

        // ===== LIGNES 6+ : Données =====
        $excelRow = $dataStart;
        foreach ($rows as $i => $row) {
            $sheet->setCellValue('A' . $excelRow, $row['customer_id']);
            $sheet->setCellValue('B' . $excelRow, $row['company']);
            $sheet->setCellValue('C' . $excelRow, $row['activity']);
            $sheet->setCellValue('D' . $excelRow, $row['dept']);
            $sheet->setCellValue('E' . $excelRow, $row['pays']);
            $sheet->setCellValue('F' . $excelRow, $row['ecart_ca_raw']);
            $sheet->setCellValue('G' . $excelRow, $row['ca_n_raw']);
            $sheet->setCellValue('H' . $excelRow, $row['mb_n_raw']);
            $sheet->setCellValue('I' . $excelRow, $row['pct_mb_n']);
            $sheet->setCellValue('J' . $excelRow, $row['ca_n1_raw']);
            $sheet->setCellValue('K' . $excelRow, $row['mb_n1_raw']);
            $sheet->setCellValue('L' . $excelRow, $row['pct_mb_n1']);
            $sheet->setCellValue('M' . $excelRow, $row['pct_mb_vs_n1']);
            $sheet->setCellValue('N' . $excelRow, $row['pct_ca_vs_n1']);
            $sheet->setCellValue('O' . $excelRow, $row['nb_devis']);
            $sheet->setCellValue('P' . $excelRow, $row['nb_commandes']);
            $sheet->setCellValue('Q' . $excelRow, $row['nb_devis_transformed']);
            $sheet->setCellValue('R' . $excelRow, $row['taux_transfo']);
            $sheet->setCellValue('S' . $excelRow, $row['panier_moyen']);
            $sheet->setCellValue('T' . $excelRow, $row['nb_avoirs']);
            $sheet->setCellValue('U' . $excelRow, $row['siret'] ?? '');

            if ($row['is_new_customer']) {
                $sheet->getStyle('A' . $excelRow . ':U' . $excelRow)->applyFromArray($styleNew);
            } elseif ($i % 2 === 1) {
                $sheet->getStyle('A' . $excelRow . ':U' . $excelRow)->applyFromArray($styleDataEven);
            }
            foreach (['F', 'G', 'H', 'J', 'K', 'S'] as $col) {
                $sheet->getStyle($col . $excelRow)->getNumberFormat()->setFormatCode('#,##0.00');
            }
            foreach (['I', 'L', 'M', 'N', 'R'] as $col) {
                $sheet->getStyle($col . $excelRow)->getNumberFormat()->setFormatCode('0.00"%"');
            }
            ++$excelRow;
        }

        // Auto-width + freeze 2 premières colonnes à partir ligne 6
        foreach (range('A', 'U') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('C' . $dataStart);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->setPreCalculateFormulas(false);
        $writer->save('php://output');
        exit;
    }

    private function exportCustomerKpisXlsx(array $rows, array $summary, $year, $month)
    {
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
        $periodN = ($monthLabels[$month] ?? $month) . ' ' . $year;
        $periodN1 = ($monthLabels[$month] ?? $month) . ' ' . ($year - 1);
        $filename = sprintf('kpi_clients_%04d_%02d.xlsx', $year, $month);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('KPI Clients');

        // ---- Styles ----
        $styleBoldGray = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFD9D9D9']
            ],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
        ];
        $styleSummaryLabel = [
            'font' => ['bold' => true, 'color' => ['argb' => 'FF555555']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFEFF3FB']
            ],
        ];
        $styleSummaryValue = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFDCE6F1']
            ],
        ];
        $styleHeader = [
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF2E6DA4']
            ],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'wrapText' => true
            ],
        ];
        $styleDataEven = [
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFF2F2F2']
            ]
        ];
        $styleNew = [
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFFDE9D9']
            ]
        ];

        // === LIGNE 1 : Titre ===
        $sheet->setCellValue('A1', 'KPI Clients – ' . $periodN . ' vs ' . $periodN1);
        $sheet->mergeCells('A1:U1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FF2E6DA4']],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        // === LIGNE 2 : Labels agrégats ===
        $sheet->setCellValue('A2', 'Sous-totaux');
        $sheet->setCellValue('F2', 'Écart CA');
        $sheet->setCellValue('G2', 'Date mensuel');
        $sheet->setCellValue('H2', 'Nb clients N');
        $sheet->setCellValue('I2', 'Nb clients N-1');
        $sheet->setCellValue('J2', 'Évol. clients %');
        $sheet->setCellValue('N2', 'Évol. CA %');
        $sheet->setCellValue('O2', 'Total Devis');
        $sheet->setCellValue('P2', 'Total Cdes');
        $sheet->setCellValue('Q2', 'Total Transfo');
        $sheet->setCellValue('R2', 'Taux transfo');
        $sheet->setCellValue('T2', 'Total Avoirs');
        $sheet->setCellValue('U2', 'Nb nouveaux');
        $sheet->getStyle('A2:V2')->applyFromArray($styleSummaryLabel);

        // === LIGNE 3 : Valeurs agrégats ===
        $sheet->setCellValue('A3', 'Valeurs');
        $sheet->setCellValue('F3', !empty($summary) ? $summary['ecart_ca'] : '');
        $sheet->setCellValue('G3', !empty($summary) ? $periodN : '');
        $sheet->setCellValue('H3', !empty($summary) ? $summary['nb_actifs_n'] : '');
        $sheet->setCellValue('I3', !empty($summary) ? $summary['nb_actifs_n1'] : '');
        $sheet->setCellValue('J3', !empty($summary) ? $summary['evol_nb_clients'] . ' %' : '');
        $sheet->setCellValue('K3', !empty($summary) ? $summary['total_mb_n'] : '');
        $sheet->setCellValue('L3', !empty($summary) ? $summary['pct_mb_n'] . ' %' : '');
        $sheet->setCellValue('M3', !empty($summary) ? $summary['total_mb_n1'] : '');
        // Row 3 col G also holds CA info in the image – we split across 2 "header" rows
        // Additional full-width summary in cols G-N row3:
        $sheet->setCellValue('N3', !empty($summary) ? $summary['pct_ca_vs_n1'] . ' %' : '');
        $sheet->setCellValue('O3', !empty($summary) ? $summary['total_devis'] : '');
        $sheet->setCellValue('P3', !empty($summary) ? $summary['total_cmds'] : '');
        $sheet->setCellValue('Q3', !empty($summary) ? $summary['total_transfo'] : '');
        $sheet->setCellValue('R3', !empty($summary) ? $summary['taux_transfo'] . ' %' : '');
        $sheet->setCellValue('S3', !empty($summary) ? $summary['panier_moyen'] : '');
        $sheet->setCellValue('T3', !empty($summary) ? $summary['total_avoirs'] : '');
        $sheet->setCellValue('U3', !empty($summary) ? $summary['nb_nouveaux'] : '');
        $sheet->getStyle('A3:V3')->applyFromArray($styleSummaryValue);

        // Ligne 4 séparateur CA / MB
        $sheet->setCellValue('F4', !empty($summary) ? 'Total CA N : ' . $summary['total_ca_n'] : '');
        $sheet->setCellValue('G4', !empty($summary) ? 'Total MB N : ' . $summary['total_mb_n'] : '');
        $sheet->setCellValue('H4', !empty($summary) ? '% MB N : ' . $summary['pct_mb_n'] . ' %' : '');
        $sheet->setCellValue('J4', !empty($summary) ? 'Total CA N-1 : ' . $summary['total_ca_n1'] : '');
        $sheet->setCellValue('K4', !empty($summary) ? 'Total MB N-1 : ' . $summary['total_mb_n1'] : '');
        $sheet->setCellValue('L4', !empty($summary) ? '% MB N-1 : ' . $summary['pct_mb_n1'] . ' %' : '');
        $sheet->setCellValue('M4', !empty($summary) ? '% MB vs N-1 : ' . $summary['pct_mb_vs_n1'] . ' %' : '');
        $sheet->getStyle('A4:V4')->applyFromArray($styleSummaryLabel);

        // === LIGNE 5 : En-têtes colonnes ===
        $headers = [
            'A' => 'Client',
            'B' => 'Raison sociale',
            'C' => 'Activité',
            'D' => 'DEPT',
            'E' => 'PAYS',
            'F' => 'Écart CA A-1',
            'G' => 'CA A',
            'H' => 'MB',
            'I' => '% MB',
            'J' => 'CA A-1',
            'K' => 'MB A-1',
            'L' => '% MB N-1',
            'M' => '% MB vs A-1',
            'N' => '% CA N vs N-1',
            'O' => 'Devis',
            'P' => 'Cdes',
            'Q' => 'Transformation devis en commande',
            'R' => 'Taux transformation devis en commande',
            'S' => 'Panier moyen',
            'T' => 'Avoirs',
            'U' => 'Nouveau client O/N',
            'V' => 'SIRET',
        ];
        foreach ($headers as $col => $label) {
            $sheet->setCellValue($col . '5', $label);
        }
        $sheet->getStyle('A5:V5')->applyFromArray($styleHeader);
        $sheet->getRowDimension(5)->setRowHeight(30);

        // === LIGNES 6+ : Données ===
        $excelRow = 6;
        foreach ($rows as $i => $row) {
            $sheet->setCellValue('A' . $excelRow, $row['customer_id']);
            $sheet->setCellValue('B' . $excelRow, $row['company']);
            $sheet->setCellValue('C' . $excelRow, $row['activity']);
            $sheet->setCellValue('D' . $excelRow, $row['dept']);
            $sheet->setCellValue('E' . $excelRow, $row['pays']);
            $sheet->setCellValue('F' . $excelRow, $row['ecart_ca_raw']);
            $sheet->setCellValue('G' . $excelRow, $row['ca_n_raw']);
            $sheet->setCellValue('H' . $excelRow, $row['mb_n_raw']);
            $sheet->setCellValue('I' . $excelRow, $row['pct_mb_n']);
            $sheet->setCellValue('J' . $excelRow, $row['ca_n1_raw']);
            $sheet->setCellValue('K' . $excelRow, $row['mb_n1_raw']);
            $sheet->setCellValue('L' . $excelRow, $row['pct_mb_n1']);
            $sheet->setCellValue('M' . $excelRow, $row['pct_mb_vs_n1']);
            $sheet->setCellValue('N' . $excelRow, $row['pct_ca_vs_n1']);
            $sheet->setCellValue('O' . $excelRow, $row['nb_devis']);
            $sheet->setCellValue('P' . $excelRow, $row['nb_commandes']);
            $sheet->setCellValue('Q' . $excelRow, $row['nb_devis_transformed']);
            $sheet->setCellValue('R' . $excelRow, $row['taux_transfo']);
            $sheet->setCellValue('S' . $excelRow, $row['panier_moyen']);
            $sheet->setCellValue('T' . $excelRow, $row['nb_avoirs']);
            $sheet->setCellValue('U' . $excelRow, $row['is_new_customer'] ? 'O' : 'N');
            $sheet->setCellValue('V' . $excelRow, $row['siret'] ?? '');

            if ($row['is_new_customer']) {
                $sheet->getStyle('A' . $excelRow . ':V' . $excelRow)->applyFromArray($styleNew);
            } elseif ($i % 2 === 1) {
                $sheet->getStyle('A' . $excelRow . ':V' . $excelRow)->applyFromArray($styleDataEven);
            }

            // Format numérique pour les montants
            $numFmt = '#,##0.00';
            foreach (['F', 'G', 'H', 'J', 'K', 'S'] as $col) {
                $sheet->getStyle($col . $excelRow)->getNumberFormat()
                    ->setFormatCode($numFmt);
            }
            // Format % pour les pourcentages (valeurs déjà en %)
            foreach (['I', 'L', 'M', 'N', 'R'] as $col) {
                $sheet->getStyle($col . $excelRow)->getNumberFormat()
                    ->setFormatCode('0.00"%"');
            }

            ++$excelRow;
        }

        // Auto-width approximatif
        $autoWidthCols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V'];
        foreach ($autoWidthCols as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Figer les volets à partir de la ligne 6 col C (2 premières colonnes figées)
        $sheet->freezePane('C6');

        // Centrer les 4 premières lignes
        $sheet->getStyle('A1:V4')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        // ---- Sortie HTTP ----
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
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
            'date expedition',
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
                $row['shipping_date'],
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

    private function exportMonthlyCsv($depannageRate, $year = null, $month = null)
    {
        if ($year === null || $month === null) {
            $date = new DateTime('first day of last month');
            $year = (int) $date->format('Y');
            $month = (int) $date->format('n');
        }

        $service = new KpiReportService($this->context);
        $rows = $service->getInvoicedKpisForMonth($year, $month, $depannageRate);

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
