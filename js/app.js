/* ============================================================
   APP.JS - Fonctions JavaScript globales
   Import/Export Manager
   ============================================================ */

// ============================================================
// SIDEBAR TOGGLE
// ============================================================
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.toggle('open');
    if (overlay) overlay.classList.toggle('open');
}

// Fermer sidebar au clic sur overlay
document.addEventListener('DOMContentLoaded', function () {
    const overlay = document.getElementById('sidebarOverlay');
    if (overlay) {
        overlay.addEventListener('click', toggleSidebar);
    }

    // Auto-dismiss alerts
    document.querySelectorAll('.alert[data-auto-dismiss]').forEach(function (el) {
        const delay = parseInt(el.getAttribute('data-auto-dismiss')) || 4000;
        setTimeout(function () {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 400);
        }, delay);
    });
});

// ============================================================
// MODALS
// ============================================================
function openModal(id) {
    const overlay = document.getElementById(id);
    if (overlay) {
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    const overlay = document.getElementById(id);
    if (overlay) {
        overlay.classList.remove('open');
        document.body.style.overflow = '';
    }
}

// Fermer modal au clic sur overlay
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
        document.body.style.overflow = '';
    }
});

// Fermer au clic .modal-close
document.addEventListener('click', function (e) {
    if (e.target.closest('.modal-close')) {
        const overlay = e.target.closest('.modal-overlay');
        if (overlay) {
            overlay.classList.remove('open');
            document.body.style.overflow = '';
        }
    }
});

// ============================================================
// CONFIRMATION SUPPRESSION
// ============================================================
function confirmDelete(message) {
    return confirm(message || 'Confirmer la suppression ?');
}

// ============================================================
// FORMAT MONTANT
// ============================================================
function formatMontant(val, devise) {
    devise = devise || 'FCFA';
    return parseInt(val || 0).toLocaleString('fr-FR') + ' ' + devise;
}

