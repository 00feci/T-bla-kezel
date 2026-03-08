<div id="exportModal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="width: 500px;">
        <h3>Adatok Exportálása (XLSX)</h3>
        <hr>
        <div class="modal-body">
            <p style="color: #333; font-size: 14px;">
                <strong>Feltétel:</strong> Jelenleg a <strong>szűrésnek megfelelő</strong> adatok kerülnek exportálásra (Törlés nélkül!).
            </p>
            
            <div style="margin-top: 15px;">
                <label style="display: block; margin-bottom: 8px;">
                    <input type="radio" name="export_type" value="all" checked onchange="toggleExportCols(this.value)"> 
                    <strong>Minden oszlop exportálása</strong>
                </label>
                <label style="display: block;">
                    <input type="radio" name="export_type" value="custom" onchange="toggleExportCols(this.value)"> 
                    <strong>Egyéni oszlopok és sorrend meghatározása</strong>
                </label>
            </div>

            <div id="export_custom_cols" style="display:none; margin-top: 15px; background: #f9f9f9; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                <p style="font-size: 13px; margin-top: 0; margin-bottom: 10px; font-weight: bold;">Állítsd be az exportálandó oszlopokat és a sorrendet:</p>
                
                <div id="export-rows-container">
                    <div class="export-row" style="display:flex; gap:5px; margin-bottom:8px; align-items: center;">
                        <span onclick="moveExportUp(this)" style="cursor:pointer; user-select:none; color: #555;">▲</span>
                        <span onclick="moveExportDown(this)" style="cursor:pointer; user-select:none; color: #555;">▼</span>
                       <div class="export-special-col-container" style="display:inline-block; position:relative; flex-grow: 1;">
                            <input type="text" class="export-col-search-input" placeholder="Oszlop keresése..." 
                                   value="<?= htmlspecialchars($headers[0] ?? '') ?>" 
                                   oninput="filterExportColList(this)" onclick="showExportColList(this)" autocomplete="off" 
                                   style="width:100%; padding: 5px; box-sizing: border-box; border: 1px solid #aaa;">
                            <input type="hidden" name="export_col[]" class="export-real-col-value" value="<?= htmlspecialchars($headers[0] ?? '') ?>">                    
                            <div class="export-custom-col-list" style="display:none; border:1px solid #ccc; background:white; max-height:150px; overflow-y:auto; position:absolute; z-index:1000; width:100%; text-align: left;">
                                <?php foreach ($headers as $fejlec): ?>
                                    <div onclick="selectExportCol(this, '<?= htmlspecialchars($fejlec) ?>')" style="cursor:pointer; padding:5px; border-bottom:1px solid #eee;">
                                        <?= htmlspecialchars($fejlec) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        </select>
                        <span onclick="removeExportRow(this)" style="color:red; cursor:pointer; font-weight:bold; padding: 0 5px;" title="Törlés">X</span>
                    </div>
                </div>
                
                <button type="button" onclick="addExportRow()" style="margin-top: 5px; font-size: 12px; padding: 6px 10px; background: #2196F3; color: white; border: none; border-radius: 3px; cursor: pointer;">
                    + Új oszlop hozzáadása
                </button>
            </div>
        </div>
        
        <div class="modal-footer" style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" class="btn-upload" onclick="startExportProcess()" style="background-color: #28a745; color: white; border: none; padding: 8px 15px; cursor: pointer; border-radius: 3px; font-weight:bold;">
                Exportálás Indítása
            </button>
            <button type="button" class="btn-cancel" onclick="closeExportModal()" style="background: #6c757d; color: white; border: none; padding: 8px 15px; cursor: pointer; border-radius: 3px;">
                Mégse
            </button>
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
    
    function toggleExportCols(val) {
        document.getElementById('export_custom_cols').style.display = (val === 'custom') ? 'block' : 'none';
    }

    function addExportRow() {
        const container = document.getElementById('export-rows-container');
        const firstRow = container.querySelector('.export-row');
        if (firstRow) {
            const newRow = firstRow.cloneNode(true);
            newRow.querySelector('select').selectedIndex = 0; // Visszaáll az első elemre
            container.appendChild(newRow);
        }
    }

    function removeExportRow(btn) {
        const container = document.getElementById('export-rows-container');
        if (container.children.length > 1) {
            btn.closest('.export-row').remove();
        } else {
            alert("Legalább egy oszlopnak maradnia kell!");
        }
    }

    function moveExportUp(btn) {
        const row = btn.closest('.export-row');
        const prev = row.previousElementSibling;
        if (prev) row.parentNode.insertBefore(row, prev);
    }

    function moveExportDown(btn) {
        const row = btn.closest('.export-row');
        const next = row.nextElementSibling;
        if (next) row.parentNode.insertBefore(next, row);
    }

    function startExportProcess() {
        const form = document.getElementById('mainForm');
        
        // Elmentjük az eredeti célt
        const originalAction = form.action;
        
        // Átirányítjuk az exportáló PHP-ra és új fülön nyitjuk meg
        form.action = 'export/export_universal.php';
        form.target = '_blank';
        form.submit();
        
        // Kis késleltetéssel visszaállítjuk az eredeti működést, és bezárjuk a popupot
        setTimeout(() => {
            form.action = originalAction;
            form.target = '';
            closeExportModal();
        }, 1000);
    }
</script>

