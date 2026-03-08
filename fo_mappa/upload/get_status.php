<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] .'/oldal/sql_config.php';

$importId = $_GET['import_id'] ?? '';
if (empty($importId)) {
    echo json_encode(['step' => 'waiting', 'current' => 0, 'total' => 0]);
    exit;
}

try {
    $pdo = csatlakozasSzerver1();
    $stmt = $pdo->prepare("SELECT step, current, total FROM m_va_import_status WHERE id = ?");
    $stmt->execute([$importId]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($res ?: ['step' => 'waiting', 'current' => 0, 'total' => 0]);
} catch (Exception $e) {
    echo json_encode(['step' => 'error', 'message' => $e->getMessage()]);
}