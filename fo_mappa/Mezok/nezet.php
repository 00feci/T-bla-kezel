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




