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

    public function getDailyKpisForPeriod($yearFrom, $monthFrom, $yearTo, $monthTo, $depannageRate = 1.06)
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

        $dailyRows = $this->fetchDailyRows($startDate, $endDate, (float) $depannageRate);

        return $this->computeOrderRows($dailyRows);
    }

    public function getDailyKpisForLastMonth($depannageRate = 1.06)
    {
        $start = new DateTime('first day of last month');
        $end = new DateTime('last day of last month');

        $dailyRows = $this->fetchDailyRows($start->format('Y-m-d'), $end->format('Y-m-d'), (float) $depannageRate);

        return $this->computeOrderRows($dailyRows);
    }

    private function fetchDailyRows($startDate, $endDate, $depannageRate = 1.06)
    {
        // TODO: compute MB and marge nette once depannage rules are confirmed.
        $sql = new DbQuery();
        $sql->select('DATE(o.date_add) AS order_day');
        $sql->select('o.reference AS order_reference');
        $sql->select('DATE(o.invoice_date) AS invoice_day');
        $sql->select(
            '(SELECT IFNULL(SUM(od.total_price_tax_excl), 0)'
            . ' FROM ' . _DB_PREFIX_ . 'order_detail od'
            . ' LEFT JOIN ' . _DB_PREFIX_ . 'product p'
            . ' ON p.id_product = od.product_id'
            . ' LEFT JOIN ' . _DB_PREFIX_ . 'supplier sup'
            . ' ON sup.id_supplier = p.id_supplier'
            . ' WHERE od.id_order = o.id_order'
            . " AND (sup.name IS NULL OR sup.name != 'ITAL Express')"
            . ') AS ca_ht'
        );
        $sql->select(
            '(SELECT IFNULL(SUM(wod2.unit_price_te * wod2.quantity), 0)'
            . ' FROM ' . _DB_PREFIX_ . 'wkdelivery_order_detail wod2'
            . ' INNER JOIN ' . _DB_PREFIX_ . 'wkdelivery_orders wo2'
            . ' ON wo2.id_wkdelivery_orders = wod2.id_delivery'
            . ' LEFT JOIN ' . _DB_PREFIX_ . 'supplier wksup'
            . ' ON wksup.id_supplier = wo2.id_supplier'
            . " WHERE FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', ','))"
            . " AND (wksup.name IS NULL OR wksup.name != 'ITAL Express')"
            . ') AS depannage_ht'
        );
        $sql->select("IFNULL(GROUP_CONCAT(DISTINCT wo.reference ORDER BY wo.reference SEPARATOR ' | '), '') AS supplier_order_refs");
        $sql->select(
            '(SELECT IFNULL(SUM(od.product_quantity * COALESCE(ps.product_supplier_price_te, 0)), 0)'
            . ' FROM ' . _DB_PREFIX_ . 'order_detail od'
            . ' LEFT JOIN ' . _DB_PREFIX_ . 'product p'
            . ' ON p.id_product = od.product_id'
            . ' LEFT JOIN ' . _DB_PREFIX_ . 'supplier sup'
            . ' ON sup.id_supplier = p.id_supplier'
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
            . " AND (sup.name IS NULL OR sup.name != 'ITAL Express')"
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
            $depannageHtRaw = (float) $result['depannage_ht'];
            $depannageHt = $depannageHtRaw * (float) $depannageRate;
            $missingSupplierPurchaseHt = (float) $result['missing_supplier_purchase_ht'];

            $rows[] = [
                'order_date' => $result['order_day'],
                'order_reference' => $result['order_reference'],
                'invoice_date' => $result['invoice_day'] ?: $result['order_day'],
                'ca_ht' => (float) $result['ca_ht'],
                'depannage_ht' => $depannageHt,
                'supplier_order_refs' => (string) $result['supplier_order_refs'],
                'mb_ht' => (float) $result['ca_ht']
                    - $depannageHt
                    - $missingSupplierPurchaseHt,
                'marge_nette' => (float) $result['ca_ht']
                    - $depannageHtRaw
                    - $missingSupplierPurchaseHt,
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

    private function computeOrderRows(array $dailyRows)
    {
        $rows = [];

        foreach ($dailyRows as $row) {
            $caHt = (float) $row['ca_ht'];
            $depannageHt = (float) ($row['depannage_ht'] ?? 0.0);
            $mbHt = (float) $row['mb_ht'];
            $margeNette = (float) $row['marge_nette'];

            $rows[] = [
                'order_date' => $this->formatDate($row['order_date']),
                'order_reference' => $row['order_reference'],
                'invoice_date' => $this->formatDate($row['invoice_date']),
                'ca_ht' => $this->formatAmount($caHt),
                'depannage_ht' => $this->formatAmount($depannageHt),
                'mb_ht' => $this->formatAmount($mbHt),
                'marge_nette' => $this->formatAmount($margeNette),
                'supplier_order_refs' => $row['supplier_order_refs'],
                'pct_mb_ht' => $this->formatPercent($mbHt, $caHt),
                'pct_marge_nette' => $this->formatPercent($margeNette, $caHt),
            ];
        }

        return $rows;
    }

    private function formatDate($value)
    {
        if (empty($value)) {
            return '';
        }

        $date = DateTime::createFromFormat('Y-m-d', (string) $value);
        if (!$date) {
            return (string) $value;
        }

        return $date->format('d/m/Y');
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
