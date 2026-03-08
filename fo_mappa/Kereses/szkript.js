if (typeof groupCounter === 'undefined') {
    var groupCounter = 0;
}

// Új csoport létrehozása [cite: 2026-03-07]
function addSearchGroup() {
    groupCounter++;
    const container = document.getElementById('dynamic-search-container');
    const groupDiv = document.createElement('div');
    groupDiv.className = 'search-group';
    groupDiv.dataset.groupId = groupCounter;
    groupDiv.style.border = "2px dashed #bbb";
    groupDiv.style.background = "#fdfdfd";
    groupDiv.style.padding = "15px";
    groupDiv.style.margin = "10px 0";
    groupDiv.style.position = "relative";
    
    groupDiv.innerHTML = `
        <span style="position:absolute; top:-10px; left:10px; background:white; padding:0 5px; font-size:11px; color:#888; font-weight:bold;">CSOPORT #${groupCounter}</span>
        <span class="remove-row" onclick="this.parentElement.remove(); updateLogicPreview();" style="position:absolute; right:10px; top:5px; color:red; cursor:pointer; font-weight:bold;">X csoport törlése</span>
        <div class="group-rows-container"></div>
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
    `;
    
    container.appendChild(groupDiv);
    addRowToGroup(groupDiv.querySelector('button'));
}

// Sor hozzáadása konkrét csoporthoz [cite: 2026-03-07]
function addRowToGroup(btn) {
    const groupDiv = btn.closest('.search-group');
    const rowsContainer = groupDiv.querySelector('.group-rows-container');
    const groupId = groupDiv.dataset.groupId;
    rowsContainer.appendChild(createRowElement(groupId));
    updateLogicPreview();
}

// Sor beszúrása [cite: 2026-03-07]
function insertSearchRow(btn) {
    const currentRow = btn.closest('.search-row');
    const groupId = currentRow.querySelector('[name="s_group[]"]').value;
    const newRow = createRowElement(groupId);
    currentRow.after(newRow);
    updateLogicPreview();
}

// Mozgatás (működik sorra és csoportra is) [cite: 2026-03-07]
function moveRowUp(btn) {
    const el = btn.closest('.search-row') || btn.closest('.search-group');
    const prev = el.previousElementSibling;
    if (prev && (prev.classList.contains('search-row') || prev.classList.contains('search-group'))) {
        el.parentNode.insertBefore(el, prev);
        updateLogicPreview();
    }
}

function moveRowDown(btn) {
    const el = btn.closest('.search-row') || btn.closest('.search-group');
    const next = el.nextElementSibling;
    if (next && (next.classList.contains('search-row') || next.classList.contains('search-group'))) {
        el.parentNode.insertBefore(next, el);
        updateLogicPreview();
    }
}

// Sor HTML generálása [cite: 2026-03-07]
function createRowElement(groupId = 0) {
    const row = document.createElement('div');
    row.className = 'search-row';
    row.style.marginBottom = '5px';
    
    let colHtml = "";
    if (window.useSpecialSearch) {
        let listItems = `<div onclick="selectCustomOption(this, 'all')" style="cursor:pointer; padding:2px;">(bárhol)</div>`;
        window.columnData.forEach(item => {
            listItems += `<div onclick="selectCustomOption(this, '${item.val}')" style="cursor:pointer; padding:2px; border-bottom:1px solid #eee;">${item.label}</div>`;
        });
        colHtml = `<div class="special-col-container" style="display:inline-block; position:relative;">
            <input type="text" class="col-search-input" placeholder="Oszlop..." oninput="filterCustomList(this)" onclick="showCustomList(this)" autocomplete="off">
            <input type="hidden" name="s_col[]" class="real-col-value" value="all">
            <div class="custom-col-list" style="display:none; border:1px solid #ccc; background:white; max-height:150px; overflow-y:auto; position:absolute; z-index:1000; width:100%;">${listItems}</div>
        </div>`;
    } else {
        colHtml = `<select name="s_col[]" onchange="updateLogicPreview()"><option value="all">(bárhol)</option>${window.columnOptions}</select>`;
    }

    row.innerHTML = `
        <input type="hidden" name="s_group[]" value="${groupId}">
        <span class="remove-row" onclick="this.parentElement.remove(); updateLogicPreview();" style="color:red; cursor:pointer; font-weight:bold; margin-right:5px;">X</span>
        <span class="insert-row" onclick="insertSearchRow(this)" style="color:green; cursor:pointer; font-weight:bold; margin-right:10px;">(+)</span>
        ${colHtml}
        <select name="s_op[]" onchange="updateLogicPreview()">${window.operatorOptions}</select>
        <input type="text" name="s_val[]" class="search-input" oninput="updateLogicPreview()">
        <select name="s_logic[]" class="search-logic" onchange="updateLogicPreview()">${window.logicOptions}</select>
        <span class="move-row" style="margin-left: 10px; cursor: pointer; user-select: none;">
            <span onclick="moveRowUp(this)">▲</span>
            <span onclick="moveRowDown(this)">▼</span>
        </span>`;
    return row;
}

