function toggleFilter(id) {
    const panel = document.getElementById(id);
    const isVisible = panel.style.display === 'block';
    
    // Összes többi panel bezárása
    document.querySelectorAll('.excel-panel').forEach(p => p.style.display = 'none');
    
    if (!isVisible) {
        panel.style.display = 'block';
        // Automatikus fókusz a keresőmezőre
        const searchInput = panel.querySelector('.excel-search-input');
        if (searchInput) {
            setTimeout(() => searchInput.focus(), 10);
        }
    }
}

function filterOptions(input, hash) {
    const filter = input.value.toLowerCase();
    const container = document.getElementById('items_' + hash);
    const labels = container.getElementsByClassName('excel-label');

    for (let i = 0; i < labels.length; i++) {
        const text = labels[i].textContent || labels[i].innerText;
        if (text.toLowerCase().indexOf(filter) > -1) {
            labels[i].style.display = "";
        } else {
            labels[i].style.display = "none";
        }
    }
}

function toggleAllInPanel(source, hash) {
    const container = document.getElementById('items_' + hash);
    // Csak a látható (nem szűrt) checkboxokat jelöljük ki
    const checkboxes = container.querySelectorAll('.excel-label:not([style*="display: none"]) input[type="checkbox"]');
    
    for (let i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
}

// Kattintás a panelen kívül bezárja azt
window.onclick = function(event) {
    if (!event.target.matches('.excel-nyil') && !event.target.closest('.excel-panel')) {
        document.querySelectorAll('.excel-panel').forEach(p => p.style.display = 'none');
    }
}

// Megakadályozzuk, hogy a felesleges (minden kijelölt) adatokat elküldje a szervernek [cite: 2026-02-18]
document.getElementById('mainForm').onsubmit = function() {
    const panels = document.querySelectorAll('.excel-panel-items');
    panels.forEach(panel => {
        const checkboxes = panel.querySelectorAll('input[type="checkbox"]');
        const unCheckedCount = panel.querySelectorAll('input[type="checkbox"]:not(:checked)').length;
        
        // Ha minden ki van pipálva az oszlopban, akkor ne küldjük el a checkboxokat, 
        // mert az SQL-nek ez azt jelenti: "minden kell" (vagyis nem kell WHERE feltétel) [cite: 2026-02-18]
        if (unCheckedCount === 0) {
            checkboxes.forEach(cb => cb.name = ""); 
        }
    });
};