<div id="uploadModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <h3>Új adatok importálása (UTF8 ; elválasztás.csv)</h3>
        <hr>
        <div class="modal-body">
            <div class="input-group">
                <label for="new_table_name">Új tábla neve:</label>
                <input type="text" id="new_table_name" placeholder="pl: eladasok_2026_03">
            </div>

            <div class="input-group file-input-wrapper">
                <button type="button" class="btn-secondary" onclick="document.getElementById('csv_file').click()">Fájl csatolása</button>
                <input type="file" id="csv_file" style="display: none;" onchange="updateFileName(this)">
                <span id="file_name_display" class="file-name-text">Nincs kiválasztott fájl</span>
            </div>

            <div id="progress_container" style="display: none;">
                <div class="progress-text" id="progress_status">Feldolgozás alatt...</div>
                <div class="progress-bar-bg">
                    <div id="progress_bar_fill" class="progress_bar_fill"></div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" id="btn_upload_start" class="btn-upload" onclick="startImportProcess()">Feltöltés</button>
            <button type="button" class="btn-cancel" onclick="closeUploadModal()">Bezárás</button>
        </div>
    </div>
</div>