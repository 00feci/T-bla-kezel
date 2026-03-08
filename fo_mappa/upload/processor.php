<?php
ob_start(); 
session_start();

ini_set('session.use_cookies', 0);
ini_set('session.use_only_cookies', 0);
ini_set('session.use_trans_sid', 0);

function safe_json_output($data) {
    ob_clean();
    echo json_encode($data);
    exit;
}

ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__.'/../../../jogosultsag.php';
ellenorizJogosultsag('belepes');
require_once $_SERVER['DOCUMENT_ROOT'] .'/oldal/sql_config.php';
  try {
    $pdo = csatlakozasSzerver1(); 
    $pdoStatus = csatlakozasSzerver1(); 
    $pdoStatus->exec("CREATE TABLE IF NOT EXISTS m_va_import_status (id VARCHAR(100) PRIMARY KEY, step VARCHAR(20), current INT, total INT) ENGINE=InnoDB");
} catch (Exception $e) {
    safe_json_output(['success' => false, 'message' => 'Adatbázis hiba: ' . $e->getMessage()]);
}

if (isset($_POST['action']) && $_POST['action'] === 'check_table') {
    $tableNameRaw = $_POST['table_name'] ?? '';
    $tName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableNameRaw);
    if (empty($tName)) safe_json_output(['exists' => false]);
    
    $stmt = $pdoStatus->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$tName]);
    safe_json_output(['exists' => (bool)$stmt->fetch()]);
}
if (isset($_FILES['csv_file']) && !isset($_POST['action'])) {
    $tmpPath = $_FILES['csv_file']['tmp_name'];
    $importId = $_POST['import_id'] ?? ('import_' . time() . '_' . uniqid());
    
    $pdoStatus->prepare("REPLACE INTO m_va_import_status (id, step, current, total) VALUES (?, 'ssd', 0, 0)")
        ->execute([$importId]);        
    
    $handle = fopen($tmpPath, "r");
    if (!$handle) safe_json_output(['success' => false, 'message' => 'A fájl nem olvasható.']);

   $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO m_va_ssd_raw (import_id, line_content) VALUES (?, ?)");
        $statusUpd = $pdoStatus->prepare("REPLACE INTO m_va_import_status (id, step, current, total) VALUES (?, 'ssd', ?, 0)");
        
        $ssd_count = 0;
        while (($data = fgetcsv($handle, 0, ';', '"')) !== false) {
            if (empty($data) || (count($data) === 1 && $data[0] === null)) continue;
            $stmt->execute([$importId, json_encode($data, JSON_UNESCAPED_UNICODE)]);
            $ssd_count++;
            
            // Megtartva a te logikád alapján
            if ($ssd_count % 1 === 0) {
                $statusUpd->execute([$importId, $ssd_count]);
            }

            // ÚJ: Memória ürítése 5000 soronként (A 1206-os összeomlás ellen)
            if ($ssd_count % 5000 === 0) {
                $pdo->commit();
                $pdo->beginTransaction();
            }
        }
        
        // KÉNYSZERÍTETT FRISSÍTÉS A VÉGÉN:
        $statusUpd->execute([$importId, $ssd_count]); 
        
        fclose($handle);
        if ($pdo->inTransaction()) {
            $pdo->commit(); 
        }
        safe_json_output(['success' => true, 'import_id' => $importId]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        safe_json_output(['success' => false, 'message' => 'SSD hiba: ' . $e->getMessage()]);
    }
}
// 2. FÁZIS: HDD Transzponálás
if (isset($_POST['action']) && $_POST['action'] === 'transpose') {
    $importId = $_POST['import_id'];
    // Tisztítás és hossz korlátozás (SQL injection elleni védelem + karakterlimit)
    $customName = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['table_name']);
    $customName = substr($customName, 0, 64); // MySQL táblanév limit

    if (empty($customName)) safe_json_output(['success' => false, 'message' => 'Érvénytelen táblanév!']);
    try {
        // Fejléc beolvasása
        $stmt = $pdoStatus->prepare("SELECT line_content FROM m_va_ssd_raw WHERE import_id = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$importId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) safe_json_output(['success' => false, 'message' => 'Nincs adat az SSD táblában.']);
        
        $headers = json_decode($row['line_content'], true);
        $maxCols = count($headers);

        // Tábla létrehozása
        $columnsSQL = ["`id` INT AUTO_INCREMENT PRIMARY KEY"];
        $usedNames = ['id' => true]; 
        foreach ($headers as $i => $raw) {
            $name = preg_replace('/[^a-zA-Z0-9_]/', '', $raw) ?: "extra_".($i+1);
            if (isset($usedNames[strtolower($name)])) $name .= "_csv_".$i;
            $usedNames[strtolower($name)] = true;
            $columnsSQL[] = "`$name` LONGTEXT NULL";
        }
        $pdoStatus->exec("CREATE TABLE `$customName` (" . implode(", ", $columnsSQL) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Statisztika és előkészítés
        $totalCount = $pdoStatus->query("SELECT COUNT(*) FROM m_va_ssd_raw WHERE import_id = '$importId'")->fetchColumn();
        $readStmt = $pdoStatus->prepare("SELECT line_content FROM m_va_ssd_raw WHERE import_id = ? ORDER BY id ASC");
        $readStmt->execute([$importId]);
        $readStmt->fetch(); // Fejléc skip

       // HDD Fázis indítása
        $pdoStatus->prepare("REPLACE INTO m_va_import_status (id, step, current, total) VALUES (?, 'hdd', 1, ?)")
                  ->execute([$importId, $totalCount]);

      $pdo->beginTransaction();
        try {
            $insertStmt = $pdo->prepare("INSERT INTO `$customName` VALUES (NULL, " . implode(", ", array_fill(0, $maxCols, "?")) . ")");
            $statusUpdHdd = $pdoStatus->prepare("REPLACE INTO m_va_import_status (id, step, current, total) VALUES (?, 'hdd', ?, ?)");

            $hdd_count = 0;
            $chunkSize = 500; // Kisebb egységekre bontjuk a 1206-os hiba elkerülése érdekében

            while ($row = $readStmt->fetch(PDO::FETCH_ASSOC)) {
                $lineData = json_decode($row['line_content'], true);
                $paddedData = array_pad($lineData ?: [], $maxCols, '');
                $insertStmt->execute(array_slice($paddedData, 0, $maxCols));
                $hdd_count++;
                
                if ($hdd_count % $chunkSize === 0) {
                    $statusUpdHdd->execute([$importId, $hdd_count, $totalCount]);

                    // Tranzakció lezárása és azonnali újranyitása a zárolások felszabadításához
                    $pdo->commit();
                    $pdo->beginTransaction();
                    // A biztonság kedvéért a statement-et újra előkészítjük az új tranzakcióhoz
                    $insertStmt = $pdo->prepare("INSERT INTO `$customName` VALUES (NULL, " . implode(", ", array_fill(0, $maxCols, "?")) . ")");
                }
            }
            
          // 1. LÉPÉS: Mindenképpen lezárjuk az utolsó tranzakciót
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }

            // 2. LÉPÉS: Kényszerített 100% jelentés az adatbázisba
            $statusUpdHdd->execute([$importId, $totalCount, $totalCount]);

            // 3. LÉPÉS: A törlést (DELETE) optimalizáljuk. 
            // Ha XAMPP-on ez még mindig lassú, használhatunk egy gyorsabb megoldást.
            $pdo->exec("DELETE FROM m_va_ssd_raw WHERE import_id = '$importId'");
            
            // 4. LÉPÉS: Visszaküldjük a sikert a JS-nek
            safe_json_output(['success' => true]);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            safe_json_output(['success' => false, 'message' => 'HDD hiba: ' . $e->getMessage()]);
        }
    } catch (Exception $e) {
        safe_json_output(['success' => false, 'message' => 'Rendszer hiba: ' . $e->getMessage()]);
    }
}
// FIGYELEM: Itt a PHP fájl vége! Ne legyen utána JS kód!