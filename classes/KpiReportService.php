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

        $rows = $this->fetchOrderRowsByInvoicePeriod($startDate, $endDate, (float) $depannageRate);

        return $this->computeOrderRows($rows);
    }

    /**
     * Récupère les commandes facturées dans la période donnée (via ps_order_invoice),
     * avec le CA HT, le dépannage proportionnel et les références fournisseur.
     * Utilisé pour le tableau backoffice.
     */
    private function fetchOrderRowsByInvoicePeriod($startDate, $endDate, $depannageRate = 1.06)
    {
        $start = pSQL($startDate . ' 00:00:00');
        $end = pSQL($endDate . ' 23:59:59');
        $prefix = _DB_PREFIX_;

        // Sous-requête de proportion dépannage (identique à la requête de référence)
        $depannageProportion =
            "((LENGTH(IFNULL(wod2.customer_id_orders, '')) - LENGTH(REPLACE(IFNULL(wod2.customer_id_orders, ''), CONCAT('|', o.id_order, '|'), ''))) / LENGTH(CONCAT('|', o.id_order, '|')))"
            . " / (CASE WHEN TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')) = '' THEN 1"
            . " ELSE (LENGTH(TRIM(BOTH '|' FROM wod2.customer_id_orders)) - LENGTH(REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', '')) + 1)"
            . " END)";

        $depannageSubquery =
            "(SELECT IFNULL(SUM(wod2.unit_price_te * wod2.quantity * ({$depannageProportion})), 0)"
            . " FROM {$prefix}wkdelivery_order_detail wod2"
            . " INNER JOIN {$prefix}wkdelivery_orders wo2 ON wo2.id_wkdelivery_orders = wod2.id_delivery"
            . " LEFT JOIN {$prefix}supplier wksup ON wksup.id_supplier = wo2.id_supplier"
            . " WHERE FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', ','))"
            . " AND (wksup.name IS NULL OR LOWER(wksup.name) != 'ital express'))";

        $invoiceDateSubquery =
            "(SELECT DATE(MIN(oi1.date_add)) FROM {$prefix}order_invoice oi1"
            . " WHERE oi1.id_order = o.id_order"
            . " AND oi1.date_add >= '{$start}'"
            . " AND oi1.date_add <= '{$end}')";

        $sql = new DbQuery();
        $sql->select('o.id_order');
        $sql->select('o.reference AS order_reference');
        $sql->select('DATE(o.date_add) AS order_day');
        $sql->select($invoiceDateSubquery . ' AS invoice_day');
        $sql->select(
            "(SELECT IFNULL(SUM(od.total_price_tax_excl), 0)"
            . " FROM {$prefix}order_detail od"
            . " LEFT JOIN {$prefix}product p ON p.id_product = od.product_id"
            . " LEFT JOIN {$prefix}supplier sup ON sup.id_supplier = p.id_supplier"
            . " WHERE od.id_order = o.id_order"
            . " AND (sup.name IS NULL OR LOWER(sup.name) != 'ital express')) AS ca_ht"
        );
        $sql->select($depannageSubquery . ' AS depannage_ht_raw');
        $sql->select(
            "IFNULL(GROUP_CONCAT(DISTINCT CONCAT(wo.reference, ' [', wod.quantity, 'x ',"
            . " COALESCE(NULLIF(wod.supplier_reference, ''), CONCAT('#', wod.id_product, IF(wod.id_product_attribute > 0, CONCAT('-', wod.id_product_attribute), ''))),"
            . " ']') ORDER BY wo.reference SEPARATOR ' | '), '') AS supplier_order_refs"
        );
        $sql->from('orders', 'o');
        $sql->leftJoin(
            'wkdelivery_order_detail',
            'wod',
            "FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod.customer_id_orders), '|', ','))"
        );
        $sql->leftJoin('wkdelivery_orders', 'wo', 'wo.id_wkdelivery_orders = wod.id_delivery');
        $sql->where(
            "EXISTS (SELECT 1 FROM {$prefix}order_invoice oi"
            . " WHERE oi.id_order = o.id_order"
            . " AND oi.date_add >= '{$start}'"
            . " AND oi.date_add <= '{$end}')"
        );
        $sql->where('o.current_state IN (4, 5, 18)');
        $sql->groupBy('o.id_order');
        $sql->orderBy('invoice_day ASC');

        $results = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        $rows = [];

        foreach ($results as $result) {
            $caHt = (float) $result['ca_ht'];
            $depannageHtRaw = (float) $result['depannage_ht_raw'];
            $depannageHt = $depannageHtRaw * (float) $depannageRate;

            $rows[] = [
                'order_date' => $result['order_day'],
                'order_reference' => $result['order_reference'],
                'invoice_date' => $result['invoice_day'] ?: $result['order_day'],
                'ca_ht' => $caHt,
                'depannage_ht' => $depannageHt,
                'supplier_order_refs' => (string) $result['supplier_order_refs'],
                'mb_ht' => $caHt - $depannageHt,
                'marge_nette' => $caHt - $depannageHtRaw,
            ];
        }

        return $rows;
    }

    /**
     * KPI clients pour un mois donné vs N-1 (même mois, année précédente).
     * Colonnes : client, activité, dept, pays, CA N, CA N-1, écart CA, % CA vs N-1,
     *            MB N, % MB N, MB N-1, % MB N-1, % MB vs N-1,
     *            devis, commandes, devis transformés, taux transfo, panier moyen,
     *            avoirs, nouveau client.
     */
    public function getCustomerKpis($year, $month)
    {
        $prefix = _DB_PREFIX_;

        $dateN = DateTime::createFromFormat('Y-n-j', $year . '-' . $month . '-1');
        $dateN->modify('last day of this month');
        $startN = pSQL(sprintf('%04d-%02d-01 00:00:00', $year, $month));
        $endN = pSQL($dateN->format('Y-m-d') . ' 23:59:59');

        $yearN1 = $year - 1;
        $dateN1 = DateTime::createFromFormat('Y-n-j', $yearN1 . '-' . $month . '-1');
        $dateN1->modify('last day of this month');
        $startN1 = pSQL(sprintf('%04d-%02d-01 00:00:00', $yearN1, $month));
        $endN1 = pSQL($dateN1->format('Y-m-d') . ' 23:59:59');

        // Filtre commandes valides (hors annulées / remboursées)
        $validStates = 'NOT IN (6, 7)';

        // Existence facture dans période N
        $invoicedN = "EXISTS (SELECT 1 FROM {$prefix}order_invoice oi WHERE oi.id_order = o.id_order AND oi.date_add >= '{$startN}' AND oi.date_add <= '{$endN}')";
        $invoicedN1 = "EXISTS (SELECT 1 FROM {$prefix}order_invoice oi WHERE oi.id_order = o.id_order AND oi.date_add >= '{$startN1}' AND oi.date_add <= '{$endN1}')";

        // Sous-requête : CA pour une période (somme toutes lignes commandes)
        $caN = "(SELECT IFNULL(SUM(od.total_price_tax_excl), 0)"
            . " FROM {$prefix}orders o INNER JOIN {$prefix}order_detail od ON od.id_order = o.id_order"
            . " WHERE o.id_customer = c.id_customer AND o.current_state {$validStates} AND {$invoicedN})";

        $caN1 = "(SELECT IFNULL(SUM(od.total_price_tax_excl), 0)"
            . " FROM {$prefix}orders o INNER JOIN {$prefix}order_detail od ON od.id_order = o.id_order"
            . " WHERE o.id_customer = c.id_customer AND o.current_state {$validStates} AND {$invoicedN1})";

        // Sous-requête : achats dépannage pour une période (somme wkdelivery)
        $depannageN = "(SELECT IFNULL(SUM(wod.unit_price_te * wod.quantity), 0)"
            . " FROM {$prefix}orders o"
            . " INNER JOIN {$prefix}wkdelivery_order_detail wod"
            . "   ON FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod.customer_id_orders), '|', ','))"
            . " WHERE o.id_customer = c.id_customer AND o.current_state {$validStates} AND {$invoicedN})";

        $depannageN1 = "(SELECT IFNULL(SUM(wod.unit_price_te * wod.quantity), 0)"
            . " FROM {$prefix}orders o"
            . " INNER JOIN {$prefix}wkdelivery_order_detail wod"
            . "   ON FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod.customer_id_orders), '|', ','))"
            . " WHERE o.id_customer = c.id_customer AND o.current_state {$validStates} AND {$invoicedN1})";

        // Sous-requête : nombre de commandes dans une période
        $nbCmdsN = "(SELECT IFNULL(COUNT(DISTINCT o.id_order), 0) FROM {$prefix}orders o WHERE o.id_customer = c.id_customer AND o.current_state {$validStates} AND {$invoicedN})";
        $nbCmdsN1 = "(SELECT IFNULL(COUNT(DISTINCT o.id_order), 0) FROM {$prefix}orders o WHERE o.id_customer = c.id_customer AND o.current_state {$validStates} AND {$invoicedN1})";

        // Sous-requête : activité B2B (champ custom id=1)
        $activitySub = "(SELECT COALESCE(cfvl.value, rv.field_value)"
            . " FROM {$prefix}presta_btwob_registration_value rv"
            . " LEFT JOIN {$prefix}presta_btwob_custom_fields_value_lang cfvl"
            . "   ON cfvl.id_multi_value = CAST(REPLACE(REPLACE(REPLACE(rv.field_value,'[',''),']',''),'\"','') AS UNSIGNED)"
            . "   AND cfvl.id_lang = 1"
            . " WHERE rv.id_customer = c.id_customer AND rv.field_id = 1"
            . " ORDER BY rv.id_presta_btwob_registration_value DESC LIMIT 1)";

        // Sous-requête : code postal (première adresse)
        $deptSub = "(SELECT a.postcode FROM {$prefix}address a WHERE a.id_customer = c.id_customer AND a.deleted = 0 ORDER BY a.id_address ASC LIMIT 1)";

        // Sous-requête : pays (première adresse)
        $paysSub = "(SELECT cl.name FROM {$prefix}address a LEFT JOIN {$prefix}country_lang cl ON cl.id_country = a.id_country AND cl.id_lang = 1 WHERE a.id_customer = c.id_customer AND a.deleted = 0 ORDER BY a.id_address ASC LIMIT 1)";

        // Sous-requête : devis N (table opartdevis)
        $nbDevisN = "(SELECT IFNULL(COUNT(DISTINCT d.id_opartdevis), 0) FROM {$prefix}opartdevis d WHERE d.id_customer = c.id_customer AND d.date_add >= '{$startN}' AND d.date_add <= '{$endN}')";

        // Sous-requête : devis transformés en commande (status=2) N
        $nbDevisTransformedN = "(SELECT IFNULL(COUNT(DISTINCT d.id_opartdevis), 0) FROM {$prefix}opartdevis d WHERE d.id_customer = c.id_customer AND d.date_add >= '{$startN}' AND d.date_add <= '{$endN}' AND d.status = 2)";

        // Sous-requête : avoirs N
        $nbAvoirsN = "(SELECT IFNULL(COUNT(DISTINCT os.id_order_slip), 0) FROM {$prefix}order_slip os WHERE os.id_customer = c.id_customer AND os.date_add >= '{$startN}' AND os.date_add <= '{$endN}')";

        // Nouveau client : première facture dans la période N
        $nouveauClient = "CASE WHEN (SELECT MIN(oi.date_add) FROM {$prefix}orders o INNER JOIN {$prefix}order_invoice oi ON oi.id_order = o.id_order WHERE o.id_customer = c.id_customer) BETWEEN '{$startN}' AND '{$endN}' THEN 1 ELSE 0 END";

        $sql = "SELECT
            c.id_customer,
            c.company,
            IFNULL({$activitySub}, '') AS activity,
            IFNULL({$deptSub}, '') AS dept,
            IFNULL({$paysSub}, '') AS pays,
            ROUND({$caN}, 2) AS ca_n,
            ROUND({$caN1}, 2) AS ca_n1,
            ROUND({$caN} - {$caN1}, 2) AS ecart_ca,
            ROUND({$caN} - {$depannageN}, 2) AS mb_n,
            ROUND({$caN1} - {$depannageN1}, 2) AS mb_n1,
            {$nbDevisN} AS nb_devis,
            {$nbCmdsN} AS nb_commandes,
            {$nbDevisTransformedN} AS nb_devis_transformed,
            {$nbAvoirsN} AS nb_avoirs,
            {$nouveauClient} AS is_new_customer
        FROM {$prefix}customer c
        WHERE {$nbCmdsN} > 0 OR {$nbCmdsN1} > 0
        ORDER BY c.company ASC";

        $results = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        $rows = [];

        if (!is_array($results)) {
            return ['rows' => [], 'summary' => []];
        }

        // Variables d'agrégation
        $sumCaN = 0.0;
        $sumCaN1 = 0.0;
        $sumMbN = 0.0;
        $sumMbN1 = 0.0;
        $sumDevis = 0;
        $sumCmds = 0;
        $sumTransfo = 0;
        $sumAvoirs = 0;
        $nbActifsN = 0;
        $nbActifsN1 = 0;
        $nbNouveaux = 0;

        foreach ($results as $r) {
            $caN_val = (float) $r['ca_n'];
            $caN1_val = (float) $r['ca_n1'];
            $mbN_val = (float) $r['mb_n'];
            $mbN1_val = (float) $r['mb_n1'];
            $nbDevis = (int) $r['nb_devis'];
            $nbCmds = (int) $r['nb_commandes'];
            $nbTransfo = (int) $r['nb_devis_transformed'];
            $isNew = (bool) $r['is_new_customer'];

            // Agrégats bruts
            $sumCaN += $caN_val;
            $sumCaN1 += $caN1_val;
            $sumMbN += $mbN_val;
            $sumMbN1 += $mbN1_val;
            $sumDevis += $nbDevis;
            $sumCmds += $nbCmds;
            $sumTransfo += $nbTransfo;
            $sumAvoirs += (int) $r['nb_avoirs'];
            if ($caN_val > 0) {
                ++$nbActifsN;
            }
            if ($caN1_val > 0) {
                ++$nbActifsN1;
            }
            if ($isNew) {
                ++$nbNouveaux;
            }

            $rows[] = [
                'customer_id' => (int) $r['id_customer'],
                'company' => (string) $r['company'],
                'activity' => (string) $r['activity'],
                'dept' => (string) $r['dept'],
                'pays' => (string) $r['pays'],
                'ca_n' => $this->formatAmount($caN_val),
                'ca_n_raw' => $caN_val,
                'ca_n1' => $this->formatAmount($caN1_val),
                'ca_n1_raw' => $caN1_val,
                'ecart_ca' => $this->formatAmount($caN_val - $caN1_val),
                'ecart_ca_raw' => $caN_val - $caN1_val,
                'pct_ca_vs_n1' => $this->formatPercent($caN_val - $caN1_val, $caN1_val),
                'mb_n' => $this->formatAmount($mbN_val),
                'mb_n_raw' => $mbN_val,
                'pct_mb_n' => $this->formatPercent($mbN_val, $caN_val),
                'mb_n1' => $this->formatAmount($mbN1_val),
                'mb_n1_raw' => $mbN1_val,
                'pct_mb_n1' => $this->formatPercent($mbN1_val, $caN1_val),
                'pct_mb_vs_n1' => $this->formatPercent($mbN_val - $mbN1_val, $mbN1_val),
                'nb_devis' => $nbDevis,
                'nb_commandes' => $nbCmds,
                'nb_devis_transformed' => $nbTransfo,
                'taux_transfo' => $this->formatPercent($nbTransfo, $nbDevis),
                'panier_moyen' => $this->formatAmount($nbCmds > 0 ? $caN_val / $nbCmds : 0),
                'nb_avoirs' => (int) $r['nb_avoirs'],
                'is_new_customer' => $isNew,
            ];
        }

        $ecartCaTotal = $sumCaN - $sumCaN1;
        $panierMoyenGlobal = $sumCmds > 0 ? $sumCaN / $sumCmds : 0.0;

        $summary = [
            'nb_actifs_n' => $nbActifsN,
            'nb_actifs_n1' => $nbActifsN1,
            'evol_nb_clients' => $this->formatPercent($nbActifsN - $nbActifsN1, $nbActifsN1),
            'total_ca_n' => $this->formatAmount($sumCaN),
            'total_ca_n1' => $this->formatAmount($sumCaN1),
            'ecart_ca' => $this->formatAmount($ecartCaTotal),
            'pct_ca_vs_n1' => $this->formatPercent($ecartCaTotal, $sumCaN1),
            'total_mb_n' => $this->formatAmount($sumMbN),
            'pct_mb_n' => $this->formatPercent($sumMbN, $sumCaN),
            'total_mb_n1' => $this->formatAmount($sumMbN1),
            'pct_mb_n1' => $this->formatPercent($sumMbN1, $sumCaN1),
            'pct_mb_vs_n1' => $this->formatPercent($sumMbN - $sumMbN1, $sumMbN1),
            'total_devis' => $sumDevis,
            'total_cmds' => $sumCmds,
            'total_transfo' => $sumTransfo,
            'taux_transfo' => $this->formatPercent($sumTransfo, $sumDevis),
            'panier_moyen' => $this->formatAmount($panierMoyenGlobal),
            'total_avoirs' => $sumAvoirs,
            'nb_nouveaux' => $nbNouveaux,
        ];

        return ['rows' => $rows, 'summary' => $summary];
    }

    public function getDailyKpisForLastMonth($depannageRate = 1.06)
    {
        $start = new DateTime('first day of last month');
        $end = new DateTime('last day of last month');

        $dailyRows = $this->fetchDailyRows($start->format('Y-m-d'), $end->format('Y-m-d'), (float) $depannageRate);

        return $this->computeOrderRows($dailyRows);
    }

    public function getInvoicedKpisForMonth($year, $month, $depannageRate = 1.06)
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = $this->getMonthEndDate($year, $month);

        if ($endDate > date('Y-m-d')) {
            $endDate = date('Y-m-d');
        }

        $rows = $this->fetchOrderRowsByInvoicePeriod($startDate, $endDate, (float) $depannageRate);

        return $this->computeOrderRows($rows);
    }

    private function fetchDailyRows($startDate, $endDate, $depannageRate = 1.06, $dateColumn = 'o.date_add', $onlyInvoiced = false)
    {
        $dateColumn = ($dateColumn === 'o.invoice_date') ? 'o.invoice_date' : 'o.date_add';

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
            . " AND (sup.name IS NULL OR LOWER(sup.name) != 'ital express')"
            . ') AS ca_ht'
        );
        $sql->select(
            "(SELECT IFNULL(SUM((wod2.unit_price_te * wod2.quantity) * ("
            . " ((LENGTH(CONCAT(',', REPLACE(TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')), '|', ','), ','))"
            . " - LENGTH(REPLACE(CONCAT(',', REPLACE(TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')), '|', ','), ','), CONCAT(',', o.id_order, ','), '')))"
            . " / LENGTH(CONCAT(',', o.id_order, ',')))"
            . " / (CASE"
            . " WHEN TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')) = '' THEN 1"
            . " ELSE (LENGTH(TRIM(BOTH '|' FROM wod2.customer_id_orders)) - LENGTH(REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', '')) + 1)"
            . " END)"
            . " )), 0)"
            . ' FROM ' . _DB_PREFIX_ . 'wkdelivery_order_detail wod2'
            . ' INNER JOIN ' . _DB_PREFIX_ . 'wkdelivery_orders wo2'
            . ' ON wo2.id_wkdelivery_orders = wod2.id_delivery'
            . ' LEFT JOIN ' . _DB_PREFIX_ . 'supplier wksup'
            . ' ON wksup.id_supplier = wo2.id_supplier'
            . " WHERE FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', ','))"
            . " AND (wksup.name IS NULL OR LOWER(wksup.name) != 'ital express')"
            . ') AS depannage_ht'
        );
        $sql->select("IFNULL(GROUP_CONCAT(DISTINCT CONCAT(wo.reference, ' [', wod.quantity, 'x ', COALESCE(NULLIF(wod.supplier_reference, ''), CONCAT('#', wod.id_product, IF(wod.id_product_attribute > 0, CONCAT('-', wod.id_product_attribute), ''))), ']') ORDER BY wo.reference SEPARATOR ' | '), '') AS supplier_order_refs");
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
            . " AND (sup.name IS NULL OR LOWER(sup.name) != 'ital express')"
            . ') AS missing_supplier_purchase_ht'
        );
        $sql->from('orders', 'o');
        $sql->leftJoin(
            'wkdelivery_order_detail',
            'wod',
            "FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod.customer_id_orders), '|', ','))"
        );
        $sql->leftJoin('wkdelivery_orders', 'wo', 'wo.id_wkdelivery_orders = wod.id_delivery');
        $sql->where($dateColumn . " >= '" . pSQL($startDate . ' 00:00:00') . "'");
        $sql->where($dateColumn . " <= '" . pSQL($endDate . ' 23:59:59') . "'");
        if ($onlyInvoiced) {
            $sql->where('o.invoice_date IS NOT NULL');
            $sql->where("o.invoice_date != '0000-00-00 00:00:00'");
        }
        $sql->groupBy('o.id_order');
        $sql->orderBy($dateColumn . ' ASC');

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

        $rawValue = trim((string) $value);
        if ($rawValue === '0000-00-00' || $rawValue === '0000-00-00 00:00:00') {
            return '';
        }

        $date = DateTime::createFromFormat('Y-m-d', $rawValue);
        if (!$date) {
            return $rawValue;
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
