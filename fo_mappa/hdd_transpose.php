<?php
// hdd_transpose.php - Adatok kiterítése strukturált táblába

require_once __DIR__ . '/sql_config.php';
$pdo = csatlakozasSzerver1();

/**
 * Létrehoz egy egyedi táblát és átpakolja az adatokat az SSD-ből
 */
function createStructuredTable($importId, $customName, $pdo) {
    // 1. Táblanév tisztítása (csak betű, szám és alulvonás)
    $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $customName);
    
    // Ellenőrizzük, létezik-e már ilyen tábla
    $check = $pdo->query("SHOW TABLES LIKE '$tableName'")->fetch();
    if ($check) return "Hiba: A '$tableName' tábla már létezik. Kérlek, adj meg más nevet.";

    // 2. Maximális oszlopszám és fejlécek meghatározása
    $stmt = $pdo->prepare("SELECT line_content FROM m_va_ssd_raw WHERE import_id = ? ORDER BY id ASC");
    $stmt->execute([$importId]);
    
    $maxCols = 0;
    $headers = [];
    $isFirst = true;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cols = explode(';', rtrim($row['line_content'], ';'));
        $count = count($cols);
        if ($count > $maxCols) $maxCols = $count;
        
        if ($isFirst) {
            $headers = $cols;
            $isFirst = false;
        }
    }

    if ($maxCols === 0) return "Hiba: Nem található adat ehhez az importáláshoz.";

    // 3. CREATE TABLE lekérdezés összeállítása
    $columnsSQL = ["`id` INT AUTO_INCREMENT PRIMARY KEY"];
    for ($i = 0; $i < $maxCols; $i++) {
        // Ha van név a fejlécben, azt használjuk, egyébként generálunk egyet [cite: 2026-02-18]
        $colName = (!empty($headers[$i])) ? preg_replace('/[^a-zA-Z0-9_]/', '', $headers[$i]) : "extra_adat_" . ($i + 1);
        // Ha üres vagy duplikált lenne a név
        if (empty($colName)) $colName = "oszlop_" . ($i + 1);
        
        $columnsSQL[] = "`$colName` LONGTEXT NULL";
    }
    
    $createSQL = "CREATE TABLE `$tableName` (" . implode(", ", $columnsSQL) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($createSQL);

    // 4. Adatok átmásolása batch (kötegelt) módban a sebességért
    $stmt->execute([$importId]); // Újrafuttatjuk a lekérdezést az elejétől
    $stmt->fetch(); // Átugorjuk a fejlécet

    $pdo->beginTransaction();
    try {
        // Dinamikus INSERT előkészítése
        $placeholders = array_fill(0, $maxCols, "?");
        $insertSQL = "INSERT INTO `$tableName` VALUES (NULL, " . implode(", ", $placeholders) . ")";
        $insertStmt = $pdo->prepare($insertSQL);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cols = explode(';', rtrim($row['line_content'], ';'));
            // Kiegészítjük üres értékekkel, ha rövidebb a sor
            $data = array_pad($cols, $maxCols, '');
            // Levágjuk, ha hosszabb lenne (elvileg a maxCols miatt nem lesz)
            $data = array_slice($data, 0, $maxCols);
            
            $insertStmt->execute($data);
        }
        $pdo->commit();
        return "Siker: A '$tableName' tábla létrejött és az adatok feltöltve.";
    } catch (Exception $e) {
        $pdo->rollBack();
        return "Hiba az adatok átmásolásakor: " . $e->getMessage();
    }
}