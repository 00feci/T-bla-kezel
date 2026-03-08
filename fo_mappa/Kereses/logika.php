<?php
// Kereses / logika.php [cite: 2026-02-18]
// A tábla nevének meghatározása több forrásból [cite: 2026-03-06]
// Megpróbáljuk kérésből, ha nincs, akkor a session-ből előbányászni
$tableName = $_REQUEST['selected_table'] ?? $_SESSION['last_selected_table'] ?? $selected_table ?? '';

if (!empty($tableName)) {
    $_SESSION['last_selected_table'] = $tableName; // Elmentjük, hogy legközelebb is tudjuk
}

if (empty($tableName)) {
    // Ha tényleg nincs semmi, csak akkor állunk le, de egy barátságosabb üzenettel
    echo "Válassz egy táblát a folytatáshoz!";
    return; // die() helyett return, hogy a nezet.php váza betöltődhessen [cite: 2026-03-06]
}

$whereClauses = [];
$queryParams = [];
$whereSQL = "";

// 1. LIMIT MEGHATÁROZÁSA
if (isset($_REQUEST['limit'])) {

    // jött új nézet (pl 5,10,50,all,count)
    $limit_param = $_REQUEST['limit'];
    $_SESSION['last_limit'] = $limit_param;

} elseif (isset($_SESSION['last_limit'])) {

    // ha nincs a kérésben, a sessionből vesszük
    $limit_param = $_SESSION['last_limit'];

} else {

    // alapértelmezett
    $limit_param = 5;
}
// 2. LAPOZÁS SZÁMÍTÁSA
$page = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 1;
if ($page < 1) $page = 1;
$page = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 1;
if ($page < 1) $page = 1;

$limit_int = is_numeric($limit_param) ? (int)$limit_param : 0;
$offset = ($page - 1) * $limit_int;
$offset = ($page - 1) * $limit_int;

$groupedConditions = [];
if (isset($_REQUEST['s_col']) && is_array($_REQUEST['s_col'])) {
    // EZ A SOR HIÁNYZOTT:
    foreach ($_REQUEST['s_col'] as $i => $col) {
        $groupId = $_REQUEST['s_group'][$i] ?? 0;
        $op = $_REQUEST['s_op'][$i] ?? '=';
        $val = trim($_REQUEST['s_val'][$i] ?? '');
        $logic = $_REQUEST['s_logic'][$i] ?? 'AND';

        $searchIn = ($col === '' || $col === 'all') ? $headers : [$col];
        $rowParts = [];

        if ($op === 'IS NULL' || $op === 'IS NOT NULL') {
            foreach ($searchIn as $c) {
                $cleanC = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace('-egyedi', '', $c));
                $rowParts[] = ($op === 'IS NULL') ? "(`$cleanC` IS NULL OR `$cleanC` = '')" : "(`$cleanC` IS NOT NULL AND `$cleanC` != '')";
            }
        } 
        elseif ($val !== '') {
            foreach ($searchIn as $c) {
                // Szőrszálhasogató tisztítás [cite: 2026-03-07]
                $cleanC = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace('-egyedi', '', $c));
                if ($cleanC === 'id') continue;
                switch ($op) {
                        case '=':           $rowParts[] = "`$c` = ?"; $queryParams[] = $val; break;
                        case '!=':          $rowParts[] = "`$c` != ?"; $queryParams[] = $val; break;
                        case '<':           $rowParts[] = "`$c` < ?"; $queryParams[] = $val; break;
                        case '>':           $rowParts[] = "`$c` > ?"; $queryParams[] = $val; break;
                        case '<=':          $rowParts[] = "`$c` <= ?"; $queryParams[] = $val; break;
                        case '>=':          $rowParts[] = "`$c` >= ?"; $queryParams[] = $val; break;
                        case 'LIKE':        $rowParts[] = "`$c` LIKE ?"; $queryParams[] = $val . "%"; break;
                        case 'LIKE_ANY':    $rowParts[] = "`$c` LIKE ?"; $queryParams[] = "%" . $val . "%"; break;
                        case 'NOT LIKE':    $rowParts[] = "`$c` NOT LIKE ?"; $queryParams[] = "%" . $val . "%"; break;
                        case 'REGEXP':      $rowParts[] = "`$c` REGEXP ?"; $queryParams[] = $val; break;
                        case 'NOT REGEXP':  $rowParts[] = "`$c` NOT REGEXP ?"; $queryParams[] = $val; break;
                        case 'WORD_SEARCH': $rowParts[] = "`$c` REGEXP ?"; $queryParams[] = "[[:<:]]" . $val . "[[:>:]]"; break;
                        case 'IN':
                        case 'NOT IN':
            $vals = array_map('trim', explode(',', $val));
            $placeholders = implode(',', array_fill(0, count($vals), '?'));
            $rowParts[] = "`$cleanC` " . ($op === 'NOT IN' ? 'NOT IN' : 'IN') . " ($placeholders)";
            foreach ($vals as $v) $queryParams[] = $v;
            break;
            default:
            $rowParts[] = "`$cleanC` = ?"; $queryParams[] = $val; break;
                }
            }
        }

     if (!empty($rowParts)) {
            $currentCondition = "(" . implode(" OR ", $rowParts) . ")";
            $groupedConditions[$groupId][] = [
                'sql' => $currentCondition,
                'logic' => $logic
            ];
        }
    } // Foreach lezárása

    foreach ($groupedConditions as $gid => $rows) {
        $parts = [];
        foreach ($rows as $idx => $r) {
            $parts[] = $r['sql'] . ($idx < count($rows) - 1 ? " " . $r['logic'] : "");
        }
        $innerContent = implode(" ", $parts);
        $whereClauses[] = ($gid == 0) ? $innerContent : "(" . $innerContent . ")";
    }
} // If (isset s_col) lezárása


// 4. Végleges WHERE felépítése (A csoportok közé alapértelmezett AND-et teszünk) [cite: 2026-03-07]
if (!empty($whereClauses)) {
    $whereSQL = " WHERE " . implode(" AND ", $whereClauses);
}

// 5. Nyers import tábla védelme
if (isset($isStructured) && !$isStructured) {
    $whereSQL = empty($whereSQL) ? " WHERE id > 1" : "$whereSQL AND id > 1";
}

// 1. Számláló lekérdezése
$countSql = "SELECT COUNT(*) FROM `$tableName` $whereSQL";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($queryParams);
$matchCount = $stmtCount->fetchColumn(); 

$totalRows = $matchCount; 
$totalPages = ceil($totalRows / ($limit_int ?: 1));
if ($totalPages < 1) $totalPages = 1;

if ($limit_param === 'count') {

    // CSAK SZÁM → nem kérünk le adatokat
    $pagedData = [];

} else {

    $limitSQL = $limit_int > 0 ? "LIMIT $limit_int OFFSET $offset" : "";
    $sql = "SELECT * FROM `$tableName` $whereSQL $limitSQL";

    $stmtData = $pdo->prepare($sql);
    $stmtData->execute($queryParams);

    $pagedData = $stmtData->fetchAll(PDO::FETCH_ASSOC);
}