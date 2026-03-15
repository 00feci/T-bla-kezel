<?php
// Mezok / nezet.php [cite: 2026-03-05]
?>
<div class="vezerlo-kontener"> <div class="vezerlo-csoport bal-oldal">



<label for="selected_table">Aktuális tábla:</label>
<select name="selected_table" id="selected_table" onchange="this.form.submit()" class="tabla-valaszto">
    <?php if (empty($selected_table)): ?>
        <option value="" selected disabled>-- Válassz táblát --</option>
    <?php endif; ?>

    <?php foreach ($availableTables as $tName): ?>
        <?php 
        // Szőrszálhasogató szűrés: a technikai táblákat elrejtjük [cite: 2026-03-06]
        if ($tName === 'm_va_ssd_raw' || $tName === 'raw_import_data' || $tName === 'm_va_import_status') continue; 
        ?>
        <option value="<?= $tName ?>" <?= ($selected_table === $tName) ? 'selected' : '' ?>>
            <?= htmlspecialchars($tName) ?>
        </option>
    <?php endforeach; ?>
</select>
    <?php if ($selected_table !== 'raw_import_data'): ?>
    <button type="submit" name="action" value="delete_table" class="btn-remove" 
            onclick="return confirm('Biztos törölni szeretné a \'<?= htmlspecialchars($selected_table) ?>\' táblát?')">
        Törlés
    </button>
    <input type="hidden" name="table_to_delete" value="<?= htmlspecialchars($selected_table) ?>">
<?php endif; ?>
    </div>
<?php if (isset($limit_param) && $limit_param !== 'all'): ?>
    <div class="pagination-container">
        <?php if ($limit_param === 'count'): ?>
            <span class="page-info">Teljes tábla: <?= number_format($absoluteTotal, 0, '.', ' ') ?> / Szűrt találatok: <?= number_format($totalRows, 0, '.', ' ') ?></span>
        <?php else: ?>
            <input type="hidden" name="page" id="current_page" value="<?= $page ?>">
            <button type="submit" onclick="document.getElementById('current_page').value=<?= max(1, $page - 1) ?>;" <?= $page <= 1 ? 'disabled' : '' ?>>« Előző</button>
            <?php 
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            for ($i = $startPage; $i <= $endPage; $i++): ?>
                <button type="submit" name="page_btn" onclick="document.getElementById('current_page').value=<?= $i ?>;" class="<?= $i == $page ? 'active-page' : '' ?>"><?= $i ?></button>
            <?php endfor; ?>
            <button type="submit" onclick="document.getElementById('current_page').value=<?= min($totalPages, $page + 1) ?>;" <?= $page >= $totalPages ? 'disabled' : '' ?>>Következő »</button>
            <span class="page-info">Szűrt találatok: <?= number_format($totalRows, 0, '.', ' ') ?></span>
        <?php endif; ?>
    </div>
<?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>



<?php if (!empty($selected_table) && $selected_table !== 'raw_import_data'): ?>
<?php
// Oszlop típusok lekérdezése a JS számára
$colTypesMap = [];
try {
    $stmtT = $pdo->query("DESCRIBE `$selected_table`");
    while ($rT = $stmtT->fetch(PDO::FETCH_ASSOC)) {
        $colTypesMap[$rT['Field']] = $rT['Type'];
    }
} catch(Exception $e) {}
?>
<script>
    window.columnTypesMap = <?= json_encode($colTypesMap) ?>;
</script>

<div class="vezerlo-csoport kozep-oldal" style="display:flex; align-items:center; gap:8px; background: #eef2f7; padding: 5px 10px; border-radius: 4px; border: 1px solid #cdd6e1;">
    <label style="font-size: 13px; font-weight: bold; color: #333;">Oszlop típus:</label>
    
    <div class="type-special-col-container" style="display:inline-block; position:relative; min-width: 180px;">
        <input type="text" class="type-col-search-input" placeholder="Válassz oszlopot..." 
               value="" 
               oninput="filterTypeColList(this)" onclick="showTypeColList(this)" autocomplete="off" 
               style="width:100%; padding: 5px; box-sizing: border-box; border: 1px solid #aaa; font-size: 13px;">
        <input type="hidden" id="type_col_select" class="type-real-col-value" value="">                    
        <div class="type-custom-col-list" style="display:none; border:1px solid #ccc; background:white; max-height:150px; overflow-y:auto; position:absolute; z-index:1000; width:100%; text-align: left; box-shadow: 0px 4px 6px rgba(0,0,0,0.1);">
            <?php foreach ($headers as $fejlec): ?>
                <div onclick="selectTypeCol(this, '<?= htmlspecialchars($fejlec) ?>')" style="cursor:pointer; padding:5px; border-bottom:1px solid #eee; font-size: 13px;">
                    <?= htmlspecialchars($fejlec) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <select id="type_val_select" class="tabla-valaszto" style="font-size: 13px;">
        <option value="longtext">szöveg</option>
        <option value="int(11)">szám</option>
    </select>
    <button type="button" onclick="changeColumnType()" style="background:#2196F3; color:white; border:none; padding:5px 10px; cursor:pointer; border-radius:3px; font-weight:bold; font-size: 13px;">Mentés</button>
    <span id="type_save_success" style="color:#4CAF50; font-weight:bold; font-size:16px; display:none; margin-left:5px;">✔</span>
