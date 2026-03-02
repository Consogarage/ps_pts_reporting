SELECT
    c.id_customer AS code_client,
    c.company AS raison_sociale,
    IFNULL((
        SELECT COALESCE(
            cfvl.value,
            rv.field_value
        )
        FROM ps_presta_btwob_registration_value rv
        LEFT JOIN ps_presta_btwob_custom_fields_value_lang cfvl
            ON cfvl.id_multi_value = CAST(
                REPLACE(REPLACE(REPLACE(rv.field_value, '[', ''), ']', ''), '"', '') AS UNSIGNED
            )
           AND cfvl.id_lang = 1
        WHERE rv.id_customer = c.id_customer
          AND rv.field_id = 1
        ORDER BY rv.id_presta_btwob_registration_value DESC
        LIMIT 1
    ), '') AS activite,
    IFNULL((
        SELECT a.postcode
        FROM ps_address a
        WHERE a.id_customer = c.id_customer
          AND a.deleted = 0
        ORDER BY a.id_address ASC
        LIMIT 1
    ), '') AS departement,
    IFNULL((
        SELECT cl.name
        FROM ps_address a
        LEFT JOIN ps_country_lang cl
            ON cl.id_country = a.id_country
           AND cl.id_lang = 1
        WHERE a.id_customer = c.id_customer
          AND a.deleted = 0
        ORDER BY a.id_address ASC
        LIMIT 1
    ), '') AS pays,
    ROUND(IFNULL((
        SELECT SUM(od.total_price_tax_excl)
        FROM ps_orders o
        INNER JOIN ps_order_detail od ON od.id_order = o.id_order
        WHERE o.id_customer = c.id_customer
          AND o.current_state IN (4, 5, 18)
          AND EXISTS (
              SELECT 1
              FROM ps_order_invoice oi
              WHERE oi.id_order = o.id_order
                AND oi.date_add >= '2026-02-01 00:00:00'
                AND oi.date_add <= '2026-02-28 23:59:59'
          )
    ), 0) - IFNULL((
        SELECT SUM(od.total_price_tax_excl)
        FROM ps_orders o
        INNER JOIN ps_order_detail od ON od.id_order = o.id_order
        WHERE o.id_customer = c.id_customer
          AND o.current_state IN (4, 5, 18)
          AND EXISTS (
              SELECT 1
              FROM ps_order_invoice oi
              WHERE oi.id_order = o.id_order
                AND oi.date_add >= '2025-02-01 00:00:00'
                AND oi.date_add <= '2025-02-28 23:59:59'
          )
    ), 0), 2) AS ecart_ca_vs_ca_n_1,
    ROUND(IFNULL((
        SELECT SUM(od.total_price_tax_excl)
        FROM ps_orders o
        INNER JOIN ps_order_detail od ON od.id_order = o.id_order
        WHERE o.id_customer = c.id_customer
          AND o.current_state IN (4, 5, 18)
          AND EXISTS (
              SELECT 1
              FROM ps_order_invoice oi
              WHERE oi.id_order = o.id_order
                AND oi.date_add >= '2026-02-01 00:00:00'
                AND oi.date_add <= '2026-02-28 23:59:59'
          )
    ), 0), 2) AS ca_ht,
    ROUND(IFNULL((
        SELECT SUM(od.total_price_tax_excl)
        FROM ps_orders o
        INNER JOIN ps_order_detail od ON od.id_order = o.id_order
        WHERE o.id_customer = c.id_customer
          AND o.current_state IN (4, 5, 18)
          AND EXISTS (
              SELECT 1
              FROM ps_order_invoice oi
              WHERE oi.id_order = o.id_order
                AND oi.date_add >= '2025-02-01 00:00:00'
                AND oi.date_add <= '2025-02-28 23:59:59'
          )
    ), 0), 2) AS ca_ht_n_1,
    ROUND(
        CASE
            WHEN IFNULL((
                SELECT SUM(od.total_price_tax_excl)
                FROM ps_orders o
                INNER JOIN ps_order_detail od ON od.id_order = o.id_order
                WHERE o.id_customer = c.id_customer
                  AND o.current_state IN (4, 5, 18)
                  AND EXISTS (
                      SELECT 1
                      FROM ps_order_invoice oi
                      WHERE oi.id_order = o.id_order
                        AND oi.date_add >= '2025-02-01 00:00:00'
                        AND oi.date_add <= '2025-02-28 23:59:59'
                  )
            ), 0) = 0 THEN 0
            ELSE (
                IFNULL((
                    SELECT SUM(od.total_price_tax_excl)
                    FROM ps_orders o
                    INNER JOIN ps_order_detail od ON od.id_order = o.id_order
                    WHERE o.id_customer = c.id_customer
                      AND o.current_state IN (4, 5, 18)
                      AND EXISTS (
                          SELECT 1
                          FROM ps_order_invoice oi
                          WHERE oi.id_order = o.id_order
                            AND oi.date_add >= '2026-02-01 00:00:00'
                            AND oi.date_add <= '2026-02-28 23:59:59'
                      )
                ), 0)
                -
                IFNULL((
                    SELECT SUM(od.total_price_tax_excl)
                    FROM ps_orders o
                    INNER JOIN ps_order_detail od ON od.id_order = o.id_order
                    WHERE o.id_customer = c.id_customer
                      AND o.current_state NOT IN (6, 7)
                      AND EXISTS (
                          SELECT 1
                          FROM ps_order_invoice oi
                          WHERE oi.id_order = o.id_order
                            AND oi.date_add >= '2025-02-01 00:00:00'
                            AND oi.date_add <= '2025-02-28 23:59:59'
                      )
                ), 0)
            ) * 100 /
            IFNULL((
                SELECT SUM(od.total_price_tax_excl)
                FROM ps_orders o
                INNER JOIN ps_order_detail od ON od.id_order = o.id_order
                WHERE o.id_customer = c.id_customer
                  AND o.current_state NOT IN (6, 7)
                  AND EXISTS (
                      SELECT 1
                      FROM ps_order_invoice oi
                      WHERE oi.id_order = o.id_order
                        AND oi.date_add >= '2025-02-01 00:00:00'
                        AND oi.date_add <= '2025-02-28 23:59:59'
                  )
            ), 0)
        END,
        2
    ) AS pct_evolution_ca_vs_n_1,
    ROUND(
        CASE
            WHEN IFNULL((
                SELECT SUM(od.total_price_tax_excl)
                FROM ps_orders o
                INNER JOIN ps_order_detail od ON od.id_order = o.id_order
                WHERE o.id_customer = c.id_customer
                  AND o.current_state NOT IN (6, 7)
                  AND EXISTS (
                      SELECT 1
                      FROM ps_order_invoice oi
                      WHERE oi.id_order = o.id_order
                        AND oi.date_add >= '2026-02-01 00:00:00'
                        AND oi.date_add <= '2026-02-28 23:59:59'
                  )
            ), 0) = 0 THEN 0
            ELSE (
                IFNULL((
                    SELECT SUM(
                        IFNULL((SELECT SUM(od1.total_price_tax_excl) FROM ps_order_detail od1 WHERE od1.id_order = o.id_order), 0)
                        -
                        IFNULL((
                            SELECT SUM((wod2.unit_price_te * wod2.quantity) * ((((LENGTH(CONCAT(',', REPLACE(TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')), '|', ','), ',')) - LENGTH(REPLACE(CONCAT(',', REPLACE(TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')), '|', ','), ','), CONCAT(',', o.id_order, ','), ''))) / LENGTH(CONCAT(',', o.id_order, ','))) / (CASE WHEN TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')) = '' THEN 1 ELSE (LENGTH(TRIM(BOTH '|' FROM wod2.customer_id_orders)) - LENGTH(REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', '')) + 1) END)))
                            FROM ps_wkdelivery_order_detail wod2
                            INNER JOIN ps_wkdelivery_orders wo2 ON wo2.id_wkdelivery_orders = wod2.id_delivery
                            WHERE FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', ','))
                        ), 0)
                    )
                    FROM ps_orders o
                    WHERE o.id_customer = c.id_customer
                      AND o.current_state NOT IN (6, 7)
                      AND EXISTS (
                          SELECT 1
                          FROM ps_order_invoice oi
                          WHERE oi.id_order = o.id_order
                            AND oi.date_add >= '2026-02-01 00:00:00'
                            AND oi.date_add <= '2026-02-28 23:59:59'
                      )
                ), 0) * 100 /
                IFNULL((
                    SELECT SUM(od.total_price_tax_excl)
                    FROM ps_orders o
                    INNER JOIN ps_order_detail od ON od.id_order = o.id_order
                    WHERE o.id_customer = c.id_customer
                      AND o.current_state NOT IN (6, 7)
                      AND EXISTS (
                          SELECT 1
                          FROM ps_order_invoice oi
                          WHERE oi.id_order = o.id_order
                            AND oi.date_add >= '2026-02-01 00:00:00'
                            AND oi.date_add <= '2026-02-28 23:59:59'
                      )
                ), 0)
            )
        END,
        2
    ) AS pct_marge_brute,
    ROUND(IFNULL((
        SELECT SUM(
            IFNULL((SELECT SUM(od1.total_price_tax_excl) FROM ps_order_detail od1 WHERE od1.id_order = o.id_order), 0)
            -
            IFNULL((
                SELECT SUM((wod2.unit_price_te * wod2.quantity) * ((((LENGTH(CONCAT(',', REPLACE(TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')), '|', ','), ',')) - LENGTH(REPLACE(CONCAT(',', REPLACE(TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')), '|', ','), ','), CONCAT(',', o.id_order, ','), ''))) / LENGTH(CONCAT(',', o.id_order, ','))) / (CASE WHEN TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')) = '' THEN 1 ELSE (LENGTH(TRIM(BOTH '|' FROM wod2.customer_id_orders)) - LENGTH(REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', '')) + 1) END)))
                FROM ps_wkdelivery_order_detail wod2
                INNER JOIN ps_wkdelivery_orders wo2 ON wo2.id_wkdelivery_orders = wod2.id_delivery
                WHERE FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', ','))
            ), 0)
        )
        FROM ps_orders o
        WHERE o.id_customer = c.id_customer
          AND o.current_state IN (4, 5, 18)
          AND EXISTS (
              SELECT 1
              FROM ps_order_invoice oi
              WHERE oi.id_order = o.id_order
                AND oi.date_add >= '2026-02-01 00:00:00'
                AND oi.date_add <= '2026-02-28 23:59:59'
          )
    ), 0), 2) AS marge_brute_ht,
    ROUND(IFNULL((
        SELECT SUM(
            IFNULL((SELECT SUM(od1.total_price_tax_excl) FROM ps_order_detail od1 WHERE od1.id_order = o.id_order), 0)
            -
            IFNULL((
                SELECT SUM((wod2.unit_price_te * wod2.quantity) * ((((LENGTH(CONCAT(',', REPLACE(TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')), '|', ','), ',')) - LENGTH(REPLACE(CONCAT(',', REPLACE(TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')), '|', ','), ','), CONCAT(',', o.id_order, ','), ''))) / LENGTH(CONCAT(',', o.id_order, ','))) / (CASE WHEN TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')) = '' THEN 1 ELSE (LENGTH(TRIM(BOTH '|' FROM wod2.customer_id_orders)) - LENGTH(REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', '')) + 1) END)))
                FROM ps_wkdelivery_order_detail wod2
                INNER JOIN ps_wkdelivery_orders wo2 ON wo2.id_wkdelivery_orders = wod2.id_delivery
                WHERE FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', ','))
            ), 0)
        )
        FROM ps_orders o
        WHERE o.id_customer = c.id_customer
                  AND o.current_state IN (4, 5, 18)
          AND EXISTS (
              SELECT 1
              FROM ps_order_invoice oi
              WHERE oi.id_order = o.id_order
                  AND oi.date_add >= '2025-02-01 00:00:00'
                  AND oi.date_add <= '2025-02-28 23:59:59'
          )
    ), 0), 2) AS marge_brute_ht_n_1,
    ROUND(
        IFNULL((
            SELECT SUM(
                IFNULL((SELECT SUM(od1.total_price_tax_excl) FROM ps_order_detail od1 WHERE od1.id_order = o.id_order), 0)
                -
                IFNULL((
                    SELECT SUM((wod2.unit_price_te * wod2.quantity) * ((((LENGTH(CONCAT(',', REPLACE(TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')), '|', ','), ',')) - LENGTH(REPLACE(CONCAT(',', REPLACE(TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')), '|', ','), ','), CONCAT(',', o.id_order, ','), ''))) / LENGTH(CONCAT(',', o.id_order, ','))) / (CASE WHEN TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')) = '' THEN 1 ELSE (LENGTH(TRIM(BOTH '|' FROM wod2.customer_id_orders)) - LENGTH(REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', '')) + 1) END)))
                    FROM ps_wkdelivery_order_detail wod2
                    INNER JOIN ps_wkdelivery_orders wo2 ON wo2.id_wkdelivery_orders = wod2.id_delivery
                    WHERE FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', ','))
                ), 0)
            )
            FROM ps_orders o
            WHERE o.id_customer = c.id_customer
              AND o.current_state IN (4, 5, 18)
              AND EXISTS (
                  SELECT 1
                  FROM ps_order_invoice oi
                  WHERE oi.id_order = o.id_order
                    AND oi.date_add >= '2026-02-01 00:00:00'
                    AND oi.date_add <= '2026-02-28 23:59:59'
              )
        ), 0)
        -
        IFNULL((
            SELECT SUM(
                IFNULL((SELECT SUM(od1.total_price_tax_excl) FROM ps_order_detail od1 WHERE od1.id_order = o.id_order), 0)
                -
                IFNULL((
                    SELECT SUM((wod2.unit_price_te * wod2.quantity) * ((((LENGTH(CONCAT(',', REPLACE(TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')), '|', ','), ',')) - LENGTH(REPLACE(CONCAT(',', REPLACE(TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')), '|', ','), ','), CONCAT(',', o.id_order, ','), ''))) / LENGTH(CONCAT(',', o.id_order, ','))) / (CASE WHEN TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')) = '' THEN 1 ELSE (LENGTH(TRIM(BOTH '|' FROM wod2.customer_id_orders)) - LENGTH(REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', '')) + 1) END)))
                    FROM ps_wkdelivery_order_detail wod2
                    INNER JOIN ps_wkdelivery_orders wo2 ON wo2.id_wkdelivery_orders = wod2.id_delivery
                    WHERE FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', ','))
                ), 0)
            )
            FROM ps_orders o
            WHERE o.id_customer = c.id_customer
              AND o.current_state IN (4, 5, 18)
              AND EXISTS (
                  SELECT 1
                  FROM ps_order_invoice oi
                  WHERE oi.id_order = o.id_order
                    AND oi.date_add >= '2025-02-01 00:00:00'
                    AND oi.date_add <= '2025-02-28 23:59:59'
              )
        ), 0),
        2
    ) AS ecart_marge_brute_vs_n_1,
    ROUND(
        CASE
            WHEN IFNULL((
                SELECT SUM(
                    IFNULL((SELECT SUM(od1.total_price_tax_excl) FROM ps_order_detail od1 WHERE od1.id_order = o.id_order), 0)
                    -
                    IFNULL((
                        SELECT SUM((wod2.unit_price_te * wod2.quantity) * ((((LENGTH(CONCAT(',', REPLACE(TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')), '|', ','), ',')) - LENGTH(REPLACE(CONCAT(',', REPLACE(TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')), '|', ','), ','), CONCAT(',', o.id_order, ','), ''))) / LENGTH(CONCAT(',', o.id_order, ','))) / (CASE WHEN TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')) = '' THEN 1 ELSE (LENGTH(TRIM(BOTH '|' FROM wod2.customer_id_orders)) - LENGTH(REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', '')) + 1) END)))
                        FROM ps_wkdelivery_order_detail wod2
                        INNER JOIN ps_wkdelivery_orders wo2 ON wo2.id_wkdelivery_orders = wod2.id_delivery
                        WHERE FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', ','))
                    ), 0)
                )
                FROM ps_orders o
                WHERE o.id_customer = c.id_customer
                  AND o.current_state IN (4, 5, 18)
                  AND EXISTS (
                      SELECT 1
                      FROM ps_order_invoice oi
                      WHERE oi.id_order = o.id_order
                        AND oi.date_add >= '2025-02-01 00:00:00'
                        AND oi.date_add <= '2025-02-28 23:59:59'
                  )
            ), 0) = 0 THEN 0
            ELSE (
                (
                    IFNULL((
                        SELECT SUM(
                            IFNULL((SELECT SUM(od1.total_price_tax_excl) FROM ps_order_detail od1 WHERE od1.id_order = o.id_order), 0)
                            -
                            IFNULL((
                                SELECT SUM((wod2.unit_price_te * wod2.quantity) * ((((LENGTH(CONCAT(',', REPLACE(TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')), '|', ','), ',')) - LENGTH(REPLACE(CONCAT(',', REPLACE(TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')), '|', ','), ','), CONCAT(',', o.id_order, ','), ''))) / LENGTH(CONCAT(',', o.id_order, ','))) / (CASE WHEN TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')) = '' THEN 1 ELSE (LENGTH(TRIM(BOTH '|' FROM wod2.customer_id_orders)) - LENGTH(REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', '')) + 1) END)))
                                FROM ps_wkdelivery_order_detail wod2
                                INNER JOIN ps_wkdelivery_orders wo2 ON wo2.id_wkdelivery_orders = wod2.id_delivery
                                WHERE FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', ','))
                            ), 0)
                        )
                        FROM ps_orders o
                        WHERE o.id_customer = c.id_customer
                          AND o.current_state IN (4, 5, 18)
                          AND EXISTS (
                              SELECT 1
                              FROM ps_order_invoice oi
                              WHERE oi.id_order = o.id_order
                                AND oi.date_add >= '2026-02-01 00:00:00'
                                AND oi.date_add <= '2026-02-28 23:59:59'
                          )
                    ), 0)
                    -
                    IFNULL((
                        SELECT SUM(
                            IFNULL((SELECT SUM(od1.total_price_tax_excl) FROM ps_order_detail od1 WHERE od1.id_order = o.id_order), 0)
                            -
                            IFNULL((
                                SELECT SUM((wod2.unit_price_te * wod2.quantity) * ((((LENGTH(CONCAT(',', REPLACE(TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')), '|', ','), ',')) - LENGTH(REPLACE(CONCAT(',', REPLACE(TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')), '|', ','), ','), CONCAT(',', o.id_order, ','), ''))) / LENGTH(CONCAT(',', o.id_order, ','))) / (CASE WHEN TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')) = '' THEN 1 ELSE (LENGTH(TRIM(BOTH '|' FROM wod2.customer_id_orders)) - LENGTH(REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', '')) + 1) END)))
                                FROM ps_wkdelivery_order_detail wod2
                                INNER JOIN ps_wkdelivery_orders wo2 ON wo2.id_wkdelivery_orders = wod2.id_delivery
                                WHERE FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', ','))
                            ), 0)
                        )
                        FROM ps_orders o
                        WHERE o.id_customer = c.id_customer
                          AND o.current_state IN (4, 5, 18)
                          AND EXISTS (
                              SELECT 1
                              FROM ps_order_invoice oi
                              WHERE oi.id_order = o.id_order
                                AND oi.date_add >= '2025-02-01 00:00:00'
                                AND oi.date_add <= '2025-02-28 23:59:59'
                          )
                    ), 0)
                ) * 100 /
                IFNULL((
                    SELECT SUM(
                        IFNULL((SELECT SUM(od1.total_price_tax_excl) FROM ps_order_detail od1 WHERE od1.id_order = o.id_order), 0)
                        -
                        IFNULL((
                            SELECT SUM((wod2.unit_price_te * wod2.quantity) * ((((LENGTH(CONCAT(',', REPLACE(TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')), '|', ','), ',')) - LENGTH(REPLACE(CONCAT(',', REPLACE(TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')), '|', ','), ','), CONCAT(',', o.id_order, ','), ''))) / LENGTH(CONCAT(',', o.id_order, ','))) / (CASE WHEN TRIM(BOTH '|' FROM IFNULL(wod2.customer_id_orders, '')) = '' THEN 1 ELSE (LENGTH(TRIM(BOTH '|' FROM wod2.customer_id_orders)) - LENGTH(REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', '')) + 1) END)))
                            FROM ps_wkdelivery_order_detail wod2
                            INNER JOIN ps_wkdelivery_orders wo2 ON wo2.id_wkdelivery_orders = wod2.id_delivery
                            WHERE FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wod2.customer_id_orders), '|', ','))
                        ), 0)
                    )
                    FROM ps_orders o
                    WHERE o.id_customer = c.id_customer
                      AND o.current_state IN (4, 5, 18)
                      AND EXISTS (
                          SELECT 1
                          FROM ps_order_invoice oi
                          WHERE oi.id_order = o.id_order
                            AND oi.date_add >= '2025-02-01 00:00:00'
                            AND oi.date_add <= '2025-02-28 23:59:59'
                      )
                ), 0)
            )
        END,
        2
    ) AS pct_evolution_marge_brute_vs_n_1,
        IFNULL((
                SELECT COUNT(DISTINCT d.id_opartdevis)
                FROM ps_opartdevis d
                WHERE d.id_customer = c.id_customer
                    AND d.date_add >= '2026-02-01 00:00:00'
                    AND d.date_add <= '2026-02-28 23:59:59'
        ), 0) AS nbre_devis,
    IFNULL((
        SELECT COUNT(DISTINCT o.id_order)
        FROM ps_orders o
        WHERE o.id_customer = c.id_customer
                          AND o.current_state IN (4, 5, 18)
          AND EXISTS (
              SELECT 1
              FROM ps_order_invoice oi
              WHERE oi.id_order = o.id_order
                AND oi.date_add >= '2026-02-01 00:00:00'
                AND oi.date_add <= '2026-02-28 23:59:59'
          )
    ), 0) AS nbre_commandes,
    IFNULL((
        SELECT COUNT(DISTINCT d.id_opartdevis)
        FROM ps_opartdevis d
        WHERE d.id_customer = c.id_customer
          AND d.date_add >= '2026-02-01 00:00:00'
          AND d.date_add <= '2026-02-28 23:59:59'
          AND d.status = 2
    ), 0) AS transformation_devis_en_commande,
    ROUND(
        CASE
            WHEN IFNULL((
                SELECT COUNT(DISTINCT d.id_opartdevis)
                FROM ps_opartdevis d
                WHERE d.id_customer = c.id_customer
                  AND d.date_add >= '2026-02-01 00:00:00'
                  AND d.date_add <= '2026-02-28 23:59:59'
            ), 0) = 0 THEN 0
            ELSE (
                IFNULL((
                    SELECT COUNT(DISTINCT d.id_opartdevis)
                    FROM ps_opartdevis d
                    WHERE d.id_customer = c.id_customer
                      AND d.date_add >= '2026-02-01 00:00:00'
                      AND d.date_add <= '2026-02-28 23:59:59'
                      AND d.status = 2
                ), 0) * 100 /
                IFNULL((
                    SELECT COUNT(DISTINCT d.id_opartdevis)
                    FROM ps_opartdevis d
                    WHERE d.id_customer = c.id_customer
                      AND d.date_add >= '2026-02-01 00:00:00'
                      AND d.date_add <= '2026-02-28 23:59:59'
                ), 0)
            )
        END,
        2
    ) AS taux_transformation_devis_en_commande,
    ROUND(
        CASE
            WHEN IFNULL((
                SELECT COUNT(DISTINCT o.id_order)
                FROM ps_orders o
                WHERE o.id_customer = c.id_customer
                  AND o.current_state IN (4, 5, 18)
                  AND EXISTS (
                      SELECT 1
                      FROM ps_order_invoice oi
                      WHERE oi.id_order = o.id_order
                        AND oi.date_add >= '2026-02-01 00:00:00'
                        AND oi.date_add <= '2026-02-28 23:59:59'
                  )
            ), 0) = 0 THEN 0
            ELSE IFNULL((
                SELECT SUM(od.total_price_tax_excl)
                FROM ps_orders o
                INNER JOIN ps_order_detail od ON od.id_order = o.id_order
                WHERE o.id_customer = c.id_customer
                      AND o.current_state IN (4, 5, 18)
                  AND EXISTS (
                      SELECT 1
                      FROM ps_order_invoice oi
                      WHERE oi.id_order = o.id_order
                        AND oi.date_add >= '2026-02-01 00:00:00'
                        AND oi.date_add <= '2026-02-28 23:59:59'
                  )
            ), 0) /
            IFNULL((
                SELECT COUNT(DISTINCT o.id_order)
                FROM ps_orders o
                WHERE o.id_customer = c.id_customer
                          AND o.current_state IN (4, 5, 18)
                  AND EXISTS (
                      SELECT 1
                      FROM ps_order_invoice oi
                      WHERE oi.id_order = o.id_order
                        AND oi.date_add >= '2026-02-01 00:00:00'
                        AND oi.date_add <= '2026-02-28 23:59:59'
                  )
            ), 0)
        END,
        2
    ) AS panier_moyen,
    IFNULL((
        SELECT COUNT(DISTINCT os.id_order_slip)
        FROM ps_order_slip os
        WHERE os.id_customer = c.id_customer
          AND os.date_add >= '2026-02-01 00:00:00'
          AND os.date_add <= '2026-02-28 23:59:59'
    ), 0) AS avoirs,
    CASE
        WHEN (
            SELECT MIN(oi.date_add)
            FROM ps_orders o
            INNER JOIN ps_order_invoice oi ON oi.id_order = o.id_order
            WHERE o.id_customer = c.id_customer
        ) >= '2026-02-01 00:00:00'
         AND (
            SELECT MIN(oi.date_add)
            FROM ps_orders o
            INNER JOIN ps_order_invoice oi ON oi.id_order = o.id_order
            WHERE o.id_customer = c.id_customer
        ) <= '2026-02-28 23:59:59' THEN 1
        ELSE 0
    END AS nouveau_client
FROM ps_customer c
WHERE
    IFNULL((
        SELECT COUNT(DISTINCT o.id_order)
        FROM ps_orders o
        WHERE o.id_customer = c.id_customer
                          AND o.current_state IN (4, 5, 18)
          AND EXISTS (
              SELECT 1
              FROM ps_order_invoice oi
              WHERE oi.id_order = o.id_order
                AND oi.date_add >= '2026-02-01 00:00:00'
                AND oi.date_add <= '2026-02-28 23:59:59'
          )
    ), 0) > 0
    OR
    IFNULL((
        SELECT COUNT(DISTINCT o.id_order)
        FROM ps_orders o
        WHERE o.id_customer = c.id_customer
          AND o.current_state NOT IN (6, 7)
          AND EXISTS (
              SELECT 1
              FROM ps_order_invoice oi
              WHERE oi.id_order = o.id_order
                AND oi.date_add >= '2025-02-01 00:00:00'
                AND oi.date_add <= '2025-02-28 23:59:59'
          )
        ), 0) > 0
ORDER BY ca_ht DESC, code_client ASC;