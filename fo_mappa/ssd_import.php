<?php
// ssd_import.php - Nyers adatok betöltése az SSD táblába

// 📦 Konfiguráció betöltése a te logikád szerint
$config_path = __DIR__ . '/sql_config.php';
try {
    require_once $config_path;
    if (!function_exists('csatlakozasSzerver1')) {
        die("Hiba: Az adatbázis csatlakozási függvény nem található.");
    }
    $pdo = csatlakozasSzerver1();
} catch (Exception $e) {
    die("Hiba a konfigurációs fájl betöltésekor: " . $e->getMessage());
}

/**
 * Beolvassa a CSV-t és soronként az SSD táblába menti
 */
function importToSSD($filePath, $pdo) {
    $importId = 'import_' . time() . '_' . uniqid();
    
    if (!file_exists($filePath)) return "Hiba: A fájl nem létezik.";
    $handle = fopen($filePath, "r");
    
    if (!$handle) return "Hiba: A fájl nem nyitható meg.";

    $pdo->beginTransaction();
    try {
        // LONGTEXT-be mentünk, nincs darabolás, nincs adatvesztés [cite: 2026-02-18]
        $stmt = $pdo->prepare("INSERT INTO m_va_ssd_raw (import_id, line_content) VALUES (?, ?)");
        
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') continue; 
            
            $stmt->execute([$importId, $line]);
        }
        
        fclose($handle);
        $pdo->commit();
        return $importId; 
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        fclose($handle);
        return "Hiba az importálás során: " . $e->getMessage();
    }
}

// Példa a használatra:
// $eredmeny = importToSSD('adatok.csv', $pdo);
// echo "Új import azonosító: " . $eredmeny;