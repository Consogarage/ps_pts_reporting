SELECT
    o.id_order,
    o.reference AS order_reference,
    o.current_state,
    DATE(o.date_add) AS order_date,
    oi.invoice_date,
    CASE
        WHEN oi.invoice_date >= '2026-02-01 00:00:00'
         AND oi.invoice_date <= '2026-02-28 23:59:59'
         AND oi.invoice_date IS NOT NULL
         AND oi.invoice_date <> '0000-00-00 00:00:00'
         AND o.current_state NOT IN (6, 7)
        THEN 1
        ELSE 0
    END AS incluse_dans_reporting,
    CASE
        WHEN o.current_state IN (6, 7) THEN 'EXCLUE_ETAT_6_7'
        WHEN oi.invoice_date IS NULL OR oi.invoice_date = '0000-00-00 00:00:00' THEN 'EXCLUE_NON_FACTUREE'
        WHEN oi.invoice_date < '2026-02-01 00:00:00' OR oi.invoice_date > '2026-02-28 23:59:59' THEN 'EXCLUE_FACTURE_HORS_PERIODE'
        ELSE 'INCLUSE'
    END AS raison_exclusion,
    ROUND(IFNULL(o.total_products, 0), 2) AS total_products_ht
FROM ps_orders o
LEFT JOIN (
    SELECT
        oi1.id_order,
        MIN(oi1.date_add) AS invoice_date
    FROM ps_order_invoice oi1
    GROUP BY oi1.id_order
) oi
    ON oi.id_order = o.id_order
WHERE o.date_add >= '2026-02-01 00:00:00'
  AND o.date_add <= '2026-02-28 23:59:59'
  AND (
      o.current_state IN (6, 7)
      OR oi.invoice_date IS NULL
      OR oi.invoice_date = '0000-00-00 00:00:00'
      OR oi.invoice_date < '2026-02-01 00:00:00'
      OR oi.invoice_date > '2026-02-28 23:59:59'
  )
ORDER BY incluse_dans_reporting ASC, o.date_add ASC;