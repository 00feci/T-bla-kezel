<?php
session_start();
require_once __DIR__.'/../../jogosultsag.php';
ellenorizJogosultsag('belepes');
header("Pragma: no-cache");

require_once $_SERVER['DOCUMENT_ROOT'] .'/oldal/sql_config.php';
$pdo = csatlakozasSzerver1();

// 0. Alap adatok lekérése
$tablesStmt = $pdo->query("SHOW TABLES");
$availableTables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

// SZŐRSZÁLHASOGATÓ MÓDOSÍTÁS: Nincs kényszerített alapértelmezett tábla [cite: 2026-03-07]
$selected_table = $_REQUEST['selected_table'] ?? null;

// Csak akkor ellenőrizzük, ha van választott tábla
if ($selected_table && !in_array($selected_table, $availableTables)) {
    $selected_table = null; 
}

// 1. Fejlécek meghatározása a választott tábla alapján [cite: 2026-02-18]
$headers = [];
$queryParams = [];
$whereSQL = "";
$pagedData = [];
$totalRows = 0;
$totalPages = 1;
if (!isset($limit_param)) {
    $limit_param = $_REQUEST['limit'] ?? 5;
}
$page = (int)($_REQUEST['page'] ?? 1);

// SZŐRSZÁLHASOGATÓ VÉDELEM: Csak akkor fut le a lekérdezés, ha van tábla! [cite: 2026-03-07]
if ($selected_table) {
    $isStructured = ($selected_table !== 'raw_import_data');

    if (!$isStructured) {
        $stmtHeader = $pdo->query("SELECT line_content FROM raw_import_data WHERE id = 1");
        $headerContent = $stmtHeader->fetchColumn();
        if ($headerContent) {
            $headers = explode(';', rtrim($headerContent, ';'));
            $headers[] = 'Extra adat';
        }
    } else {
        $stmtCols = $pdo->query("DESCRIBE `$selected_table` ");
        $cols = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
        foreach ($cols as $colName) {
            if ($colName === 'id') continue;
            $headers[] = $colName;
        }
    }

    require_once 'Kereses/logika.php';
    include_once 'Tabla/torles.php';

    // Abszolút összes sor kiszámítása a teljes táblához [cite: 2026-03-08]
    $stmtAbs = $pdo->query("SELECT COUNT(*) FROM `$tableName` " . ($selected_table === 'raw_import_data' ? "WHERE id > 1" : ""));
    $absoluteTotal = (int)$stmtAbs->fetchColumn();

    $stmtTotalCount = $pdo->prepare("SELECT COUNT(*) FROM `$tableName` $whereSQL");
    $stmtTotalCount->execute($queryParams);
    $totalRows = (int)$stmtTotalCount->fetchColumn();

    $limit = ($limit_param === 'all' || $limit_param === 'count') ? ($totalRows ?: 1) : (int)$limit_param;
    $startOffset = ($page - 1) * $limit;
    $totalPages = ceil($totalRows / $limit) ?: 1;

    $limitSQL = ($limit_param !== 'all' && $limit_param !== 'count') ? "LIMIT $limit OFFSET $startOffset" : "";
   if ($limit_param !== 'count') {

    $stmtData = $pdo->prepare("SELECT * FROM `$selected_table` $whereSQL $limitSQL");
    $stmtData->execute($queryParams);
    $pagedData = $stmtData->fetchAll(PDO::FETCH_ASSOC);

} else {

    // CSAK SZÁM esetén nem kérünk le rekordokat
    $pagedData = [];
}
}
?>
<title>Tábla</title> <link rel="icon" type="image/png" href="/M-VA-.png">
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="Kereses/stilus.css">
    <link rel="stylesheet" href="Mezok/stilus.css">
    <link rel="stylesheet" href="Tabla/stilus.css">
</head>
<body>
    <form method="POST" action="tabla1.php" id="mainForm">
        <?php 
include 'Tabla/torles.php';  
        include 'Kereses/nezet.php'; // A dinamikus fieldset [cite: 2026-02-18]
        include 'Mezok/nezet.php';   // Táblaváltó és Megjelenítés [cite: 2026-03-05]
        include 'Tabla/nezet.php';   // Maga a táblázat [cite: 2026-03-05]
        include_once 'upload/modal.php';
        include_once 'export/modal.php';
        ?>
        <input type="hidden" name="page" id="current_page" value="<?= $page ?>">
    </form>

  <script src="Kereses/szkript.js?v=<?= time() ?>"></script>
    <script src="szuro.js?v=<?= time() ?>"></script>
    <script src="upload/script.js?v=<?= time() ?>"></script>
</body>

</html>


