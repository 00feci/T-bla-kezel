<?php
// --- JOGOSULTSÁG ELLENŐRZÉSE ---
//require_once $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';
require_once __DIR__.'/../../jogosultsag.php';
ellenorizJogosultsag('belepes');

// -------------------------------


header("Pragma: no-cache");

// 📦 Adatbázis kapcsolat - Robusztusabb útvonal kezelés
$config_path = $_SERVER['DOCUMENT_ROOT'] .'/oldal/sql_config.php'; // Alapértelmezett: egy szinttel feljebb

try {
    require_once $config_path;
    if (!function_exists('csatlakozasSzerver1')) {
        die("Hiba: Az adatbázis csatlakozási függvény nem található.");
    }
    $pdo = csatlakozasSzerver1();
} catch (Exception $e) {
    die("Hiba a konfigurációs fájl betöltésekor: " . $e->getMessage());
}

// 0. Táblák listázása és kiválasztása
$tablesStmt = $pdo->query("SHOW TABLES");
$availableTables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
$selected_table = isset($_REQUEST['selected_table']) ? $_REQUEST['selected_table'] : 'raw_import_data';

// Törlési logika (Szigorúan tilos szemetet hagyni [cite: 2026-02-18])
if (isset($_POST['action']) && $_POST['action'] === 'delete_table' && $_POST['table_to_delete'] !== 'raw_import_data') {
    $tableToDelete = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['table_to_delete']);
    $pdo->exec("DROP TABLE IF EXISTS `$tableToDelete` ");
    header("Location: tabla1.php");
    exit;
}





// 1. Fejlécek meghatározása a választott tábla alapján [cite: 2026-02-18]
$headers = [];
$isStructured = ($selected_table !== 'raw_import_data');

if (!$isStructured) {
    // Régi nyers tábla fejléce (id=1 sor)
    $stmtHeader = $pdo->query("SELECT line_content FROM raw_import_data WHERE id = 1");
    $headerContent = $stmtHeader->fetchColumn();
    if ($headerContent) {
        $headers = explode(';', rtrim($headerContent, ';'));
        $headers[] = 'Extra adat';
    }
} else {
    // Új HDD tábla fejlécei az SQL oszlopnevekből
    $stmtCols = $pdo->query("DESCRIBE `$selected_table` ");
    $cols = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
    foreach ($cols as $colName) {
        if ($colName === 'id') continue;
        $headers[] = $colName;
    }
}

// 2. Szűrési és lapozási alapértékek meghatározása [cite: 2026-02-18]
$limit_param = isset($_REQUEST['limit']) ? $_REQUEST['limit'] : 5;
$page = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 1;

$stmtTotalCount = $pdo->query("SELECT COUNT(*) FROM `$selected_table` " . (!$isStructured ? "WHERE id > 1" : ""));
$absoluteTotal = (int)$stmtTotalCount->fetchColumn();

if ($limit_param === 'all' || $limit_param === 'count') {
    $limit = $absoluteTotal > 0 ? $absoluteTotal : 1;
    $page = 1;
} else {
    $limit = (int)$limit_param;
    if ($limit <= 0) $limit = 5;
}
$startOffset = ($page - 1) * $limit;

// --- UNIVERZÁLIS KERESŐMOTOR 2.0 (AND/OR + ÖSSZES OPERÁTOR) --- [cite: 2026-02-18]
$whereClauses = [];
$queryParams = [];
$whereSQL = ""; 
$logic = (isset($_REQUEST['s_logic']) && $_REQUEST['s_logic'] === 'OR') ? 'OR' : 'AND';

