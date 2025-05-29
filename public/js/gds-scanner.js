// GDS-scanner.js version 2025-0527-0945


let sessionId = null;

document.addEventListener("DOMContentLoaded", function () {
    const $ = jQuery;

    console.log("🧠 GDS Scanner JS chargé");
    
    // Ajouts ancien fichier 
    
      // ✅ Toggle actif sur les boutons calibre
    $(document).on('click', '.gds-calibre-btn', function () {
        $(this).toggleClass('active');
    });

    // 👤 Toggle affichage invité
    $('#gds-show-invite').on('click', function () {
        $('#gds-invite-form').slideToggle(200);
    });

    // 👻 Cache la liste au démarrage
    $('#gds-live-presence').fadeIn();
    
    
    
    
    // Fin ajouts

    // ✅ Démarrer session
    $('#gds-start-session').on('click', function (e) {
        e.preventDefault();
        const stand = $('#gds-stand').val();
       // const gds_user_name = $('#gds-stand').val();

        fetch('/wp-json/gds/v1/start-session/', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': gds_rest_nonce },
            body: JSON.stringify({ stand })
        })
        .then(res => res.json())
        .then(response => {
            if (response.success) {
                sessionId = response.session_id;
                $('#gds-start-session').hide();
                $('#gds-end-session').show();
                $('#gds-scanner-section').show();
                $('#gds-header-session').text(`Stand ${stand} — Encadrant : ${gds_user_name} — Début : ${new Date().toLocaleTimeString()}`);
                //$('#gds-header-session').text(`Stand ${stand} — Début : ${new Date().toLocaleTimeString()}`);
                $('#gds-live-presence').fadeIn();
                clearLiveTable();
            } else {
                alert("⚠️ " + response.message);
            }
        });
    });

    // ✅ Scanner QR licence FFTir (ITAC)
    $('#gds-scan-qr').on('click', () => {
        const qrDiv = $('#gds-qr-reader').show();
        const html5QrCode = new Html5Qrcode("gds-qr-reader");

        html5QrCode.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            (decodedText) => {
                html5QrCode.stop();
                fetchItacDataFromQr(decodedText);
            },
            (err) => {}
        ).catch(err => {
            console.error("❌ Erreur QR", err);
        });
    });

    // 🧠 Fonction de parsing ITAC

    function fetchItacDataFromQr(url) {
        if (!url.includes('itac.pro/F.aspx')) {
            alert("⚠️ QR non reconnu comme licence FFTir.");
            return;
        }

        fetch('/wp-json/gds/v1/itac-parse/', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': gds_rest_nonce },
            body: JSON.stringify({ url })
        })
        .then(res => res.json())
        .then(response => {
            if (!response.success) return alert("❌ " + response.message);
            const { nom, prenom, licence, statut } = response.data;
            window.gdsItacData = { nom, prenom, licence, statut };
            openGdsModal({ type: 'adhérent', licence, nom, prenom });
        });
    }

    // ✅ Saisie manuelle
    $('#gds-scan-input').on('keydown', function (e) {
        if (e.key === 'Enter') {
            const licence = $(this).val().trim();
            if (licence) openGdsModal({ type: 'adhérent', licence });
        }
    });

    // ✅ Ajout invité
    $('#gds-show-invite').on('click', function () {
        openGdsModal({ type: 'invité' });
    });

    // ✅ Modale
    function openGdsModal({ type, licence = '', nom = '', prenom = '' }) {
        const content = $('#gds-modal-content');
        let html = '';

        if (type === 'invité') {
            html += `
                <div><label>Nom :</label>
                <input type="text" id="gds-modal-name" value="${nom}" /></div>
                <div><label>Code :</label>
                <input type="text" id="gds-modal-code" /></div>
            `;
        } else {
            html += `
                <div><label>Licence :</label>
                <input type="text" id="gds-modal-licence" value="${licence}" readonly /></div>
                <div><label>Nom complet :</label>
                <input type="text" id="gds-modal-name" value="${nom} ${prenom}" /></div>
            `;
        }

        html += `
            <div><label>Calibres :</label>
            <div id="gds-modal-calibres" class="gds-calibre-buttons">
                ${$('.gds-calibre-buttons').html()}
            </div></div>
            <p>Autre : <input type="text" id="gds-calibre-custom" /></p>
        `;

        content.html(html);
        $('#gds-modal-overlay').fadeIn();
    }

    $('#gds-modal-cancel').on('click', function () {
        $('#gds-modal-overlay').fadeOut();
    });

    $('#gds-modal-confirm').on('click', function () {
        const type = $('#gds-modal-code').length ? 'invité' : 'adhérent';
        const name = $('#gds-modal-name').val().trim();
        const licence = $('#gds-modal-licence')?.val()?.trim() || '';
        const code = $('#gds-modal-code')?.val()?.trim() || '';
        let calibres = [];

        $('#gds-modal-calibres .gds-calibre-btn.active').each(function () {
            calibres.push($(this).data('value'));
        });

        const custom = $('#gds-calibre-custom').val()?.trim();
        if (custom) calibres.push(custom);

        if (type === 'invité') {
            fetch('/wp-json/gds/v1/invite/', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': gds_rest_nonce },
                body: JSON.stringify({ session_id: sessionId, name, code, calibres })
            })
            .then(res => res.json())
            .then(response => {
                alert(`✅ ${name}, invité ${code} est bien entré.`);
                addParticipantRow({ type: 'invité', id: name, entry_time: new Date().toISOString().slice(0, 16).replace('T', ' ') });
                updateLiveCount();
                $('#gds-modal-overlay').fadeOut();
            });
        } else {
            fetch('/wp-json/gds/v1/scan/', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': gds_rest_nonce },
                body: JSON.stringify({ session_id: sessionId, licence, name, calibres })
            })
            .then(res => res.json())
            .then(response => {
                alert(`✅ ${name}, licence ${licence} est bien entré.`);
                addParticipantRow({ type: 'adhérent', id: licence, entry_time: new Date().toISOString().slice(0, 16).replace('T', ' ') });
                updateLiveCount();
                $('#gds-modal-overlay').fadeOut();
            });
        }
    });

    // ✅ Fin de session
    $('#gds-end-session').on('click', function (e) {
        e.preventDefault();
        if (!sessionId) return;

        fetch('/wp-json/gds/v1/end-session/', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': gds_rest_nonce },
            body: JSON.stringify({ session_id: sessionId })
        })
        .then(res => res.json())
        .then(response => {
            $('#gds-log').prepend('<p>' + response.message + '</p>');
            $('#gds-live-presence').hide();
            $('#gds-scanner-section').hide();
        });
    });
    


    // ✅ Actions tableau
    function addParticipantRow({ type, id, entry_time }) {
        const row = `
        <tr>
            <td>${type}</td>
            <td>${id}</td>
            <td>${entry_time}</td>
            <td><em>En cours</em></td>
            <td><button class="gds-exit-btn" data-id="${id}" data-type="${type}">Sortie</button></td>
        </tr>`;
        $('#gds-live-table tbody').append(row);
    }

    $('#gds-live-table').on('click', '.gds-exit-btn', function () {
        const id = $(this).data('id');
        const type = $(this).data('type');
        const endpoint = (type === 'invité') ? 'exit-invite' : 'scan';

        fetch('/wp-json/gds/v1/' + endpoint + '/', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': gds_rest_nonce },
            body: JSON.stringify({ session_id: sessionId, name: id, licence: id })
        })
        .then(res => res.json())
        .then(response => {
            $(this).closest('tr').remove();
            updateLiveCount();
        });
    });

    // Ajouts anciennes fonctions 
    
       // 🔢 Compteur
    function updateLiveCount() {
        const count = $('#gds-live-table tbody tr').length;
        $('#gds-live-count').text(count);
    }

    function clearLiveTable() {
        $('#gds-live-table tbody').empty();
        updateLiveCount();
    }

    
    
    // fin ajout
 //   function updateLiveCount() {
 //       $('#gds-live-count').text($('#gds-live-table tbody tr').length);
 //   }

//    function clearLiveTable() {
//        $('#gds-live-table tbody').empty();
//        updateLiveCount();
//    }

    // Calibre toggle
    $(document).on('click', '.gds-calibre-btn', function () {
        $(this).toggleClass('active');
    });
});
