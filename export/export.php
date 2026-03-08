<?php
session_start();
date_default_timezone_set('Europe/Budapest');
require_once $_SERVER['DOCUMENT_ROOT'] . '/php_xlsx_writer/xlsxwriter.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/sql_config.php';

// Jogosultság ellenőrzés
$pdo1 = csatlakozasSzerver1();
$pdo2 = csatlakozasSzerver2();
$felhasznalo = $_SESSION['felhasznalo'] ?? '';
$stmt = $pdo1->prepare("SELECT `Toborzás` FROM m_va_felhasznalok WHERE `felhasználónév` = :nev LIMIT 1");
$stmt->execute(['nev' => $felhasznalo]);
$jog = $stmt->fetchColumn();
if ($jog !== 'OK') {
    die('Nincs jogosultság');
}

// Adatok lekérése
$stmt = $pdo2->query("SELECT * FROM m_va_adatbazis WHERE státusz REGEXP 'Azonnali kiküldve'");
$adatok = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$adatok) {
    die('Nincs átküldhető adat');
}

// Fogadó tábla oszlopai
$lekerdezes = $pdo1->query("DESCRIBE toborzas");
$oszlopok = array_column($lekerdezes->fetchAll(PDO::FETCH_ASSOC), 'Field');

// Új adatlista építése
$letoltesDatum = date('Y.m.d H:i');
$beszurt = 0;
$kihagyott = 0;
$atkuldeshezId = [];

$modositottAdatok = [];

foreach ($adatok as $sor) {
    $ujSor = [];

    foreach ($oszlopok as $oszlop) {
        if ($oszlop === 'jelentkezés_cv') {
            $ujSor[$oszlop] = 'igen';
        } elseif ($oszlop === 'születési_idő') {
            $ujSor[$oszlop] = empty($sor[$oszlop]) ? 'nem' : 'igen';
        } elseif ($oszlop === 'hr1' || $oszlop === 'hr2' || $oszlop === 'hr3') {
            $ujSor[$oszlop] = '';
        } elseif ($oszlop === 'letoltes_datuma') {
            $ujSor[$oszlop] = $letoltesDatum;
        } else {
            $ujSor[$oszlop] = $sor[$oszlop] ?? null;
        }
    }

    if (count($ujSor) !== count($oszlopok)) {
        $kihagyott++;
        continue;
    }

    // INSERT az adatbázis1-be
    $stmtInsert = $pdo1->prepare("INSERT INTO toborzas (" . implode(",", array_map(fn($o) => "`$o`", $oszlopok)) . ")
        VALUES (" . rtrim(str_repeat("?,", count($oszlopok)), ",") . ")");
    try {
        $stmtInsert->execute(array_map(function($value) {
            if (is_string($value) && (str_starts_with($value, '+') || str_starts_with($value, '='))) {
                return "'" . $value;
            }
            return $value ?? '';
        }, array_values($ujSor)));
        $beszurt++;
        $atkuldeshezId[] = $sor['id'];
        $modositottAdatok[] = $ujSor;
    } catch (Exception $e) {
        $kihagyott++;
    }
}

// XLSX fájl létrehozás
$writer = new XLSXWriter();
$fejlec = $oszlopok;
$writer->writeSheetHeader('Toborzás', array_fill_keys($fejlec, 'string'), ['suppress_row'=>false]);
foreach ($modositottAdatok as $sor) {
    $writer->writeSheetRow('Toborzás', array_map(function($value) {
        if (is_string($value) && (str_starts_with($value, '+') || str_starts_with($value, '='))) {
            return "'" . $value;
        }
        return $value ?? '';
    }, array_values($sor)));
}

// Állapot frissítés adatbázis2-ben
if (!empty($atkuldeshezId)) {
    $inQuery = implode(',', array_fill(0, count($atkuldeshezId), '?'));
    $pdo2->prepare("UPDATE m_va_adatbazis SET státusz = 'átküldve' WHERE id IN ($inQuery)")->execute($atkuldeshezId);
    $pdo1->prepare("DELETE FROM toborzas")->execute(); // előző adatok törlése, ha kell
    $pdo2->prepare("DELETE FROM m_va_adatbazis WHERE id IN ($inQuery)")->execute($atkuldeshezId);
}

// Letöltés válaszként
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="toborzas_' . date('Y.m.d H;i') . '.xlsx"');
header('Content-Transfer-Encoding: binary');
$writer->writeToStdOut();
exit;
?>
