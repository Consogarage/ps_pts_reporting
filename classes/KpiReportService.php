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
        $sql->select('o.reference AS order_reference');
        $sql->select('DATE(o.invoice_date) AS invoice_day');
        $sql->select('o.total_products AS ca_ht');
        $sql->select('IFNULL(SUM(wod.unit_price_te * wod.quantity), 0) AS depannage_ht');
        $sql->select("IFNULL(GROUP_CONCAT(DISTINCT wo.reference ORDER BY wo.reference SEPARATOR ' | '), '') AS supplier_order_refs");
        $sql->select(
            '(SELECT IFNULL(SUM(od.product_quantity), 0)'
            . ' FROM ' . _DB_PREFIX_ . 'order_detail od'
            . ' LEFT JOIN ' . _DB_PREFIX_ . 'wkdelivery_order_detail wlink'
            . ' ON wlink.id_product = od.product_id'
            . ' AND wlink.id_product_attribute = od.product_attribute_id'
            . " AND FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wlink.customer_id_orders), '|', ','))"
            . ' WHERE od.id_order = o.id_order'
            . ' AND wlink.id_wkdelivery_order_detail IS NULL'
            . ') AS missing_supplier_qty'
        );
        $sql->select(
            '(SELECT IFNULL(SUM(od.product_quantity * COALESCE(ps.product_supplier_price_te, 0)), 0)'
            . ' FROM ' . _DB_PREFIX_ . 'order_detail od'
            . ' LEFT JOIN ' . _DB_PREFIX_ . 'product p'
            . ' ON p.id_product = od.product_id'
            . ' LEFT JOIN ' . _DB_PREFIX_ . 'product_supplier ps'
            . ' ON ps.id_product = od.product_id'
            . ' AND ps.id_product_attribute = od.product_attribute_id'
            . ' AND ps.id_supplier = p.id_supplier'
            . ' LEFT JOIN ' . _DB_PREFIX_ . 'wkdelivery_order_detail wlink'
            . ' ON wlink.id_product = od.product_id'
            . ' AND wlink.id_product_attribute = od.product_attribute_id'
            . " AND FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wlink.customer_id_orders), '|', ','))"
            . ' WHERE od.id_order = o.id_order'
            . ' AND wlink.id_wkdelivery_order_detail IS NULL'
            . ') AS missing_supplier_purchase_ht'
        );
        $sql->from('orders', 'o');
        $sql->leftJoin(
            'wkdelivery_order_detail',
            'wod',
            "FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod.customer_id_orders), '|', ','))"
        );
        $sql->leftJoin('wkdelivery_orders', 'wo', 'wo.id_wkdelivery_orders = wod.id_delivery');
        $sql->where("o.date_add >= '" . pSQL($startDate . ' 00:00:00') . "'");
        $sql->where("o.date_add <= '" . pSQL($endDate . ' 23:59:59') . "'");
        $sql->groupBy('o.id_order');
        $sql->orderBy('o.date_add ASC');

        $results = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        $rows = [];

        foreach ($results as $result) {
            $rows[] = [
                'order_date' => $result['order_day'],
                'order_reference' => $result['order_reference'],
                'invoice_date' => $result['invoice_day'] ?: $result['order_day'],
                'ca_ht' => (float) $result['ca_ht'],
                'depannage_ht' => (float) $result['depannage_ht'],
                'supplier_order_refs' => (string) $result['supplier_order_refs'],
                'missing_supplier_qty' => (int) $result['missing_supplier_qty'],
                'missing_supplier_purchase_ht' => (float) $result['missing_supplier_purchase_ht'],
                'mb_ht' => (float) $result['ca_ht']
                    - (float) $result['depannage_ht']
                    - (float) $result['missing_supplier_purchase_ht'],
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
        $cumulDepannageHt = 0.0;
        $cumulMbHt = 0.0;
        $cumulMargeNette = 0.0;
        $cumulMissingSupplierQty = 0;
        $cumulMissingSupplierPurchaseHt = 0.0;
        $rows = [];

        foreach ($dailyRows as $row) {
            $cumulCaHt += (float) $row['ca_ht'];
            $cumulDepannageHt += (float) ($row['depannage_ht'] ?? 0.0);
            $cumulMbHt += (float) $row['mb_ht'];
            $cumulMargeNette += (float) $row['marge_nette'];
            $cumulMissingSupplierQty += (int) ($row['missing_supplier_qty'] ?? 0);
            $cumulMissingSupplierPurchaseHt += (float) ($row['missing_supplier_purchase_ht'] ?? 0.0);

            $rows[] = [
                'order_date' => $row['order_date'],
                'order_reference' => $row['order_reference'],
                'invoice_date' => $row['invoice_date'],
                'cumul_ca_ht' => $this->formatAmount($cumulCaHt),
                'cumul_depannage_ht' => $this->formatAmount($cumulDepannageHt),
                'cumul_mb_ht' => $this->formatAmount($cumulMbHt),
                'cumul_marge_nette' => $this->formatAmount($cumulMargeNette),
                'supplier_order_refs' => $row['supplier_order_refs'],
                'cumul_missing_supplier_qty' => $cumulMissingSupplierQty,
                'cumul_missing_supplier_purchase_ht' => $this->formatAmount($cumulMissingSupplierPurchaseHt),
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
