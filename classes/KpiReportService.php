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
        return $this->getDailyKpisForPeriod($year, $month, $year, $month);
    }

    public function getDailyKpisForPeriod($yearFrom, $monthFrom, $yearTo, $monthTo)
    {
        $startDate = sprintf('%04d-%02d-01', $yearFrom, $monthFrom);
        $endDate = $this->getMonthEndDate($yearTo, $monthTo);

        if (strtotime($endDate) < strtotime($startDate)) {
            $tmp = $yearFrom;
            $yearFrom = $yearTo;
            $yearTo = $tmp;

            $tmp = $monthFrom;
            $monthFrom = $monthTo;
            $monthTo = $tmp;

            $startDate = sprintf('%04d-%02d-01', $yearFrom, $monthFrom);
            $endDate = $this->getMonthEndDate($yearTo, $monthTo);
        }

        if ($endDate > date('Y-m-d')) {
            $endDate = date('Y-m-d');
        }

        $dailyRows = $this->fetchDailyRows($startDate, $endDate);

        return $this->computeCumulativeRows($dailyRows);
    }

    public function getDailyKpisForLastMonth()
    {
        $start = new DateTime('first day of last month');
        $end = new DateTime('last day of last month');

        $dailyRows = $this->fetchDailyRows($start->format('Y-m-d'), $end->format('Y-m-d'));

        return $this->computeCumulativeRows($dailyRows);
    }

    private function fetchDailyRows($startDate, $endDate)
    {
        // TODO: compute MB and marge nette once depannage rules are confirmed.
        $sql = new DbQuery();
        $sql->select('DATE(o.date_add) AS order_day');
        $sql->select('DATE(o.invoice_date) AS invoice_day');
        $sql->select('o.total_products AS ca_ht');
        $sql->from('orders', 'o');
        $sql->where("o.date_add >= '" . pSQL($startDate . ' 00:00:00') . "'");
        $sql->where("o.date_add <= '" . pSQL($endDate . ' 23:59:59') . "'");
        $sql->orderBy('o.date_add ASC');

        $results = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        $rows = [];

        foreach ($results as $result) {
            $rows[] = [
                'order_date' => $result['order_day'],
                'invoice_date' => $result['invoice_day'] ?: $result['order_day'],
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
                'order_date' => $row['order_date'],
                'invoice_date' => $row['invoice_date'],
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
