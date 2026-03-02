SELECT
    ROUND(SUM(q.missing_supplier_purchase_ht), 2) AS total_missing_supplier_purchase_ht,
    SUM(CASE WHEN q.missing_supplier_purchase_ht <> 0 THEN 1 ELSE 0 END) AS nb_orders_with_missing_supplier_purchase_ht_non_zero
FROM (
    SELECT
        o.id_order,
        (
            SELECT IFNULL(SUM(od.product_quantity * COALESCE(ps.product_supplier_price_te, 0)), 0)
            FROM ps_order_detail od
            LEFT JOIN ps_product p
                ON p.id_product = od.product_id
            LEFT JOIN ps_product_supplier ps
                ON ps.id_product = od.product_id
                AND ps.id_product_attribute = od.product_attribute_id
                AND ps.id_supplier = p.id_supplier
            LEFT JOIN ps_wkdelivery_order_detail wlink
                ON wlink.id_product = od.product_id
                AND wlink.id_product_attribute = od.product_attribute_id
                AND FIND_IN_SET(o.id_order, REPLACE(TRIM(BOTH '|' FROM wlink.customer_id_orders), '|', ','))
            WHERE od.id_order = o.id_order
              AND wlink.id_wkdelivery_order_detail IS NULL
        ) AS missing_supplier_purchase_ht
    FROM ps_orders o
    WHERE o.invoice_date >= '2026-01-01 00:00:00'
      AND o.invoice_date <= '2026-01-31 23:59:59'
      AND o.invoice_date IS NOT NULL
      AND o.invoice_date <> '0000-00-00 00:00:00'
) q;