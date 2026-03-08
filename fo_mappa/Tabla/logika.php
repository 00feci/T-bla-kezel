<?php
// /Tabla/logika.php

// 1. Megszámoljuk a szűrt találatokat
$countSql = "SELECT COUNT(*) FROM `$selected_table` $whereSQL";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($queryParams);
$totalRows = (int)$stmtCount->fetchColumn();

// 2. Lapozás újraszámolása a szűrt adatok alapján
$totalPages = ceil($totalRows / $limit);
if ($page > $totalPages) $page = max(1, $totalPages);
$startOffset = ($page - 1) * $limit;

// 3. Tényleges adatok lekérése
$dataSql = "SELECT * FROM `$selected_table` $whereSQL LIMIT $limit OFFSET $startOffset";
$stmtData = $pdo->prepare($dataSql);
$stmtData->execute($queryParams);
$pagedData = $stmtData->fetchAll(PDO::FETCH_ASSOC);