if (isset($_REQUEST['s_col']) && is_array($_REQUEST['s_col'])) {
    foreach ($_REQUEST['s_col'] as $i => $col) {
        $op = $_REQUEST['s_op'][$i] ?? '=';
        $val = trim($_REQUEST['s_val'][$i] ?? '');

        if ($col !== '' && in_array($col, $headers)) {
            // Operátorok, amikhez nem kell bemeneti érték [cite: 2026-02-18]
            if ($op === 'IS NULL') { $whereClauses[] = "(`$col` IS NULL OR `$col` = '')"; continue; }
            if ($op === 'IS NOT NULL') { $whereClauses[] = "(`$col` IS NOT NULL AND `$col` != '')"; continue; }

            if ($val !== '') {
                switch ($op) {
                    case '=':      $whereClauses[] = "`$col` = ?"; $queryParams[] = $val; break;
                    case '!=':     $whereClauses[] = "`$col` != ?"; $queryParams[] = $val; break;
                    case '>':      $whereClauses[] = "`$col` > ?"; $queryParams[] = $val; break;
                    case '<':      $whereClauses[] = "`$col` < ?"; $queryParams[] = $val; break;
                    case '>=':     $whereClauses[] = "`$col` >= ?"; $queryParams[] = $val; break;
                    case '<=':     $whereClauses[] = "`$col` <= ?"; $queryParams[] = $val; break;
                    case 'LIKE':   $whereClauses[] = "`$col` LIKE ?"; $queryParams[] = "%$val%"; break;
                    case 'NOT LIKE': $whereClauses[] = "`$col` NOT LIKE ?"; $queryParams[] = "%$val%"; break;
                    case 'STARTS': $whereClauses[] = "`$col` LIKE ?"; $queryParams[] = "$val%"; break;
                    case 'ENDS':   $whereClauses[] = "`$col` LIKE ?"; $queryParams[] = "%$val"; break;
                    case 'REGEXP': $whereClauses[] = "`$col` REGEXP ?"; $queryParams[] = $val; break;
                    case 'NOT REGEXP': $whereClauses[] = "`$col` NOT REGEXP ?"; $queryParams[] = $val; break;
                    case 'FIND_IN_SET': $whereClauses[] = "FIND_IN_SET(?, `$col`)"; $queryParams[] = $val; break;
                    case 'IN':
                        $vals = array_map('trim', explode(',', $val));
                        $placeholders = implode(',', array_fill(0, count($vals), '?'));
                        $whereClauses[] = "`$col` IN ($placeholders)";
                        foreach ($vals as $v) $queryParams[] = $v;
                        break;
                    case 'NOT IN':
                        $vals = array_map('trim', explode(',', $val));
                        $placeholders = implode(',', array_fill(0, count($vals), '?'));
                        $whereClauses[] = "`$col` NOT IN ($placeholders)";
                        foreach ($vals as $v) $queryParams[] = $v;
                        break;
                    case 'SQL': // Óvatosan használandó! [cite: 2026-02-18]
                        $whereClauses[] = "(`$col` $val)"; break;
                }
            }
        }
    }
}

// A logikai kapcsolat (AND/OR) alkalmazása [cite: 2026-02-18]
if (!empty($whereClauses)) {
    $whereSQL = "WHERE (" . implode(" $logic ", $whereClauses) . ")";
}

if (!$isStructured) {
    $whereSQL = empty($whereSQL) ? "WHERE id > 1" : $whereSQL . " AND id > 1";
}
// --- ADATOK LEKÉRDEZÉSE ---
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM `$selected_table` $whereSQL");
$stmtCount->execute($queryParams);
$matchCount = (int)$stmtCount->fetchColumn();

$totalRows = $matchCount; 
$totalPages = ceil($totalRows / $limit);
if ($totalPages < 1) $totalPages = 1;

$limitSQL = ($limit_param !== 'all' && $limit_param !== 'count') ? "LIMIT $limit OFFSET $startOffset" : "";
$stmtData = $pdo->prepare("SELECT * FROM `$selected_table` $whereSQL $limitSQL");
$stmtData->execute($queryParams);
$pagedData = $stmtData->fetchAll(PDO::FETCH_ASSOC);

// 4. Lapozás véglegesítése [cite: 2026-02-18]
$totalRows = $matchCount; 
$totalPages = ceil($totalRows / $limit);
if ($totalPages < 1) $totalPages = 1;

