<?php
// Importe en local l'export CSV du Gestionnaire SQL (requête tests/prod_export_query.sql).
// Usage : php import_sql_manager_export.php request_sql_X.csv
if (PHP_SAPI !== 'cli' || !isset($argv[1])) {
    fwrite(STDERR, "Usage : php import_sql_manager_export.php <fichier.csv>\n");
    exit(2);
}

require_once dirname(__DIR__, 3) . '/config/config.inc.php';

$handle = fopen($argv[1], 'r');
if (!$handle) {
    fwrite(STDERR, "Fichier illisible : {$argv[1]}\n");
    exit(2);
}

fgetcsv($handle, 0, ';', '"', '\\'); // en-tête
$db = Db::getInstance();
$ok = 0;
$errors = 0;

while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
    $query = isset($row[0]) ? $row[0] : '';
    if (strpos($query, 'INSERT INTO ') !== 0) {
        continue;
    }
    if ($db->execute($query, false)) {
        $ok++;
    } else {
        $errors++;
        fwrite(STDERR, 'ERREUR : ' . $db->getMsgError() . ' -> ' . substr($query, 0, 120) . "\n");
    }
}

// ps_wkdelivery_orders a pour clé primaire (id, date_upd) : une commande fournisseur modifiée
// en prod est insérée en double. On ne garde que la version la plus récente.
$db->execute('DELETE w FROM ' . _DB_PREFIX_ . 'wkdelivery_orders w'
    . ' INNER JOIN ' . _DB_PREFIX_ . 'wkdelivery_orders w2 ON w2.id_wkdelivery_orders = w.id_wkdelivery_orders'
    . ' AND w2.date_upd > w.date_upd');
echo $db->Affected_Rows() . " anciennes versions de commandes fournisseur supprimées.\n";

echo "{$ok} lignes importées, {$errors} erreurs.\n";
exit($errors ? 1 : 0);
