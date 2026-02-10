# Module WK Gestion Fournisseurs - Tables et colonnes

Ce document liste les tables du module WK Gestion Fournisseurs (prefixe `ps_wkdelivery_`) et leurs colonnes principales, utile pour l exploitation et les exports.

## Tableau recapitulatif

| Table                           | Colonnes (nb) |
| ------------------------------- | ------------: |
| ps_wkdelivery_allow_oosp        |             2 |
| ps_wkdelivery_cart              |             9 |
| ps_wkdelivery_cart_product      |            13 |
| ps_wkdelivery_contact           |             8 |
| ps_wkdelivery_cron              |             6 |
| ps_wkdelivery_history           |             5 |
| ps_wkdelivery_import            |             9 |
| ps_wkdelivery_orders            |            27 |
| ps_wkdelivery_order_attachments |             3 |
| ps_wkdelivery_order_detail      |            18 |
| ps_wkdelivery_payment           |             7 |
| ps_wkdelivery_products          |             7 |
| ps_wkdelivery_state             |             3 |
| ps_wkdelivery_state_lang        |             3 |
| ps_wkdelivery_supplier          |             5 |
| ps_wkdelivery_templates         |            26 |

## Liste detaillee (list markdown)

- ps_wkdelivery_allow_oosp
  - id_product : int(11) UNSIGNED
  - out_of_stock : tinyint(1) UNSIGNED
- ps_wkdelivery_cart
  - id_cart : int(10) UNSIGNED
  - id_shop : int(11) UNSIGNED
  - id_lang : int(10) UNSIGNED
  - id_currency : int(10) UNSIGNED
  - id_supplier : int(10) UNSIGNED
  - additional_costs : decimal(20,6)
  - general_discount : decimal(20,6)
  - date_add : datetime
  - date_upd : datetime
- ps_wkdelivery_cart_product
  - id_cart : int(10) UNSIGNED
  - id_product : int(10) UNSIGNED
  - id_product_attribute : int(10) UNSIGNED
  - supplier_reference : varchar(32)
  - unit_price_te : decimal(20,6)
  - quantity : int(10) UNSIGNED
  - price_te : decimal(20,6)
  - discount_rate : decimal(20,6)
  - discount_value_te : decimal(20,6)
  - tax_rate : decimal(20,6)
  - tax_value : decimal(20,6)
  - price_ti : decimal(20,6)
  - date_add : datetime
  - customer_id_orders : varchar(255)
- ps_wkdelivery_contact
  - id_wkdelivery_contact : int(10) UNSIGNED
  - firstname : varchar(64)
  - lastname : varchar(64)
  - email : varchar(128)
  - job : varchar(128)
  - is_main : tinyint(1) UNSIGNED
  - phone : varchar(32)
  - id_supplier : int(10) UNSIGNED
- ps_wkdelivery_cron
  - id_wkdelivery_cron : int(11) UNSIGNED
  - id_supplier : int(11) UNSIGNED
  - id_supply_order : int(11) UNSIGNED
  - date_add : datetime
  - date_upd : datetime
  - done : tinyint(1) UNSIGNED
- ps_wkdelivery_history
  - id_order_history : int(10) UNSIGNED
  - id_order : int(10) UNSIGNED
  - id_order_state : int(10) UNSIGNED
  - id_employee : int(11)
  - date_add : datetime
- ps_wkdelivery_import
  - import_id : int(11)
  - id_product : int(11) UNSIGNED
  - id_product_attribute : int(11) UNSIGNED
  - id_supplier : int(10) UNSIGNED
  - id_employee : int(11)
  - identifier : varchar(3)
  - identifier_data : varchar(32)
  - quantity : int(11)
  - new : tinyint(1) UNSIGNED
