SELECT
    ROUND(IFNULL(SUM(x.depannage_ital_ht), 0), 2) AS total_article_ht_depannage_ital_express,
    ROUND(IFNULL(SUM(x.ca_ht), 0), 2) AS total_ca_ht_lignes_ital,
    ROUND(IFNULL(SUM(x.ca_ht), 0) - (IFNULL(SUM(x.depannage_ital_ht), 0) * 1.06), 2) AS marge_brute_ht,
    ROUND(IFNULL(SUM(x.ca_ht), 0) - IFNULL(SUM(x.depannage_ital_ht), 0), 2) AS marge_nette_avec_coef,
    ROUND(
        CASE
            WHEN IFNULL(SUM(x.ca_ht), 0) = 0 THEN 0
            ELSE (IFNULL(SUM(x.ca_ht), 0) - (IFNULL(SUM(x.depannage_ital_ht), 0) * 1.06)) * 100 / IFNULL(SUM(x.ca_ht), 0)
        END,
        2
    ) AS pct_marge_brute_ht,
    ROUND(
        CASE
            WHEN IFNULL(SUM(x.ca_ht), 0) = 0 THEN 0
            ELSE (IFNULL(SUM(x.ca_ht), 0) - IFNULL(SUM(x.depannage_ital_ht), 0)) * 100 / IFNULL(SUM(x.ca_ht), 0)
        END,
        2
    ) AS pct_marge_nette__avec_coef
FROM (
    SELECT
        o.id_order,
        (
            SELECT IFNULL(
                SUM(
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM ps_wkdelivery_order_detail wodm
                            INNER JOIN ps_wkdelivery_orders wom
                                ON wom.id_wkdelivery_orders = wodm.id_delivery
                            LEFT JOIN ps_supplier supm
                                ON supm.id_supplier = wom.id_supplier
                            WHERE wodm.id_product = od.product_id
                              AND wodm.id_product_attribute = od.product_attribute_id
                              AND FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wodm.customer_id_orders), '|', ','))
                              AND LOWER(IFNULL(supm.name, '')) = 'ital express'
                        ) THEN od.total_price_tax_excl
                        ELSE 0
                    END
                ),
                0
            )
            FROM ps_order_detail od
            WHERE od.id_order = o.id_order
        ) AS ca_ht,
        (
            SELECT IFNULL(
                SUM(
                    (wod2.unit_price_te * wod2.quantity)
                    * (
                        (
                            (LENGTH(IFNULL(wod2.customer_id_orders, ''))
                            - LENGTH(REPLACE(IFNULL(wod2.customer_id_orders, ''), CONCAT('|', o.id_order, '|'), '')))
                            / LENGTH(CONCAT('|', o.id_order, '|'))
                        )
                        /
                        (
                            CASE
                                WHEN TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')) = '' THEN 1
                                ELSE (
                                    LENGTH(TRIM(BOTH '|' FROM wod2.customer_id_orders))
                                    - LENGTH(REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', ''))
                                    + 1
                                )
                            END
                        )
                    )
                ),
                0
            )
            FROM ps_wkdelivery_order_detail wod2
            INNER JOIN ps_wkdelivery_orders wo2
                ON wo2.id_wkdelivery_orders = wod2.id_delivery
            LEFT JOIN ps_supplier sup2
                ON sup2.id_supplier = wo2.id_supplier
            WHERE FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', ','))
              AND LOWER(IFNULL(sup2.name, '')) = 'ital express'
        ) AS depannage_ital_ht
    FROM ps_orders o
    WHERE EXISTS (
            SELECT 1
            FROM ps_order_invoice oi1
            WHERE oi1.id_order = o.id_order
              AND oi1.date_add >= '2026-02-01 00:00:00'
              AND oi1.date_add <= '2026-02-28 23:59:59'
        )
      AND o.current_state IN (4, 5, 18)
      AND EXISTS (
            SELECT 1
            FROM ps_wkdelivery_order_detail wodx
            INNER JOIN ps_wkdelivery_orders wox
                ON wox.id_wkdelivery_orders = wodx.id_delivery
            LEFT JOIN ps_supplier supx
                ON supx.id_supplier = wox.id_supplier
            WHERE FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wodx.customer_id_orders), '|', ','))
              AND LOWER(IFNULL(supx.name, '')) = 'ital express'
        )
) x;