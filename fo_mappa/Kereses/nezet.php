<?php

$operators = [
'=' =>'pontosan ez',
'<' =>'kisebb mint',
'>' => 'nagyobb mint',
'<=' => 'kisebb vagy egyenlő',
'>=' => 'nagyobb vagy egyenlő',
'!=' => 'nem ez',
'LIKE' => 'ezzel kezdődik',
'LIKE_ANY' => 'tartalmazza',
'NOT LIKE' => 'egyáltalán nem tartalmazza',
'REGEXP' => 'igaz a REGEXP mintára',
'NOT REGEXP' => 'hamis a REGEXP mintára',
'IN' => 'benne van a felsorolásban (vesszővel elválasztva)',
'NOT IN' =>'nincs benne a felsorolásban',
'WORD_SEARCH' => 'pontos szókeresés (szóköz, vessző stb. nem akadály)',
'IS NULL' => 'üres (nincs kitöltve semmi)',
'IS NOT NULL' => 'nem üres (van benne adat)'//,
//'SQL'=>'SQL',
];
if (!in_array("Tesztoszlop-egyedi", $headers)) { $headers[] = "Tesztoszlop-egyedi"; }

$columnOptionsArr = [];
$useSpecialSearch = false; // Alaphelyzet [cite: 2026-03-07]
foreach ($headers as $f) { 
    if (strpos($f, '-egyedi') !== false) { $useSpecialSearch = true; }
    $columnOptionsArr[] = ['val' => str_replace('-egyedi', '', $f), 'label' => str_replace('-egyedi', '', $f)]; 
}

$operatorHtmlBase = "";
foreach ($operators as $val => $label) { $operatorHtmlBase .= "<option value=\"$val\">$label</option>"; }
?>

<script>
    window.columnData = <?= json_encode($columnOptionsArr) ?>;
    window.operatorOptions = <?= json_encode($operatorHtmlBase) ?>;
    window.logicOptions = `<option value="AND">ÉS</option><option value="OR">VAGY</option>`;
    window.useSpecialSearch = <?= json_encode($useSpecialSearch) ?>;
    <?php
    $s_group_req = $_REQUEST['s_group'] ?? [];
    $maxGroupId = empty($s_group_req) ? 0 : max((array)$s_group_req);
    ?>
    if (typeof groupCounter === 'undefined') { var groupCounter = <?= (int)$maxGroupId ?>; }
</script>