// Szigorúan tilos: $pagedData = $allData; (ez felülírná a jó adatokat) [cite: 2026-02-18]
?>
  
<title>Tábla</title> <link rel="icon" type="image/png" href="/M-VA-.png">
<link rel="stylesheet" href="stilus.css">
   <?php include_once 'upload/modal.php'; ?>
<link rel="stylesheet" href="upload/style.css">
<form method="POST" action="tabla1.php" id="mainForm">
    <fieldset class="adminer-search-box">
        <legend>Részletes Keresés (Dinamikus)</legend>
        
        <div class="search-logic-row">
            Kapcsolat: 
            <label><input type="radio" name="s_logic" value="AND" <?= $logic === 'AND' ? 'checked' : '' ?>> ÉS (Mind)</label>
            <label><input type="radio" name="s_logic" value="OR" <?= $logic === 'OR' ? 'checked' : '' ?>> VAGY (Bármelyik)</label>
        </div>

        <div id="search-rows-container">
            <?php 
            $rowCount = isset($_REQUEST['s_col']) ? count($_REQUEST['s_col']) : 1;
            for ($i = 0; $i < $rowCount; $i++): 
            ?>
            <div class="search-row">
                <select name="s_col[]">
                    <option value="">(bárhol)</option>
                    <?php foreach ($headers as $h): ?>
                        <option value="<?= $h ?>" <?= (isset($_REQUEST['s_col'][$i]) && $_REQUEST['s_col'][$i] == $h) ? 'selected' : '' ?>><?= $h ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="s_op[]">
                    <option value="=" <?= (isset($_REQUEST['s_op'][$i]) && $_REQUEST['s_op'][$i] == '=') ? 'selected' : '' ?>>=</option>
                    <option value="<" <?= (isset($_REQUEST['s_op'][$i]) && $_REQUEST['s_op'][$i] == '<') ? 'selected' : '' ?>><</option>
                    <option value=">" <?= (isset($_REQUEST['s_op'][$i]) && $_REQUEST['s_op'][$i] == '>') ? 'selected' : '' ?>>></option>
                    <option value="<=" <?= (isset($_REQUEST['s_op'][$i]) && $_REQUEST['s_op'][$i] == '<=') ? 'selected' : '' ?>><=</option>
                    <option value=">=" <?= (isset($_REQUEST['s_op'][$i]) && $_REQUEST['s_op'][$i] == '>=') ? 'selected' : '' ?>>>=</option>
                    <option value="!=" <?= (isset($_REQUEST['s_op'][$i]) && $_REQUEST['s_op'][$i] == '!=') ? 'selected' : '' ?>>!=</option>
                    <option value="LIKE" <?= (isset($_REQUEST['s_op'][$i]) && $_REQUEST['s_op'][$i] == 'LIKE') ? 'selected' : '' ?>>LIKE</option>
                    <option value="LIKE %" <?= (isset($_REQUEST['s_op'][$i]) && $_REQUEST['s_op'][$i] == 'LIKE %') ? 'selected' : '' ?>>LIKE %%</option>
                    <option value="REGEXP" <?= (isset($_REQUEST['s_op'][$i]) && $_REQUEST['s_op'][$i] == 'REGEXP') ? 'selected' : '' ?>>REGEXP</option>
                    <option value="IN" <?= (isset($_REQUEST['s_op'][$i]) && $_REQUEST['s_op'][$i] == 'IN') ? 'selected' : '' ?>>IN</option>
                    <option value="FIND_IN_SET" <?= (isset($_REQUEST['s_op'][$i]) && $_REQUEST['s_op'][$i] == 'FIND_IN_SET') ? 'selected' : '' ?>>FIND_IN_SET</option>
                    <option value="IS NULL" <?= (isset($_REQUEST['s_op'][$i]) && $_REQUEST['s_op'][$i] == 'IS NULL') ? 'selected' : '' ?>>IS NULL</option>
                    <option value="NOT LIKE" <?= (isset($_REQUEST['s_op'][$i]) && $_REQUEST['s_op'][$i] == 'NOT LIKE') ? 'selected' : '' ?>>NOT LIKE</option>
                    <option value="NOT REGEXP" <?= (isset($_REQUEST['s_op'][$i]) && $_REQUEST['s_op'][$i] == 'NOT REGEXP') ? 'selected' : '' ?>>NOT REGEXP</option>
                    <option value="NOT IN" <?= (isset($_REQUEST['s_op'][$i]) && $_REQUEST['s_op'][$i] == 'NOT IN') ? 'selected' : '' ?>>NOT IN</option>
                    <option value="IS NOT NULL" <?= (isset($_REQUEST['s_op'][$i]) && $_REQUEST['s_op'][$i] == 'IS NOT NULL') ? 'selected' : '' ?>>IS NOT NULL</option>
                    <option value="SQL" <?= (isset($_REQUEST['s_op'][$i]) && $_REQUEST['s_op'][$i] == 'SQL') ? 'selected' : '' ?>>SQL</option>
                </select>
                <input type="text" name="s_val[]" value="<?= htmlspecialchars($_REQUEST['s_val'][$i] ?? '') ?>">
                <button type="button" class="btn-remove" onclick="this.parentElement.remove()">X</button>
            </div>
            <?php endfor; ?>
        </div>

        <div class="search-actions">
            <button type="button" class="btn-add-row" onclick="addSearchRow()">+ Új feltétel</button>
            <button type="submit" class="btn-ok">Szűrés alkalmazása</button>
            <button type="button" class="btn-clear" onclick="window.location.href='tabla1.php'">Minden szűrő törlése</button>
        </div>
    </fieldset>

    <div class="top-controls">
        <div class="control-group">
            <label>Aktuális tábla: 
                <select name="selected_table" onchange="this.form.submit()" class="selected-table-select">
                    <?php foreach ($availableTables as $tName): ?>
                        <option value="<?= $tName ?>" <?= $selected_table === $tName ? 'selected' : '' ?>><?= $tName ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="control-group">
            <label>Megjelenítés: 
                <select name="limit" onchange="this.form.submit()">
                    <option value="5" <?= $limit_param == "5" ? 'selected' : '' ?>>5</option>
                    <option value="50" <?= $limit_param == "50" ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $limit_param == "100" ? 'selected' : '' ?>>100</option>
                    <option value="all" <?= $limit_param == "all" ? 'selected' : '' ?>>MIND</option>
                    <option value="count" <?= $limit_param == "count" ? 'selected' : '' ?>>CSAK SZÁM</option>
                </select>
            </label>
            <button type="button" class="btn-import" onclick="openUploadModal()">Importálás</button>
        </div>
    </div>

    <table border="1" class="data-table">
        <thead>
            <tr>
                <?php foreach ($headers as $headerName): ?>
                    <th><?= htmlspecialchars($headerName) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if ($limit_param === 'count'): ?>
                <tr>
                    <td colspan="<?= count($headers) ?>" class="stat-row">
                        <div class="stat-info">
                            <strong>Statisztika:</strong> 
                            Összes sor: <?= $absoluteTotal ?> | 
                            Szűrésnek megfelel: <span class="stat-highlight"><?= $totalRows ?></span>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($pagedData as $row): ?>
                    <tr>
                        <?php foreach ($headers as $headerName): ?>
                            <td><?= htmlspecialchars($row[$headerName]) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>


    <?php if ($limit_param !== 'all' && $limit_param !== 'count'): ?>
        <div class="pagination-container">
            <button type="submit" onclick="document.getElementById('current_page').value=<?= max(1, $page - 1) ?>;" <?= $page <= 1 ? 'disabled' : '' ?>>« Előző</button>
            <?php 
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            for ($i = $startPage; $i <= $endPage; $i++): ?>
                <button type="submit" name="page_btn" onclick="document.getElementById('current_page').value=<?= $i ?>;" class="<?= $i == $page ? 'active-page' : '' ?>"><?= $i ?></button>
            <?php endfor; ?>
            <button type="submit" onclick="document.getElementById('current_page').value=<?= min($totalPages, $page + 1) ?>;" <?= $page >= $totalPages ? 'disabled' : '' ?>>Következő »</button>
            <span class="page-info">Szűrt találatok: <?= $totalRows ?></span>
        </div>
    <?php endif; ?>

    <input type="hidden" name="page" id="current_page" value="<?= $page ?>">
