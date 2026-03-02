SELECT
    o.id_order,
    o.reference AS order_reference,
    DATE(o.date_add) AS order_date,
    DATE(
        (
            SELECT MIN(oi1.date_add)
            FROM ps_order_invoice oi1
            WHERE oi1.id_order = o.id_order
              AND oi1.date_add >= '2026-02-01 00:00:00'
              AND oi1.date_add <= '2026-02-28 23:59:59'
        )
    ) AS invoice_date,
    ROUND(
        (
            SELECT IFNULL(SUM(od.total_price_tax_excl), 0)
            FROM ps_order_detail od
            WHERE od.id_order = o.id_order
        ),
        2
    ) AS ca_ht,
    ROUND(
        (
            SELECT IFNULL(SUM((wod2.unit_price_te * wod2.quantity) * (((LENGTH(IFNULL(wod2.customer_id_orders, '')) - LENGTH(REPLACE(IFNULL(wod2.customer_id_orders, ''), CONCAT('|', o.id_order, '|'), ''))) / LENGTH(CONCAT('|', o.id_order, '|'))) / (CASE WHEN TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')) = '' THEN 1 ELSE (LENGTH(TRIM(BOTH '|' FROM wod2.customer_id_orders)) - LENGTH(REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', '')) + 1) END))), 0)
            FROM ps_wkdelivery_order_detail wod2
            INNER JOIN ps_wkdelivery_orders wo2
                ON wo2.id_wkdelivery_orders = wod2.id_delivery
            WHERE FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', ','))
        ),
        2
    ) AS depannage_ht_sans_coef,
    ROUND(
        (
            SELECT IFNULL(SUM((wod2.unit_price_te * wod2.quantity) * (((LENGTH(IFNULL(wod2.customer_id_orders, '')) - LENGTH(REPLACE(IFNULL(wod2.customer_id_orders, ''), CONCAT('|', o.id_order, '|'), ''))) / LENGTH(CONCAT('|', o.id_order, '|'))) / (CASE WHEN TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')) = '' THEN 1 ELSE (LENGTH(TRIM(BOTH '|' FROM wod2.customer_id_orders)) - LENGTH(REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', '')) + 1) END))), 0)
            FROM ps_wkdelivery_order_detail wod2
            INNER JOIN ps_wkdelivery_orders wo2
                ON wo2.id_wkdelivery_orders = wod2.id_delivery
            WHERE FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', ','))
        ) * 1.06,
        2
    ) AS depannage_ht_avec_coef,
    (
        SELECT IFNULL(GROUP_CONCAT(DISTINCT CONCAT(wo3.reference, ' [', wod3.quantity, 'x ', COALESCE(NULLIF(wod3.supplier_reference, ''), CONCAT('#', wod3.id_product, IF(wod3.id_product_attribute > 0, CONCAT('-', wod3.id_product_attribute), ''))), ']') ORDER BY wo3.reference SEPARATOR ' | '), '')
        FROM ps_wkdelivery_order_detail wod3
        INNER JOIN ps_wkdelivery_orders wo3
            ON wo3.id_wkdelivery_orders = wod3.id_delivery
        WHERE FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod3.customer_id_orders), '|', ','))
    ) AS supplier_order_refs,
    ROUND(
        (
            SELECT IFNULL(SUM(od.total_price_tax_excl), 0)
            FROM ps_order_detail od
            WHERE od.id_order = o.id_order
        )
        - (
            (
                SELECT IFNULL(SUM((wod2.unit_price_te * wod2.quantity) * (((LENGTH(IFNULL(wod2.customer_id_orders, '')) - LENGTH(REPLACE(IFNULL(wod2.customer_id_orders, ''), CONCAT('|', o.id_order, '|'), ''))) / LENGTH(CONCAT('|', o.id_order, '|'))) / (CASE WHEN TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')) = '' THEN 1 ELSE (LENGTH(TRIM(BOTH '|' FROM wod2.customer_id_orders)) - LENGTH(REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', '')) + 1) END))), 0)
                FROM ps_wkdelivery_order_detail wod2
                INNER JOIN ps_wkdelivery_orders wo2
                    ON wo2.id_wkdelivery_orders = wod2.id_delivery
                WHERE FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', ','))
            ) * 1.06
        ),
        2
    ) AS mb_ht,
    ROUND(
        (
            SELECT IFNULL(SUM(od.total_price_tax_excl), 0)
            FROM ps_order_detail od
            WHERE od.id_order = o.id_order
        )
        - (
            SELECT IFNULL(SUM((wod2.unit_price_te * wod2.quantity) * (((LENGTH(IFNULL(wod2.customer_id_orders, '')) - LENGTH(REPLACE(IFNULL(wod2.customer_id_orders, ''), CONCAT('|', o.id_order, '|'), ''))) / LENGTH(CONCAT('|', o.id_order, '|'))) / (CASE WHEN TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')) = '' THEN 1 ELSE (LENGTH(TRIM(BOTH '|' FROM wod2.customer_id_orders)) - LENGTH(REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', '')) + 1) END))), 0)
            FROM ps_wkdelivery_order_detail wod2
            INNER JOIN ps_wkdelivery_orders wo2
                ON wo2.id_wkdelivery_orders = wod2.id_delivery
            WHERE FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', ','))
        ),
        2
    ) AS marge_nette,
    ROUND(
        CASE
            WHEN (
                SELECT IFNULL(SUM(od.total_price_tax_excl), 0)
                FROM ps_order_detail od
                WHERE od.id_order = o.id_order
            ) = 0 THEN 0
            ELSE (
                (
                    SELECT IFNULL(SUM(od.total_price_tax_excl), 0)
                    FROM ps_order_detail od
                    WHERE od.id_order = o.id_order
                )
                - (
                    (
                        SELECT IFNULL(SUM((wod2.unit_price_te * wod2.quantity) * (((LENGTH(IFNULL(wod2.customer_id_orders, '')) - LENGTH(REPLACE(IFNULL(wod2.customer_id_orders, ''), CONCAT('|', o.id_order, '|'), ''))) / LENGTH(CONCAT('|', o.id_order, '|'))) / (CASE WHEN TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')) = '' THEN 1 ELSE (LENGTH(TRIM(BOTH '|' FROM wod2.customer_id_orders)) - LENGTH(REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', '')) + 1) END))), 0)
                        FROM ps_wkdelivery_order_detail wod2
                        INNER JOIN ps_wkdelivery_orders wo2
                            ON wo2.id_wkdelivery_orders = wod2.id_delivery
                        WHERE FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', ','))
                    ) * 1.06
                )
            ) * 100 /
            (
                SELECT IFNULL(SUM(od.total_price_tax_excl), 0)
                FROM ps_order_detail od
                WHERE od.id_order = o.id_order
            )
        END,
        2
    ) AS pct_mb_ht,
    ROUND(
        CASE
            WHEN (
                SELECT IFNULL(SUM(od.total_price_tax_excl), 0)
                FROM ps_order_detail od
                WHERE od.id_order = o.id_order
            ) = 0 THEN 0
            ELSE (
                (
                    SELECT IFNULL(SUM(od.total_price_tax_excl), 0)
                    FROM ps_order_detail od
                    WHERE od.id_order = o.id_order
                )
                - (
                    SELECT IFNULL(SUM((wod2.unit_price_te * wod2.quantity) * (((LENGTH(IFNULL(wod2.customer_id_orders, '')) - LENGTH(REPLACE(IFNULL(wod2.customer_id_orders, ''), CONCAT('|', o.id_order, '|'), ''))) / LENGTH(CONCAT('|', o.id_order, '|'))) / (CASE WHEN TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')) = '' THEN 1 ELSE (LENGTH(TRIM(BOTH '|' FROM wod2.customer_id_orders)) - LENGTH(REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', '')) + 1) END))), 0)
                    FROM ps_wkdelivery_order_detail wod2
                    INNER JOIN ps_wkdelivery_orders wo2
                        ON wo2.id_wkdelivery_orders = wod2.id_delivery
                    WHERE FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', ','))
                )
            ) * 100 /
            (
                SELECT IFNULL(SUM(od.total_price_tax_excl), 0)
                FROM ps_order_detail od
                WHERE od.id_order = o.id_order
            )
        END,
        2
    ) AS pct_marge_nette
FROM ps_orders o
WHERE EXISTS (
        SELECT 1
        FROM ps_order_invoice oi1
        WHERE oi1.id_order = o.id_order
          AND oi1.date_add >= '2026-02-01 00:00:00'
                    AND oi1.date_add <= '2026-02-28 23:59:59'
    )
    AND o.current_state IN (4, 5, 18)
ORDER BY DATE(
        (
            SELECT MIN(oi1.date_add)
            FROM ps_order_invoice oi1
            WHERE oi1.id_order = o.id_order
              AND oi1.date_add >= '2026-02-01 00:00:00'
              AND oi1.date_add <= '2026-02-28 23:59:59'
        )
    ) ASC;