</div>

<script>
function showTypeColList(input) {
    const list = input.parentElement.querySelector('.type-custom-col-list');
    if (list) list.style.display = 'block';
}

function filterTypeColList(input) {
    const filter = input.value.toLowerCase();
    const list = input.parentElement.querySelector('.type-custom-col-list');
    const options = list.querySelectorAll('div');
    list.style.display = 'block';
    options.forEach(opt => {
        const text = opt.innerText.toLowerCase();
        opt.style.display = text.includes(filter) ? 'block' : 'none';
    });
}

function selectTypeCol(element, value) {
    const container = element.closest('.type-special-col-container');
    container.querySelector('.type-col-search-input').value = element.innerText;
    container.querySelector('.type-real-col-value').value = value;
    container.querySelector('.type-custom-col-list').style.display = 'none';

    // Tipus olvasása és beállítása [cite: 2026-03-05]
    const typeSelect = document.getElementById('type_val_select');
    const colType = window.columnTypesMap[value] || '';
    
    if (colType.toLowerCase().includes('int')) {
        typeSelect.value = 'int(11)';
    } else {
        typeSelect.value = 'longtext';
    }
}

// Kattintás kívülre bezárja a lenyíló listát
document.addEventListener('click', function(e) {
    if (!e.target.closest('.type-special-col-container')) {
        document.querySelectorAll('.type-custom-col-list').forEach(list => {
            list.style.display = 'none';
        });
    }
});

async function changeColumnType() {
    const col = document.getElementById('type_col_select').value;
    const type = document.getElementById('type_val_select').value;
    const table = document.querySelector('select[name="selected_table"]').value;

    if(!col || !table) {
        alert("Kérlek válassz ki egy oszlopot először!");
        return;
    }

    const formData = new FormData();
    formData.append('action', 'change_column_type');
    formData.append('table', table);
    formData.append('column', col);
    formData.append('type', type);

    try {
        const res = await fetch('tabla1.php', { method: 'POST', body: formData });
        if (res.ok) {
            // JS térkép frissítése, hogy többszöri módosításnál is jó maradjon az adat
            window.columnTypesMap[col] = type;

            const checkmark = document.getElementById('type_save_success');
            checkmark.style.display = 'inline';
            setTimeout(() => { checkmark.style.display = 'none'; }, 3000);
        } else {
            const errorText = await res.text();
            alert('Hiba történt a módosítás során:\n' + errorText);
        }
    } catch (e) {
        alert('Hálózati hiba.');
    }
}
</script>
<?php endif; ?>


    
    <div class="vezerlo-csoport jobb-oldal">
        <label for="limit">Megjelenítés:</label>
        <select name="limit" id="limit" onchange="this.form.submit()" class="limit-valaszto">
            <option value="5" <?= $limit_param == "5" ? 'selected' : '' ?>>5</option>
            <option value="50" <?= $limit_param == "50" ? 'selected' : '' ?>>50</option>
            <option value="100" <?= $limit_param == "100" ? 'selected' : '' ?>>100</option>
            <option value="all" <?= $limit_param == "all" ? 'selected' : '' ?>>MIND</option>
<option value="count" <?= $limit_param == "count" ? 'selected' : '' ?>>CSAK SZÁM</option>
</select>
<button type="button" class="gomb-export" onclick="openExportModal()" style="background-color: #28a745; color: white; border: none; padding: 6px 12px; margin-right: 10px; cursor: pointer; border-radius: 3px; font-weight: bold;">Exportálás</button>
<button type="button" class="gomb-import" onclick="openUploadModal()">Importálás</button>
</div>
</div>

<?php if (empty($selected_table)): ?>
    <div style="padding: 20px; background: #fff3cd; border: 1px solid #ffeeba; border-radius: 4px;">
        Kérjük, válasszon egy új táblát a listából a folytatáshoz.
    </div>
<?php else: ?>

    <?php endif; ?>




