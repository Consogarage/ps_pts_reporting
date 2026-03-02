SELECT
    COUNT(*) AS nb_commandes,
    ROUND(SUM(x.ca_ht), 2) AS total_ca_ht,
    ROUND(SUM(x.depannage_ht_raw) * 1.06, 2) AS total_depannage_ht,
    ROUND(SUM(x.missing_supplier_items_count), 0) AS total_missing_supplier_items_count,
    ROUND(SUM(x.ca_ht) - (SUM(x.depannage_ht_raw) * 1.06), 2) AS total_mb_ht,
    ROUND(SUM(x.ca_ht) - SUM(x.depannage_ht_raw), 2) AS total_marge_nette,
    ROUND(
        CASE
            WHEN SUM(x.ca_ht) = 0 THEN 0
            ELSE (SUM(x.ca_ht) - (SUM(x.depannage_ht_raw) * 1.06)) * 100 / SUM(x.ca_ht)
        END,
        2
    ) AS pct_mb_ht,
    ROUND(
        CASE
            WHEN SUM(x.ca_ht) = 0 THEN 0
            ELSE (SUM(x.ca_ht) - SUM(x.depannage_ht_raw)) * 100 / SUM(x.ca_ht)
        END,
        2
    ) AS pct_marge_nette
FROM (
    SELECT
        o.id_order,
        (
            SELECT IFNULL(SUM(od.total_price_tax_excl), 0)
            FROM ps_order_detail od
            WHERE od.id_order = o.id_order
        ) AS ca_ht,
        (
            SELECT IFNULL(SUM((wod2.unit_price_te * wod2.quantity) * ((((LENGTH(CONCAT(',', REPLACE(TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')), '|', ','), ',')) - LENGTH(REPLACE(CONCAT(',', REPLACE(TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')), '|', ','), ','), CONCAT(',', o.id_order, ','), ''))) / LENGTH(CONCAT(',', o.id_order, ','))) / (CASE WHEN TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')) = '' THEN 1 ELSE (LENGTH(TRIM(BOTH '|' FROM wod2.customer_id_orders)) - LENGTH(REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', '')) + 1) END))), 0)
            FROM ps_wkdelivery_order_detail wod2
            INNER JOIN ps_wkdelivery_orders wo2
                ON wo2.id_wkdelivery_orders = wod2.id_delivery
            WHERE FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', ','))
        ) AS depannage_ht_raw,
        (
            SELECT IFNULL(SUM(od2.product_quantity), 0)
            FROM ps_order_detail od2
            LEFT JOIN ps_wkdelivery_order_detail wlink
                ON wlink.id_product = od2.product_id
                AND wlink.id_product_attribute = od2.product_attribute_id
                AND FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wlink.customer_id_orders), '|', ','))
            WHERE od2.id_order = o.id_order
              AND wlink.id_wkdelivery_order_detail IS NULL
        ) AS missing_supplier_items_count
    FROM ps_orders o
        WHERE EXISTS (
                SELECT 1
                FROM ps_order_invoice oi1
                WHERE oi1.id_order = o.id_order
                    AND oi1.date_add >= '2026-02-01 00:00:00'
                    AND oi1.date_add <= '2026-02-28 23:59:59'
                    AND oi1.date_add <> '0000-00-00 00:00:00'
        )
            AND o.current_state IN (4, 5, 18)
) x;