</form>
    
<script>
// Villámgyors opció betöltő javított értékkezeléssel [cite: 2026-02-18]
async function loadOptionsAndToggle(table, column, hash) {
    const panel = document.getElementById('drop_' + hash);
    const container = document.getElementById('items_' + hash);
    
    if (panel.style.display === 'none' && container.getAttribute('data-loaded') !== 'true') {
        try {
            const response = await fetch(`get_options.php?table=${encodeURIComponent(table)}&column=${encodeURIComponent(column)}`);
            const options = await response.json();
            
            let html = '';
            const filterKey = 'filter_' + hash;
            
            if (options.length === 0) {
                html = '<div class="loading-text">Nincs adat.</div>';
            } else {
                options.forEach(val => {
                    // Biztonságos értékkezelés: idézőjelek és speciális karakterek kódolása [cite: 2026-02-18]
                    const escapedVal = val.toString().replace(/"/g, '&quot;');
                    html += `<label class="excel-label">
                                <input type="checkbox" name="${filterKey}[]" value="${escapedVal}" checked>
                                <span>${val}</span>
                             </label>`;
                });
            }
            
            container.innerHTML = html;
            container.setAttribute('data-loaded', 'true');
        } catch (e) {
            container.innerHTML = '<div class="loading-text" style="color:red">Hiba a betöltéskor.</div>';
        }
    }
    
    if (typeof toggleFilter === 'function') {
        toggleFilter('drop_' + hash);
    }
}

// Villámgyors kereső a szűrőpanelen belül [cite: 2026-02-18]
function filterOptions(input, hash) {
    const filter = input.value.toLowerCase();
    const container = document.getElementById('items_' + hash);
    const labels = Array.from(container.getElementsByClassName('excel-label'));
    
    container.style.display = 'none'; 

    labels.forEach(label => {
        const text = (label.textContent || label.innerText).toLowerCase().trim();
        
        if (filter === "") {
            label.style.display = "";
            label.style.order = "0"; // Alapértelmezett sorrend
        } else if (text === filter) {
            // PONTOS EGYEZÉS: Előre soroljuk és kiemeljük [cite: 2026-03-05]
            label.style.display = "";
            label.style.order = "-1"; 
            label.style.backgroundColor = "#fff9c4"; // Enyhe sárga kiemelés
        } else if (text.indexOf(filter) > -1) {
            // RÉSZLEGES EGYEZÉS: Megjelenik, de hátrébb [cite: 2026-03-05]
            label.style.display = "";
            label.style.order = "1";
            label.style.backgroundColor = "";
        } else {
            label.style.display = "none";
            label.style.backgroundColor = "";
        }
    });

    // A 'flex' elrendezés segít a sorrend (order) kezelésében
    container.style.display = 'flex';
    container.style.flexDirection = 'column';
}
function addSearchRow() {
    const container = document.getElementById('search-rows-container');
    const firstRow = container.querySelector('.search-row');
    const newRow = firstRow.cloneNode(true);
    
    // Mezők ürítése az új sorban [cite: 2026-02-18]
    newRow.querySelector('input').value = '';
    newRow.querySelectorAll('option').forEach(opt => opt.selected = false);
    
    container.appendChild(newRow);
}
</script>

 
<script src="szuro.js?v=<?= time() ?>"></script>
<script src="upload/script.js?v=<?= time() ?>"></script>