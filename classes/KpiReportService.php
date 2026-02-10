<?php

class KpiReportService
{
    private $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function getDailyKpis($year, $month)
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = $this->getMonthEndDate($year, $month);
        if ($endDate > date('Y-m-d')) {
            $endDate = date('Y-m-d');
        }

        $dailyRows = $this->fetchDailyRows($startDate, $endDate);

        return $this->computeCumulativeRows($dailyRows);
    }

    private function fetchDailyRows($startDate, $endDate)
    {
        // TODO: compute MB and marge nette once depannage rules are confirmed.
        $sql = new DbQuery();
        $sql->select('DATE(oi.date_add) AS day');
        $sql->select('SUM(oi.total_products_tax_excl) AS ca_ht');
        $sql->from('order_invoice', 'oi');
        $sql->where("oi.date_add >= '" . pSQL($startDate . ' 00:00:00') . "'");
        $sql->where("oi.date_add <= '" . pSQL($endDate . ' 23:59:59') . "'");
        $sql->groupBy('DATE(oi.date_add)');
        $sql->orderBy('DATE(oi.date_add) ASC');

        $results = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        $rows = [];

        foreach ($results as $result) {
            $rows[] = [
                'date' => $result['day'],
                'ca_ht' => (float) $result['ca_ht'],
                'mb_ht' => 0.0,
                'marge_nette' => 0.0,
            ];
        }

        return $rows;
    }

    private function getMonthEndDate($year, $month)
    {
        $date = DateTime::createFromFormat('Y-n-j', $year . '-' . $month . '-1');
        if (!$date) {
            return date('Y-m-d');
        }

        return $date->modify('last day of this month')->format('Y-m-d');
    }

    private function computeCumulativeRows(array $dailyRows)
    {
        $cumulCaHt = 0.0;
        $cumulMbHt = 0.0;
        $cumulMargeNette = 0.0;
        $rows = [];

        foreach ($dailyRows as $row) {
            $cumulCaHt += (float) $row['ca_ht'];
            $cumulMbHt += (float) $row['mb_ht'];
            $cumulMargeNette += (float) $row['marge_nette'];

            $rows[] = [
                'date' => $row['date'],
                'cumul_ca_ht' => $this->formatAmount($cumulCaHt),
                'cumul_mb_ht' => $this->formatAmount($cumulMbHt),
                'cumul_marge_nette' => $this->formatAmount($cumulMargeNette),
                'cumul_pct_mb_ht' => $this->formatPercent($cumulMbHt, $cumulCaHt),
                'cumul_pct_marge_nette' => $this->formatPercent($cumulMargeNette, $cumulCaHt),
            ];
        }

        return $rows;
    }

    private function formatAmount($value)
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function formatPercent($numerator, $denominator)
    {
        if ((float) $denominator === 0.0) {
            return '0.00';
        }

        $value = ((float) $numerator) * 100 / (float) $denominator;

        return number_format($value, 2, '.', '');
    }
}