// ============================================================
// GENERATEUR CODE COLIS (côté client)
// Format : CODE_JJMMAA
// ============================================================
function genererCodeComplet(codeReel, dateStr) {
    if (!codeReel || !dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return '';
    const jj = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const aa = String(d.getFullYear()).slice(-2);
    return codeReel.trim().toUpperCase() + '_' + jj + mm + aa;
}

// ============================================================
// MISE A JOUR PREVIEW CODE COLIS EN TEMPS REEL
// ============================================================
function setupCodePreview(inputCodeId, inputDateId, previewId) {
    const inputCode = document.getElementById(inputCodeId);
    const inputDate = document.getElementById(inputDateId);
    const preview   = document.getElementById(previewId);
    if (!inputCode || !inputDate || !preview) return;

    function update() {
        const code = genererCodeComplet(inputCode.value, inputDate.value);
        preview.textContent = code || '-';
    }

    inputCode.addEventListener('input', update);
    inputDate.addEventListener('change', update);
    update();
}

// ============================================================
// TYPE COLIS : afficher/masquer section propriétaires
// ============================================================
function toggleTypeColis(selectEl) {
    const type = selectEl.value;
    const sectionMixte = document.getElementById('section-mixte');
    const sectionIndividuel = document.getElementById('section-individuel');
    if (sectionMixte) sectionMixte.style.display = (type === 'mixte') ? 'block' : 'none';
    if (sectionIndividuel) sectionIndividuel.style.display = (type === 'individuel') ? 'block' : 'none';
}

// ============================================================
// CALCUL MONTANT PAR PROPRIÉTAIRE (colis mixte)
// Règle : montant_part = (poids_part / poids_total_colis) * montant_total_colis
// Le diviseur est TOUJOURS le poids total du colis, pas la somme des parts.
// ============================================================
function calcMontantProprietaires() {
    const montantTotal = parseFloat(document.getElementById('colis_montant')?.value || 0);
    const poidsTotal   = parseFloat(document.getElementById('colis_poids')?.value || 0);
    const rows = document.querySelectorAll('.proprietaire-row');

    // Calcul de la somme des poids saisis (pour contrôle)
    let poidsSum = 0;
    rows.forEach(function (row) {
        poidsSum += parseFloat(row.querySelector('.prop-poids')?.value || 0);
    });

    // Diviseur = poids total du colis (référence absolue)
    // Si le poids du colis n'est pas encore saisi, on divise par poidsSum
    const diviseur = poidsTotal > 0 ? poidsTotal : poidsSum;

    rows.forEach(function (row) {
        const p = parseFloat(row.querySelector('.prop-poids')?.value || 0);
        const montantEl = row.querySelector('.prop-montant');
        if (montantEl && diviseur > 0) {
            const montant = Math.round((p / diviseur) * montantTotal);
            montantEl.value = montant;
            // Bordure rouge si la part dépasse le montant total du colis
            montantEl.style.borderColor = (montantTotal > 0 && montant > montantTotal) ? 'var(--danger)' : '';
        }
    });

    // Avertissement si la somme des poids dépasse le poids du colis
    const warnEl = document.getElementById('poids-mixte-warn');
    if (warnEl) {
        if (poidsTotal > 0 && poidsSum > poidsTotal + 0.001) {
            warnEl.style.display = 'block';
            warnEl.textContent = 'Attention : la somme des poids des propriétaires (' + poidsSum.toFixed(2) + ' kg) depasse le poids total du colis (' + poidsTotal.toFixed(2) + ' kg).';
        } else {
            warnEl.style.display = 'none';
        }
    }
}

// ============================================================
// AJOUTER LIGNE PROPRIÉTAIRE (colis mixte)
// ============================================================
let propCounter = 0;
function ajouterProprietaire(clientsData) {
    propCounter++;
    const container = document.getElementById('proprietaires-container');
    if (!container) return;

    let optionsHtml = '<option value="">-- Client --</option>';
    if (clientsData && clientsData.length) {
        clientsData.forEach(function (c) {
            optionsHtml += `<option value="${c.id}">${c.nom}</option>`;
        });
    }

    const row = document.createElement('div');
    row.className = 'proprietaire-row';
    row.id = 'prop-row-' + propCounter;
    row.innerHTML = `
        <select name="proprietaires[${propCounter}][client_id]" class="form-control" required>
            ${optionsHtml}
        </select>
        <input type="number" name="proprietaires[${propCounter}][poids]" 
               class="form-control prop-poids" placeholder="Poids kg" 
               step="0.01" min="0" oninput="calcMontantProprietaires()">
        <input type="number" name="proprietaires[${propCounter}][montant_du]" 
               class="form-control prop-montant" placeholder="Montant" step="1" min="0">
        <input type="number" name="proprietaires[${propCounter}][montant_paye]" 
               class="form-control" placeholder="Payé" step="1" min="0" value="0">
        <button type="button" class="btn-icon delete" onclick="supprimerProprietaire('prop-row-${propCounter}')">
            <i class="fas fa-trash"></i>
        </button>
    `;
    container.appendChild(row);
}

function supprimerProprietaire(rowId) {
    const row = document.getElementById(rowId);
    if (row) row.remove();
}

// ============================================================
// EXPORT CSV GENERIQUE
// ============================================================
function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;

    const rows = table.querySelectorAll('tr');
    const csv = [];

    rows.forEach(function (row) {
        const cells = row.querySelectorAll('th, td');
        const rowData = [];
        cells.forEach(function (cell) {
            // Exclure colonnes actions
            if (cell.classList.contains('no-export')) return;
            let text = cell.textContent.replace(/\s+/g, ' ').trim();
            text = '"' + text.replace(/"/g, '""') + '"';
            rowData.push(text);
        });
        csv.push(rowData.join(';'));
    });

    const blob = new Blob(['\uFEFF' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = (filename || 'export') + '_' + getDateStr() + '.csv';
    link.click();
    URL.revokeObjectURL(url);
}

// ============================================================
// EXPORT PDF BILAN via jsPDF + autoTable
// ============================================================
function exportBilanPDF(options) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });

    const date = options.date || getDateStr();
    const title = options.title || 'Bilan journalier';
    const entete = options.entete || 'Gestion Import/Export';

    // En-tête
    doc.setFillColor(30, 64, 175);
    doc.rect(0, 0, 210, 28, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(16);
    doc.setFont('helvetica', 'bold');
    doc.text(entete, 14, 12);
    doc.setFontSize(11);
    doc.setFont('helvetica', 'normal');
    doc.text(title, 14, 20);
    doc.setFontSize(9);
    doc.text('Date : ' + date, 14, 26);
    doc.text('Edité le : ' + new Date().toLocaleDateString('fr-FR') + ' à ' + new Date().toLocaleTimeString('fr-FR'), 120, 26);

    doc.setTextColor(30, 30, 30);

    let yPos = 36;

    // Résumé KPI
    if (options.kpis && options.kpis.length) {
        doc.setFontSize(10);
        doc.setFont('helvetica', 'bold');
        doc.text('RESUME DU JOUR', 14, yPos);
        yPos += 4;

        const kpiRows = options.kpis.map(function (k) { return [k.label, k.value]; });
        doc.autoTable({
            startY: yPos,
            head: [['Indicateur', 'Valeur']],
            body: kpiRows,
            theme: 'striped',
            headStyles: { fillColor: [30, 64, 175], textColor: 255, fontStyle: 'bold' },
            styles: { fontSize: 9 },
            columnStyles: { 0: { cellWidth: 80 }, 1: { cellWidth: 60, halign: 'right' } },
            margin: { left: 14, right: 14 }
        });
        yPos = doc.lastAutoTable.finalY + 8;
    }

    // Tableaux de données
    if (options.tables && options.tables.length) {
        options.tables.forEach(function (t) {
            if (yPos > 250) { doc.addPage(); yPos = 14; }

            doc.setFontSize(10);
            doc.setFont('helvetica', 'bold');
            doc.text(t.title.toUpperCase(), 14, yPos);
            yPos += 4;

            doc.autoTable({
                startY: yPos,
                head: [t.headers],
                body: t.rows,
                theme: 'striped',
                headStyles: { fillColor: [30, 64, 175], textColor: 255, fontStyle: 'bold' },
                styles: { fontSize: 8.5 },
                margin: { left: 14, right: 14 }
            });
            yPos = doc.lastAutoTable.finalY + 10;
        });
    }

    // Pied de page
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(8);
        doc.setTextColor(150);
        doc.text('Page ' + i + ' / ' + pageCount, 196, 290, { align: 'right' });
        doc.text('Généré automatiquement - Gestion Import/Export', 14, 290);
    }

    doc.save(filename(title) + '_' + date.replace(/\//g, '-') + '.pdf');
}

function filename(str) {
    return str.toLowerCase().replace(/[^a-z0-9]+/g, '-');
}

function getDateStr() {
    return new Date().toLocaleDateString('fr-FR').replace(/\//g, '-');
}

// ============================================================
// EXPORT RECU PDF (paiement individuel)
// ============================================================
function exportRecuPDF(data) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a5' });

    doc.setFillColor(30, 64, 175);
    doc.rect(0, 0, 148, 24, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(13);
    doc.setFont('helvetica', 'bold');
    doc.text('RECU DE PAIEMENT', 10, 10);
    doc.setFontSize(9);
    doc.text('Gestion Import/Export', 10, 17);

    doc.setTextColor(30, 30, 30);
    doc.setFontSize(9);
    doc.setFont('helvetica', 'normal');

    let y = 32;
    const addLine = function (label, value) {
        doc.setFont('helvetica', 'bold');
        doc.text(label, 10, y);
        doc.setFont('helvetica', 'normal');
        doc.text(String(value), 55, y);
        y += 7;
    };

    addLine('Client :', data.client || '-');
    addLine('Colis :', data.colis || '-');
    addLine('Montant payé :', data.montant || '0 FCFA');
    addLine('Mode :', data.mode || 'Espèce');
    addLine('Date :', data.date || getDateStr());
    addLine('Référence :', data.reference || '-');

    y += 4;
    doc.setDrawColor(200, 200, 200);
    doc.line(10, y, 138, y);
    y += 6;

    doc.setFontSize(8);
    doc.setTextColor(100);
    doc.text('Solde restant : ' + (data.solde || '0 FCFA'), 10, y);
    y += 5;
    doc.text('Ce reçu a été généré automatiquement.', 10, y);

    doc.setFontSize(7);
    doc.setTextColor(150);
    doc.text('Edité le : ' + new Date().toLocaleString('fr-FR'), 10, 200);

    doc.save('recu_paiement_' + (data.reference || Date.now()) + '.pdf');
}

// ============================================================
// LIVE SEARCH TABLE
// ============================================================
function setupLiveSearch(inputId, tableId, colIndex) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    input.addEventListener('input', function () {
        const query = input.value.toLowerCase().trim();
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(function (row) {
            if (colIndex !== undefined) {
                const cell = row.cells[colIndex];
                const text = cell ? cell.textContent.toLowerCase() : '';
                row.style.display = text.includes(query) ? '' : 'none';
            } else {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            }
        });
    });
}

// ============================================================
// TOAST NOTIFICATION
// ============================================================
function showToast(message, type) {
    type = type || 'info';
    const toast = document.createElement('div');
    toast.className = 'alert alert-' + type;
    toast.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;min-width:260px;box-shadow:0 4px 12px rgba(0,0,0,.15);';
    toast.innerHTML = '<i class="fas fa-' + getToastIcon(type) + '"></i> ' + message;
    document.body.appendChild(toast);
    setTimeout(function () {
        toast.style.transition = 'opacity .4s';
        toast.style.opacity = '0';
        setTimeout(function () { toast.remove(); }, 400);
    }, 3500);
}

function getToastIcon(type) {
    const icons = { success: 'check-circle', danger: 'times-circle', warning: 'exclamation-triangle', info: 'info-circle' };
    return icons[type] || 'info-circle';
}
