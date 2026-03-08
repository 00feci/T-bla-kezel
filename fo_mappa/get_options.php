<?php
// get_options.php
// --- JOGOSULTSÁG ELLENŐRZÉSE ---
//require_once $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';
require_once __DIR__.'/../../jogosultsag.php';
ellenorizJogosultsag('belepes');


header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] .'/oldal/sql_config.php';
$pdo = csatlakozasSzerver1();

// Csak a biztonságos karaktereket engedjük át a tábla és oszlopnevekben
$table = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['table'] ?? '');
$column = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['column'] ?? '');

if (!$table || !$column || $table === 'raw_import_data') {
    echo json_encode([]); exit;
}

try {
    // Csak a ténylegesen létező, nem üres, egyedi értékeket kérjük le [cite: 2026-02-18]
    $stmt = $pdo->query("SELECT DISTINCT `$column` FROM `$table` WHERE `$column` IS NOT NULL AND `$column` != '' ORDER BY `$column` ASC");
    $options = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($options);
} catch (Exception $e) {
    echo json_encode([]);
}