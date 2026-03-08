<div id="exportModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <h3>Adatok Exportálása (XLSX)</h3>
        <hr>
        <div class="modal-body">
            <p>Mely adatokat szeretnéd exportálni a(z) <strong><?= htmlspecialchars($selected_table ?? '') ?></strong> táblából?</p>
            
            <div class="input-group" style="margin-top: 15px;">
                <label><input type="radio" name="export_scope" value="filtered_all" checked> Összes szűrt találat (<?= number_format($totalRows ?? 0, 0, '', ' ') ?> sor)</label><br>
                <label><input type="radio" name="export_scope" value="current_page"> Csak az aktuális oldal (<?= count($pagedData ?? []) ?> sor)</label><br>
                <label><input type="radio" name="export_scope" value="full_table"> Teljes tábla szűrők nélkül (<?= number_format($absoluteTotal ?? 0, 0, '', ' ') ?> sor)</label>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-upload" onclick="startExportProcess()" style="background-color: #28a745;">Exportálás Indítása</button>
            <button type="button" class="btn-cancel" onclick="closeExportModal()">Mégse</button>
        </div>
    </div>
</div>

<script>
function openExportModal() { 
    document.getElementById('exportModal').style.display = 'flex'; 
}
function closeExportModal() { 
    document.getElementById('exportModal').style.display = 'none'; 
}
function startExportProcess() {
    // Megkeressük a fő formot, hogy a meglévő szűrőket is átadjuk az exportnak
    const form = document.getElementById('mainForm');
    const scope = document.querySelector('input[name="export_scope"]:checked').value;
    
    // Készítünk egy rejtett inputot a scope-nak, amit elküldünk PHP-ba
    let scopeInput = document.getElementById('hidden_export_scope');
    if(!scopeInput) {
        scopeInput = document.createElement('input');
        scopeInput.type = 'hidden';
        scopeInput.id = 'hidden_export_scope';
        scopeInput.name = 'export_scope';
        form.appendChild(scopeInput);
    }
    scopeInput.value = scope;

    // Az eredeti action lementése, majd form elküldése az univerzális export php-ra
    const originalAction = form.action;
    form.action = 'export/export_universal.php';
    form.target = '_blank'; // Új lapon nyissa meg a letöltést
    form.submit();

    // Visszaállítás az eredeti működésre a letöltés indítása után
    setTimeout(() => {
        form.action = originalAction;
        form.target = '';
        closeExportModal();
    }, 1000);
}
</script>