function addSearchRow() {
    const container = document.getElementById('dynamic-search-container');
    container.appendChild(createRowElement(0));
    updateLogicPreview();
}


// xcxxx kód, ezt a blokot cseréld
// LOGIKAI MODELL FRISSÍTÉSE (Csoport-tudatos verzió) [cite: 2026-03-07]
function updateLogicPreview() {
    const container = document.getElementById('dynamic-search-container');
    let fullLogic = [];
    let fullReadable = []; // ÚJ: Olvasható magyar mondat
    const elements = container.children;
    
    for (let i = 0; i < elements.length; i++) {
        let el = elements[i];
        const isLast = (i === elements.length - 1);
        
        if (el.classList.contains('search-row')) {
            let part = getRowLogicString(el);
            let readPart = getRowReadableString(el);
            if (part) {
                const logic = el.querySelector('.search-logic').value;
                const logicH = logic === 'AND' ? ' ÉS ' : ' VAGY ';
                fullLogic.push(part + (isLast ? "" : ` ${logic} `));
                fullReadable.push(readPart + (isLast ? "" : `<span style="color:#2196F3; font-weight:bold;">${logicH}</span>`));
            }
        } else if (el.classList.contains('search-group')) {
            const rows = el.querySelectorAll('.search-row');
            let groupParts = [];
            let groupReads = [];
            rows.forEach((r, idx) => {
                let p = getRowLogicString(r);
                let rp = getRowReadableString(r);
                if (p) {
                    const rLogic = r.querySelector('.search-logic').value;
                    const rLogicH = rLogic === 'AND' ? ' ÉS ' : ' VAGY ';
                    groupParts.push(p + (idx === rows.length - 1 ? "" : ` ${rLogic} `));
                    groupReads.push(rp + (idx === rows.length - 1 ? "" : `<span style="color:#2196F3; font-weight:bold;">${rLogicH}</span>`));
                }
            });
            if (groupParts.length > 0) {
                const gLogicEl = el.querySelector('.group-logic');
                const gLogic = gLogicEl ? gLogicEl.value : "AND";
                const gLogicH = gLogic === 'AND' ? ' ÉS ' : ' VAGY ';
                fullLogic.push(`(${groupParts.join("")})` + (isLast ? "" : ` ${gLogic} `));
                fullReadable.push(`( ${groupReads.join("")} )` + (isLast ? "" : `<span style="color:#2196F3; font-weight:bold;">${gLogicH}</span>`));
            }
        }
    }
    const logicStr = fullLogic.join("").trim();
    const readStr = fullReadable.join("").trim();

    // Fejlesztői (nyers) SQL logika a felső sorba
    document.getElementById('logic-string').innerText = logicStr || "Válasszon feltételt...";

    // Hibakeresés: okos figyelem, ha véletlenül lehetetlent kér a felhasználó
    let isError = false;
    let currentAndGroup = {};
    const allSearchRows = container.querySelectorAll('.search-row');

    for (let i = 0; i < allSearchRows.length; i++) {
        let r = allSearchRows[i];
        let colEl = r.querySelector('.real-col-value') || r.querySelector('select[name="s_col[]"]');
        let op = r.querySelector('[name="s_op[]"]').value;
        let val = r.querySelector('[name="s_val[]"]').value.trim();

        if (colEl && colEl.value !== 'all' && op === '=' && val !== '') {
            if (currentAndGroup[colEl.value] !== undefined && currentAndGroup[colEl.value] !== val) {
                isError = true;
                break;
            }
            currentAndGroup[colEl.value] = val;
        }
        let logicEl = r.querySelector('.search-logic');
        if (logicEl && logicEl.value === 'OR') {
            currentAndGroup = {}; // VAGY esetén tiszta lappal indul a vizsgálat
        }
    }

    // Emberi Minta eredmény frissítése
    const sampleEl = document.getElementById('sample-result');
    if (sampleEl) {
        if (isError) {
            sampleEl.innerHTML = `❌ <span style="color:red; font-weight:bold;">HIBA: Egy mező nem lehet egyszerre két különböző dolog! (Használj VAGY kapcsolatot, vagy IN operátort)</span>`;
        } else {
            sampleEl.innerHTML = readStr ? readStr : "-";
            sampleEl.style.color = "#444";
            sampleEl.style.fontWeight = "normal";
        }
    }
}

