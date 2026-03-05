SELECT
    o.id_order,
    o.reference AS commande_client,
    (
        SELECT GROUP_CONCAT(DISTINCT wo_ref.reference ORDER BY wo_ref.reference SEPARATOR ', ')
        FROM ps_wkdelivery_order_detail wod_ref
        INNER JOIN ps_wkdelivery_orders wo_ref
            ON wo_ref.id_wkdelivery_orders = wod_ref.id_delivery
        LEFT JOIN ps_supplier sup_ref
            ON sup_ref.id_supplier = wo_ref.id_supplier
        WHERE FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod_ref.customer_id_orders), '|', ','))
          AND LOWER(IFNULL(sup_ref.name, '')) = 'ital express'
    ) AS commandes_fournisseur_ital,
    ROUND((
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
    ), 2) AS ca_ht_depannage_ital_express,
    (
        SELECT GROUP_CONCAT(
            CONCAT(od.product_reference, ' x', od.product_quantity)
            ORDER BY od.id_order_detail
            SEPARATOR ' | '
        )
        FROM ps_order_detail od
        WHERE od.id_order = o.id_order
          AND EXISTS (
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
          )
    ) AS detail_lignes_ital_express,
    ROUND((
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
    ), 2) AS pa_ht_depannage_ital_express
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
ORDER BY o.id_order;
