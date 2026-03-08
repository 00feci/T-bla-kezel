// Villámgyors opció betöltő - Javított láthatóság ellenőrzéssel
async function loadOptionsAndToggle(table, column, hash) {
    const panel = document.getElementById('drop_' + hash);
    const container = document.getElementById('items_' + hash);
    
    // ComputedStyle használata, hogy külső CSS-nél is működjön
    const isHidden = window.getComputedStyle(panel).display === 'none';
    
    if (isHidden && container.getAttribute('data-loaded') !== 'true') {
        try {
            const response = await fetch(`get_options.php?table=${encodeURIComponent(table)}&column=${encodeURIComponent(column)}`);
            const options = await response.json();
            
            let html = '';
            const filterKey = 'filter_' + hash;
            
            if (!options || options.length === 0) {
                html = '<div class="loading-text">Nincs adat.</div>';
            } else {
                options.forEach(val => {
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
    
    // Panel megjelenítése/elrejtése (Ha nincs toggleFilter, sima váltás)
    if (typeof toggleFilter === 'function') {
        toggleFilter('drop_' + hash);
    } else {
        panel.style.display = isHidden ? 'block' : 'none';
    }
}

// Keresősor hozzáadása - Biztonságosabb klónozás
function addSearchRow() {
    const container = document.getElementById('search-rows-container');
    const firstRow = container.querySelector('.search-row');
    if (!firstRow) return;

    const newRow = firstRow.cloneNode(true);
    
    // Minden input ürítése
    newRow.querySelectorAll('input').forEach(input => input.value = '');
    // Minden select alaphelyzetbe
    newRow.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
    
    container.appendChild(newRow);
}
src="szuro.js?v=<?= time() ?>"
src="upload/script.js?v=<?= time() ?>"