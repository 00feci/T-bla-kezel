<?php
// Törlési logika (Szigorúan tilos szemetet hagyni [cite: 2026-02-18])
if (isset($_POST['action']) && $_POST['action'] === 'delete_table' && $_POST['table_to_delete'] !== 'raw_import_data') {
    $tableToDelete = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['table_to_delete']);
    $pdo->exec("DROP TABLE IF EXISTS `$tableToDelete` ");
    
    // SZŐRSZÁLHASOGATÓ MÓDOSÍTÁS: 
    // Átirányítás paraméterrel, hogy a rendszer "tudja", ne töltsön be mást [cite: 2026-03-07]
    header("Location: tabla1.php?status=table_deleted");
    exit;
}
?>