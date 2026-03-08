<?php
// export/export_universal.php
session_start();
date_default_timezone_set('Europe/Budapest');
ini_set('memory_limit', '512M'); // Nagyobb táblák exportálásához
ini_set('max_execution_time', 300);

// --- JOGOSULTSÁG ELLENŐRZÉSE ---
require_once __DIR__.'/../../../jogosultsag.php';
ellenorizJogosultsag('belepes');

require_once $_SERVER['DOCUMENT_ROOT'] . '/sql_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/php_xlsx_writer/xlsxwriter.class.php';

$pdo = csatlakozasSzerver1();

$selected_table = $_REQUEST['selected_table'] ?? '';
if (empty($selected_table)) {
    die("Nincs kiválasztva tábla.");
}

// 1. Tábla validálása és eredeti fejlécek lekérése a biztonság miatt
$stmtCols = $pdo->prepare("DESCRIBE `$selected_table`");
try {
    $stmtCols->execute();
} catch (Exception $e) {
    die("Érvénytelen tábla.");
}

$headers = [];
while ($col = $stmtCols->fetch(PDO::FETCH_ASSOC)) {
    // Az 'id' oszlopot általában nem exportáljuk a felhasználónak, de ha kell, ez kivehető
    if ($col['Field'] !== 'id') { 
        $headers[] = $col['Field'];
    }
}

// 2. Szűrőfeltételek kinyerése a meglévő logika.php-ból!
// Trükk: a 'count' limit megadásával a logika.php felépíti a $whereSQL-t, 
// de nem tölti le a memóriába az összes adatot. Azt mi csináljuk majd soronként.
$_REQUEST['limit'] = 'count'; 
$tableName = $selected_table; 
require_once __DIR__.'/../Kereses/logika.php';

// 3. Milyen oszlopokat és milyen sorrendben exportáljunk?
$export_type = $_POST['export_type'] ?? 'all';
$export_headers = [];

if ($export_type === 'custom' && !empty($_POST['export_col'])) {
    foreach ($_POST['export_col'] as $col) {
        // Ellenőrizzük, hogy a kért oszlop tényleg létezik-e a táblában (Biztonság)
        if (in_array($col, $headers)) {
            $export_headers[] = $col;
        }
    }
} else {
    // Alapértelmezett: minden oszlop
    $export_headers = $headers;
}

if (empty($export_headers)) {
    die("Nincs kiválasztott oszlop az exportáláshoz.");
}

// 4. XLSX inicializálása
$writer = new XLSXWriter();
$writer->setAuthor('M-VA Rendszer');

// Fejléc típusok beállítása (minden 'string' alapesetben, hogy az Excel ne formázza át automatikusan a dátumokat/számokat rosszul)
$header_types = array_fill_keys($export_headers, 'string');
$writer->writeSheetHeader('Export', $header_types);

// 5. Adatok lekérése (soronként, hogy ne fogyjon el a memória nagy tábláknál)
$sql = "SELECT * FROM `$selected_table` $whereSQL";
$stmtData = $pdo->prepare($sql);
$stmtData->execute($queryParams);

while ($row = $stmtData->fetch(PDO::FETCH_ASSOC)) {
    $export_row = [];
    foreach ($export_headers as $col) {
        $value = $row[$col] ?? '';
        
        // Excel Formula Injection elleni védelem (az eredeti kódodból megtartva!)
        if (is_string($value) && (str_starts_with($value, '+') || str_starts_with($value, '='))) {
            $value = "'" . $value;
        }
        
        $export_row[] = $value;
    }
    $writer->writeSheetRow('Export', $export_row);
}

// 6. Fájl küldése letöltésre a böngészőnek
$tisztitott_tablanev = preg_replace('/[^a-zA-Z0-9_]/', '', $selected_table);
$fajlnev = "export_" . $tisztitott_tablanev . "_" . date('Y.m.d_H-i') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fajlnev . '"');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: must-revalidate');
header('Pragma: public');

$writer->writeToStdOut();
exit;
