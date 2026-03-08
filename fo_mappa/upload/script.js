function openUploadModal() { document.getElementById('uploadModal').style.display = 'flex'; }
function closeUploadModal() { document.getElementById('uploadModal').style.display = 'none'; }

function updateFileName(input) {
    const fileName = input.files[0] ? input.files[0].name : "Nincs kiválasztott fájl";
    document.getElementById('file_name_display').textContent = fileName;
}

async function startImportProcess() {
    const tableNameInput = document.getElementById('new_table_name');
    const tableName = tableNameInput.value.trim();
    const fileInput = document.getElementById('csv_file');
    const bar = document.getElementById('progress_bar_fill');
    const status = document.getElementById('progress_status');

    const tableRegex = /^[a-zA-Z0-9_]{1,50}$/;
    if (!tableRegex.test(tableName)) {
         alert("A tábla neve csak angol betűket, számokat és aláhúzást tartalmazhat (max 50 karakter)!");
        return;
    }

    // ÚJ: SZERVER OLDALI ELLENŐRZÉS INDÍTÁSA
    try {
        const checkRes = await fetch('upload/processor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=check_table&table_name=${tableName}`
        });
        const checkData = await checkRes.json();
        
      if (checkData.exists) {
            alert("A tábla már létezik válaszon másik megnevezést");
            return;
        }
    } catch (e) {
        console.error("Ellenőrzési hiba:", e);
    }

    // Itt folytatódik a kód tisztán
    let currentImportId = 'imp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5);

    const formData = new FormData();
    formData.append('table_name', tableName);
    formData.append('csv_file', fileInput.files[0]);
    formData.append('import_id', currentImportId);

    document.getElementById('progress_container').style.display = 'block';
    document.getElementById('btn_upload_start').disabled = true;

    // Polling indítása (Státusz figyelés)
    let progressInterval = setInterval(async () => {
        try {
            const res = await fetch(`upload/get_status.php?import_id=${currentImportId}`);
            const data = await res.json();
            
            if (data.step === 'ssd') {
                status.textContent = `1/2: SSD Mentés... (${data.current.toLocaleString()} sor)`;
                let estimatedMax = Math.max(415000, data.current + 50000);
                let ssdPercent = Math.min(30, (data.current / estimatedMax) * 30);
                bar.style.width = ssdPercent + "%"; 
            } else if (data.step === 'hdd') {
                status.textContent = `2/2: HDD fázis... (${data.current.toLocaleString()} / ${data.total.toLocaleString()})`;
                let percent = 30 + ((data.current / data.total) * 70);
                bar.style.width = percent + "%";
            }
        } catch (e) { }
    }, 300);

    // FÁJL FELTÖLTÉSE (XHR)
    const xhr = new XMLHttpRequest();
    xhr.upload.onprogress = function(e) {
        if (e.lengthComputable) {
            const uploadPercent = Math.round((e.loaded / e.total) * 100);
            // Az első 10%-ot adjuk a feltöltésnek a csíkon
            bar.style.width = (uploadPercent * 0.1) + "%";
            status.textContent = `0/2: Fájl küldése... ${uploadPercent}%`;
        }
    };

    xhr.onload = async function() {
        if (xhr.status === 200) {
            const result = JSON.parse(xhr.responseText);
            if (result.success) {
                // SIKERES SSD MENTÉS -> INDUL A HDD TRANSZPONÁLÁS
                try {
                    const transResponse = await fetch('upload/processor.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=transpose&import_id=${currentImportId}&table_name=${tableName}`
                    });
                    const transResult = await transResponse.json();

                    if (transResult.success) {
                        clearInterval(progressInterval);
                        bar.style.width = "100%";
                        status.textContent = "Sikeres importálás!";
                        setTimeout(() => { location.reload(); }, 1500);
                    } else {
                        throw new Error(transResult.message);
                    }
                } catch (err) {
                    clearInterval(progressInterval);
                    status.textContent = "Hiba (HDD): " + err.message;
                    bar.style.backgroundColor = "red";
                }
            } else {
                clearInterval(progressInterval);
                status.textContent = "Hiba (SSD): " + result.message;
                bar.style.backgroundColor = "red";
            }
        }
    };

    xhr.onerror = function() {
        clearInterval(progressInterval);
        status.textContent = "Hálózati hiba a feltöltés során.";
    };

    xhr.open('POST', 'upload/processor.php');
    xhr.send(formData);
}