document.addEventListener('DOMContentLoaded', function () {
    console.log('✅ GDS Admin JS chargé');

    // 📦 Gestion des Calibres
    const calibreWrapper = document.getElementById('gds-calibres-wrapper');
    const addCalibreBtn = document.getElementById('gds-add-calibre');

    if (addCalibreBtn && calibreWrapper) {
        addCalibreBtn.addEventListener('click', () => {
            const div = document.createElement('div');
            div.classList.add('gds-calibre-row');
            div.innerHTML = `
                <input type="text" name="gds_calibres_list[]" placeholder="ex: 9mm" />
                <button type="button" class="button gds-remove-calibre">❌</button>`;
            calibreWrapper.appendChild(div);
        });

        calibreWrapper.addEventListener('click', function (e) {
            if (e.target.matches('.gds-remove-calibre, .gds-remove-calibre *')) {
                const row = e.target.closest('.gds-calibre-row');
                if (row) row.remove();
            }
        });
    }

    // 🏟️ Gestion des Stands
    const standWrapper = document.getElementById('gds-stands-wrapper');
    const addStandBtn = document.getElementById('gds-add-stand');

    if (addStandBtn && standWrapper) {
        addStandBtn.addEventListener('click', () => {
            const div = document.createElement('div');
            div.classList.add('gds-stand-row');
            div.innerHTML = `
                <input type="text" name="gds_stands_list[]" placeholder="ex: Stand 25m" />
                <button type="button" class="button gds-remove-stand">❌</button>`;
            standWrapper.appendChild(div);
        });

        standWrapper.addEventListener('click', function (e) {
            if (e.target.matches('.gds-remove-stand, .gds-remove-stand *')) {
                const row = e.target.closest('.gds-stand-row');
                if (row) row.remove();
            }
        });
    }
});