// Fejlesztői SQL kinyerése
function getRowLogicString(row) {
    const colEl = row.querySelector('.real-col-value') || row.querySelector('select[name="s_col[]"]');
    const op = row.querySelector('[name="s_op[]"]').value;
    const val = row.querySelector('[name="s_val[]"]').value.trim();
    if (!colEl || (val === "" && op !== 'IS NULL' && op !== 'IS NOT NULL')) return null;
    return `${colEl.value === 'all' ? 'BÁRHOL' : colEl.value} ${op}${val ? " '" + val + "'" : ""}`;
}

// ÚJ: Magyar emberi nyelvű mondat kinyerése
function getRowReadableString(row) {
    const colEl = row.querySelector('.real-col-value') || row.querySelector('select[name="s_col[]"]');
    const opSelect = row.querySelector('[name="s_op[]"]');
    const val = row.querySelector('[name="s_val[]"]').value.trim();

    if (!colEl) return null;
    const op = opSelect.value;
    if (val === "" && op !== 'IS NULL' && op !== 'IS NOT NULL') return null;

    const colText = colEl.value === 'all' ? 'Bármely mező' : colEl.value;

    let opText = "";
    switch(op) {
        case '=': opText = '='; break;
        case '!=': opText = 'nem'; break;
        case 'LIKE': opText = 'kezdődik:'; break;
        case 'LIKE_ANY': opText = 'tartalmazza:'; break;
        case 'NOT LIKE': opText = 'nem tartalmazza:'; break;
        case 'IS NULL': return `<b>${colText}</b> üres`;
        case 'IS NOT NULL': return `<b>${colText}</b> ki van töltve`;
        case 'IN': opText = 'ezek közül:'; break;
        case 'NOT IN': opText = 'nem ezek:'; break;
        default: opText = op; // <, >, <=, >= stb.
    }

    return `<b>${colText}</b> ${opText} <i>${val}</i>`;
}




// ... (showCustomList, filterCustomList, selectCustomOption és a click listener változatlan marad) ...
function showCustomList(input) {
    const list = input.parentElement.querySelector('.custom-col-list');
    // SZŐRSZÁLHASOGATÓ JAVÍTÁS: Csak akkor módosítjuk, ha megtaláltuk [cite: 2026-03-07]
    if (list) {
        list.style.display = 'block';
    } else {
        console.error("Hiba: A .custom-col-list nem található az input mellett!");
    }
}


// Szűrés gépelés alapján [cite: 2026-03-07]
function filterCustomList(input) {
    const filter = input.value.toLowerCase();
    const list = input.parentElement.querySelector('.custom-col-list');
    const options = list.querySelectorAll('div');
    
    list.style.display = 'block';
    options.forEach(opt => {
        const text = opt.innerText.toLowerCase();
        opt.style.display = text.includes(filter) ? 'block' : 'none';
    });
}
function selectCustomOption(element, value) {
    const container = element.parentElement.parentElement;
    const input = container.querySelector('.col-search-input');
    const hidden = container.querySelector('.real-col-value');
    const list = container.querySelector('.custom-col-list');
    
    if (input) input.value = element.innerText; 
    if (hidden) hidden.value = value;            
    if (list) list.style.display = 'none';     
    
    updateLogicPreview();            
}


// Kattintás kívülre: bezárja a listát
document.addEventListener('click', function(e) {
    if (!e.target.closest('.special-col-container')) {
        document.querySelectorAll('.custom-col-list').forEach(list => {
            list.style.display = 'none';
        });
    }
});

// Ez az a rész, ami hiányzott a fájlból: betöltéskor is fusson le a frissítés
document.addEventListener('DOMContentLoaded', () => {
    updateLogicPreview();
});


