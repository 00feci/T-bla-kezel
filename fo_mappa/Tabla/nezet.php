
 <link rel="stylesheet" href="upload/style.css">
<?php
// Tabla / nezet.php [cite: 2026-03-05]
?>
<div class="tabla-kontener">
    <table class="data-table">
        <thead>
            <tr>
                <?php foreach ($headers as $fejlec): ?>
                    <th class="relative-th">
                        <?= htmlspecialchars($fejlec) ?>
                        <span class="excel-nyil" onclick="loadOptionsAndToggle('<?= $selected_table ?>', '<?= $fejlec ?>', '<?= md5($fejlec) ?>')">▼</span>
                        
                        <div id="drop_<?= md5($fejlec) ?>" class="excel-panel" style="display:none;">
                            <div class="excel-panel-search-wrapper" style="background:#eee; padding:5px;">
                                <input type="text" class="excel-search-input" placeholder="Szűrés..." onkeyup="filterOptions(this, '<?= md5($fejlec) ?>')" style="width:90%;">
                            </div>
                            <div id="items_<?= md5($fejlec) ?>" class="excel-panel-items" data-loaded="false" style="max-height:200px; overflow-y:auto; padding:5px;">
                                <div>Betöltés...</div>
                            </div>
                            <div class="excel-panel-buttons" style="display:flex; gap:5px; padding:5px; background:#f0f0f0;">
                                <button type="submit" style="flex:1; background:#00897b; color:white; border:none; padding:5px; cursor:pointer;">OK</button>
                                <button type="button" onclick="this.parentElement.parentElement.style.display='none'" style="flex:1; background:#607d8b; color:white; border:none; padding:5px; cursor:pointer;">Mégse</button>
                            </div>
                        </div>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pagedData as $row): ?>
                <tr>
                    <?php foreach ($headers as $fejlec): ?>
                        <td><?= htmlspecialchars($row[$fejlec] ?? '') ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

    <?php 
    if ($limit_param !== 'all' && $limit_param !== 'count') {
        include 'lapozas.php';
    }
    ?>
</div>