- ps_wkdelivery_orders
  - id_wkdelivery_orders : int(10) UNSIGNED
  - reference : varchar(100)
  - id_shop : int(11) UNSIGNED
  - id_supplier : int(10) UNSIGNED
  - id_lang : int(10) UNSIGNED
  - id_currency : int(10) UNSIGNED
  - id_cart : int(10) UNSIGNED
  - current_state : int(10) UNSIGNED
  - total_paid : decimal(20,6)
  - additional_costs : decimal(20,6)
  - general_discount : decimal(20,6)
  - conversion_rate : decimal(13,6)
  - warehouse : int(11) UNSIGNED
  - date_received : date
  - expected_delivery_days : int(10) UNSIGNED
  - date_add : datetime
  - date_upd : datetime
  - date_orderform_sent : datetime
  - delivery_date_validation : date
  - delivery_date_supplier : date
  - valid : int(1) UNSIGNED
  - update_stock : tinyint(1) UNSIGNED
  - parent_order : int(11) UNSIGNED
  - ids_employees : varchar(50)
  - automatic_reminder : tinyint(1) UNSIGNED
  - note : text
  - extra_data : text
- ps_wkdelivery_order_attachments
  - id_attachment : int(11)
  - id_order : int(11) UNSIGNED
  - filename : varchar(250)
- ps_wkdelivery_order_detail
  - id_wkdelivery_order_detail : int(10) UNSIGNED
  - id_delivery : int(10) UNSIGNED
  - id_product : int(10) UNSIGNED
  - id_product_attribute : int(10) UNSIGNED
  - designation : text
  - supplier_reference : varchar(32)
  - unit_price_te : decimal(20,6)
  - quantity : int(10) UNSIGNED
  - real_quantity : int(10) UNSIGNED
  - conditioning_quantity : varchar(255)
  - price_te : decimal(20,6)
  - discount_rate : decimal(20,6)
  - discount_value_te : decimal(20,6)
  - tax_rate : decimal(20,6)
  - tax_value : decimal(20,6)
  - price_ti : decimal(20,6)
  - customer_id_orders : varchar(255)
  - delivered : tinyint(1) UNSIGNED
- ps_wkdelivery_payment
  - id_order_payment : int(11)
  - order_reference : varchar(50)
  - id_currency : int(10) UNSIGNED
  - conversion_rate : decimal(13,6)
  - amount : decimal(10,2)
  - payment_method : varchar(255)
  - date_add : datetime
- ps_wkdelivery_products
  - id_wkdelivery_products : int(11) UNSIGNED
  - id_product : int(11) UNSIGNED
  - id_product_attribute : int(11) UNSIGNED
  - id_shop : int(11) UNSIGNED
  - min_stock : int(11)
  - target_qty : int(11)
  - conditioning_qty : int(11)
- ps_wkdelivery_state
  - id_order_state : int(10) UNSIGNED
  - color : varchar(32)
  - editable : tinyint(1)
- ps_wkdelivery_state_lang
  - id_order_state : int(10) UNSIGNED
  - id_lang : int(10) UNSIGNED
  - name : varchar(64)
- ps_wkdelivery_supplier
  - id_wkdelivery_supplier : int(10) UNSIGNED
  - supplier_delivery_delay : int(10) UNSIGNED
  - additional_costs : decimal(20,6)
  - id_currency : int(11) UNSIGNED
  - vat_exemption : tinyint(1) UNSIGNED
- ps_wkdelivery_templates
  - id_wkdelivery_templates : int(11)
  - name : varchar(255)
  - description : text
  - thumbnail : text
  - header_template : text
  - content_template : text
  - footer_template : text
  - customize_css : text
  - id_shop : int(11)
  - is_default : tinyint(1) UNSIGNED
  - display_col_qty : tinyint(1) UNSIGNED
  - display_col_conditioning_quantity : tinyint(1) UNSIGNED
  - display_col_real_qty : tinyint(1) UNSIGNED
  - display_col_unit_pricete : tinyint(1) UNSIGNED
  - display_col_total_te : tinyint(1) UNSIGNED
  - display_col_discount_rate : tinyint(1) UNSIGNED
  - display_col_total_after_discount : tinyint(1) UNSIGNED
  - display_col_tax_rate : tinyint(1) UNSIGNED
  - display_col_total_ti : tinyint(1) UNSIGNED
  - display_col_ean : tinyint(1) UNSIGNED
  - display_col_img : tinyint(1) UNSIGNED
  - display_col_reference : tinyint(1) UNSIGNED
  - display_col_supplier_reference : tinyint(1) UNSIGNED
  - display_orders_references : tinyint(1) UNSIGNED
  - type : char(3)