<fieldset class="adminer-search-box">
    <legend>Részletes Keresés (Dinamikus)</legend>
    <div id="dynamic-search-container">
       <?php 
        $s_col_req = $_REQUEST['s_col'] ?? ['all'];
        $s_group_req = $_REQUEST['s_group'] ?? array_fill(0, count($s_col_req), 0);
        
        // Csoportosítjuk az indexeket a HTML újraépítéshez
        $groupedRows = [];
        foreach ($s_col_req as $i => $col) {
            $gId = $s_group_req[$i] ?? 0;
            $groupedRows[$gId][] = $i;
        }

        foreach ($groupedRows as $gId => $indexes):
            if ($gId != 0): // Csoport keretének renderelése
        ?>
            <div class="search-group" data-group-id="<?= htmlspecialchars($gId) ?>" style="border: 2px dashed #bbb; background: #fdfdfd; padding: 15px; margin: 10px 0; position: relative;">
                <span style="position:absolute; top:-10px; left:10px; background:white; padding:0 5px; font-size:11px; color:#888; font-weight:bold;">CSOPORT #<?= htmlspecialchars($gId) ?></span>
                <span class="remove-row" onclick="this.parentElement.remove(); updateLogicPreview();" style="position:absolute; right:10px; top:5px; color:red; cursor:pointer; font-weight:bold;">X csoport törlése</span>
                <div class="group-rows-container">
        <?php 
            endif;
            
            foreach ($indexes as $i): 
                $currentVal = $_REQUEST['s_col'][$i] ?? 'all'; 
                $displayLabel = ($currentVal === 'all') ? '(bárhol)' : $currentVal;
                $logicVal = $_REQUEST['s_logic'][$i] ?? 'AND';
        ?>
      <div class="search-row" style="margin-bottom: 5px; display: flex; align-items: center; flex-wrap: wrap;">
    <span class="insert-row" onclick="insertSearchRow(this)" style="color:green; cursor:pointer; font-weight:bold; margin-right:10px;">(+)</span>
    <span class="remove-row" onclick="this.parentElement.remove(); updateLogicPreview();" style="color:red; cursor:pointer; font-weight:bold; margin-right:10px;">(x)</span>
    <input type="hidden" name="s_group[]" value="<?= htmlspecialchars($gId) ?>">
            <?php if ($useSpecialSearch): ?>
               <div class="special-col-container" style="display:inline-block; position:relative;">
                    <input type="text" class="col-search-input" placeholder="Oszlop..." 
                           value="<?= htmlspecialchars($displayLabel) ?>" 
                           oninput="filterCustomList(this)" onclick="showCustomList(this)" autocomplete="off">
                    <input type="hidden" name="s_col[]" class="real-col-value" value="<?= htmlspecialchars($currentVal) ?>">                    
                    <div class="custom-col-list" style="display:none; border:1px solid #ccc; background:white; max-height:150px; overflow-y:auto; position:absolute; z-index:1000; width:100%;">
                        <div onclick="selectCustomOption(this, 'all')" style="cursor:pointer; padding:2px;">(bárhol)</div>
                        <?php foreach ($headers as $fejlec): 
                            $cleanName = str_replace('-egyedi', '', $fejlec); ?>
                            <div onclick="selectCustomOption(this, '<?= htmlspecialchars($cleanName) ?>')" style="cursor:pointer; padding:2px; border-bottom:1px solid #eee;">
                                <?= htmlspecialchars($cleanName) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <select name="s_col[]" onchange="updateLogicPreview()">
                    <option value="all" <?= ($currentVal == 'all') ? 'selected' : '' ?>>(bárhol)</option>
                    <?php foreach ($headers as $fejlec): ?>
                        <option value="<?= htmlspecialchars($fejlec) ?>" <?= ($currentVal == $fejlec) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($fejlec) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <select name="s_op[]" onchange="updateLogicPreview()">
                <?php foreach ($operators as $val => $label): ?>
                    <option value="<?= $val ?>" <?= (isset($_REQUEST['s_op'][$i]) && $_REQUEST['s_op'][$i] == $val) ? 'selected' : '' ?>>
                        <?= $label ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="text" name="s_val[]" class="search-input" 
                   value="<?= htmlspecialchars($_REQUEST['s_val'][$i] ?? '') ?>" oninput="updateLogicPreview()">

           <select name="s_logic[]" class="search-logic" onchange="updateLogicPreview()">
                <option value="AND" <?= ($logicVal == 'AND') ? 'selected' : '' ?>>ÉS</option>
                <option value="OR" <?= ($logicVal == 'OR') ? 'selected' : '' ?>>VAGY</option>
            </select>

            <span class="move-row" style="margin-left: 10px; cursor: pointer; user-select: none;">
                <span onclick="moveRowUp(this)">▲</span>
                <span onclick="moveRowDown(this)">▼</span>
            </span>
        </div>
        <?php 
            endforeach;
            
            if ($gId != 0): // Csoport lezárása
        ?>
                </div>
                <div style="margin-top:10px; display: flex; align-items: center; gap: 10px;">
                    <button type="button" onclick="addRowToGroup(this)" style="font-size:12px;">+ Sor a csoporthoz</button>
                    <select name="g_logic[]" class="group-logic" onchange="updateLogicPreview()" style="font-size:12px;">
                        <option value="AND">ÉS (következő elem)</option>
                        <option value="OR">VAGY (következő elem)</option>
                    </select>
                    <span class="move-row" style="cursor: pointer; user-select: none;">
                        <span onclick="moveRowUp(this)">▲</span>
                        <span onclick="moveRowDown(this)">▼</span>
                    </span>
                </div>
            </div>
        <?php 
            endif;
        endforeach;
        ?>
    </div>
    <div id="logic-preview-box" style="background: #eef2f7; border-left: 5px solid #2196F3; padding: 10px; margin: 15px 0; font-family: monospace; font-size: 13px;">
        <strong>Logikai modell:</strong> <span id="logic-string">Válasszon feltételt...</span><br>
        <strong>Minta eredmény:</strong> <span id="sample-result" style="color: red; font-weight: bold;">-</span>
    </div>

    <div class="search-actions" style="margin-top:20px; display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
    <div class="action-pair">
        <button type="button" class="btn-add-row" onclick="addSearchRow()">+ Új feltétel</button>
        <span class="help-icon" data-tip="Egyszerű szűkítés. Akkor használd, ha egymás után akarod pontosítani a keresést. (Pl. Csak a szegedi cégeket keresed, amiknek van adószáma).">?</span>
    </div>

    <div class="action-pair">
        <button type="button" class="btn-add-group" onclick="addSearchGroup()" style="background: #607D8B; color: white;">+ Új csoport ( )</button>
        <span class="help-icon" data-tip="Választási lehetőség (Zárójelezés). Akkor használd, ha több opciót is megengednél. (Pl. Olyan cégeket keresel, amik VAGY Szegediek, VAGY Bajaiak, de mindenképp Bt formájúak).">?</span>
    </div>

    <button type="submit" class="btn-ok">Szűrés alkalmazása</button>
    <button type="button" class="btn-clear" onclick="window.location.href='?selected_table=<?= urlencode($selected_table) ?>'">Minden szűrő törlése</button>
</div>
</fieldset>
<script>
    // Szőrszálhasogató inicializálás: amint betölt az oldal, frissítjük a nézetet [cite: 2026-03-07]
    window.addEventListener('load', function() {
        if (typeof updateLogicPreview === "function") {
            updateLogicPreview();
        }
    });
</script>

<script src="Kereses/szkript.js?v=<?= time() ?>"></script>




