<?php
$pageTitle  = 'Arrivages';
$activePage = 'arrivages';
$root       = '../';
require_once '../includes/header.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $db->prepare("DELETE FROM arrivages WHERE id=?")->execute([$id]);
    header('Location: arrivages.php?msg=supprime');
    exit;
}

$filterDate  = $_GET['date'] ?? '';
$filterTrans = (int)($_GET['transitaire_id'] ?? 0);

$where  = '1=1';
$params = [];
if ($filterDate)  { $where .= ' AND a.date_arrivee = ?'; $params[] = $filterDate; }
if ($filterTrans) { $where .= ' AND EXISTS (SELECT 1 FROM colis cc WHERE cc.arrivage_id = a.id AND cc.transitaire_id = ?)'; $params[] = $filterTrans; }

$arrivages = $db->prepare("
    SELECT a.id, a.date_arrivee,
           COUNT(DISTINCT c.transitaire_id) AS nb_transitaires,
           COUNT(c.id) AS nb_colis,
           COALESCE(SUM(c.poids),0) AS poids_total
    FROM arrivages a
    LEFT JOIN colis c ON c.arrivage_id = a.id
    WHERE $where
    GROUP BY a.id
    ORDER BY a.date_arrivee DESC
");
$arrivages->execute($params);
$arrivages = $arrivages->fetchAll();

$transitaires = $db->query("SELECT * FROM transitaires ORDER BY nom")->fetchAll();
$transitairesJson = json_encode($transitaires, JSON_UNESCAPED_UNICODE);

$msgGet = $_GET['msg'] ?? '';
?>
<div class="page-content">

<?php if ($msgGet === 'supprime'): ?>
    <div class="alert alert-success" data-auto-dismiss="4000"><i class="fas fa-check-circle"></i> Arrivage supprimé.</div>
<?php endif; ?>

<!-- ===== NOUVELLE SAISIE ===== -->
<div class="card" id="form-section" style="display:none">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-plus-circle"></i> Saisir un nouvel arrivage</div>
        <button class="btn btn-outline btn-sm" onclick="fermerFormulaire()"><i class="fas fa-times"></i> Fermer</button>
    </div>
    <div class="card-body">
        <div id="form-alert" style="display:none"></div>

        <div class="form-group" style="max-width:220px">
            <label class="form-label">Date d'arrivée <span class="required">*</span></label>
            <input type="date" id="arrivage-date" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>

        <div id="transitaires-blocs"></div>

        <button type="button" class="btn btn-outline" onclick="ajouterBlocTransitaire()" style="margin-top:8px">
            <i class="fas fa-plus"></i> Ajouter un transitaire
        </button>

        <div class="form-actions">
            <button type="button" class="btn btn-outline" onclick="fermerFormulaire()">Annuler</button>
            <button type="button" class="btn btn-primary" onclick="sauvegarderArrivage()">
                <i class="fas fa-save"></i> Enregistrer l'arrivage
            </button>
        </div>
    </div>
</div>

<!-- ===== LISTE DES ARRIVAGES ===== -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-truck-loading"></i> Arrivages enregistrés</div>
        <button class="btn btn-primary" onclick="ouvrirFormulaire()">
            <i class="fas fa-plus"></i> Nouvel arrivage
        </button>
    </div>
    <div class="card-body">
        <form method="get" class="filters-bar">
            <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>" class="form-control" style="width:160px">
            <select name="transitaire_id" class="form-control" style="width:200px">
                <option value="">Tous les transitaires</option>
                <?php foreach ($transitaires as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $filterTrans == $t['id'] ? 'selected' : '' ?>>
                        <?= sanitize($t['nom']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline"><i class="fas fa-filter"></i> Filtrer</button>
            <a href="arrivages.php" class="btn btn-outline"><i class="fas fa-times"></i> Réinitialiser</a>
        </form>

        <div class="table-responsive">
            <table id="tableArrivages">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Transitaires</th>
                        <th>Nb colis</th>
                        <th>Poids total (kg)</th>
                        <th class="no-export">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($arrivages): ?>
                        <?php foreach ($arrivages as $a): ?>
                        <tr>
                            <td><strong><?= date('d/m/Y', strtotime($a['date_arrivee'])) ?></strong></td>
                            <td>
                                <span class="badge badge-info"><?= $a['nb_transitaires'] ?> transitaire<?= $a['nb_transitaires'] > 1 ? 's' : '' ?></span>
                            </td>
                            <td>
                                <span class="badge badge-primary"><?= $a['nb_colis'] ?> colis</span>
                            </td>
                            <td><?= number_format((float)$a['poids_total'], 2) ?> kg</td>
                            <td class="no-export">
                                <div class="table-actions">
                                    <button class="btn-icon view" data-tooltip="Voir les colis"
                                        onclick="voirDetailArrivage(<?= $a['id'] ?>, '<?= date('d/m/Y', strtotime($a['date_arrivee'])) ?>')">
                                        <i class="fas fa-list"></i>
                                    </button>
                                    <form method="post" style="display:inline"
                                          onsubmit="return confirmDelete('Supprimer cet arrivage et tous ses colis ?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                        <button type="submit" class="btn-icon delete" data-tooltip="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5">
                            <div class="empty-state">
                                <i class="fas fa-truck-loading"></i>
                                <h3>Aucun arrivage trouvé</h3>
                                <p>Cliquez sur "Nouvel arrivage" pour commencer.</p>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- MODAL DÉTAIL ARRIVAGE -->
<div class="modal-overlay" id="modalDetailArrivage">
    <div class="modal modal-xl">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-list"></i> Colis de l'arrivage — <span id="detailArrivageDate"></span>
            </div>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="detailArrivageContent">
            <div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Chargement...</p></div>
        </div>
    </div>
</div>

<script>
const TRANSITAIRES_DATA = <?= $transitairesJson ?>;
let blocCounter = 0;

function ouvrirFormulaire() {
    const section = document.getElementById('form-section');
    section.style.display = 'block';
    section.scrollIntoView({ behavior: 'smooth' });
    if (document.getElementById('transitaires-blocs').children.length === 0) {
        ajouterBlocTransitaire();
    }
}

function fermerFormulaire() {
    document.getElementById('form-section').style.display = 'none';
}

function ajouterBlocTransitaire() {
    blocCounter++;
    const id = blocCounter;
    const container = document.getElementById('transitaires-blocs');

    let opts = '<option value="">-- Sélectionner un transitaire --</option>';
    TRANSITAIRES_DATA.forEach(function(t) {
        opts += `<option value="${t.id}">${t.nom}</option>`;
    });

    const bloc = document.createElement('div');
    bloc.className = 'transitaire-bloc';
    bloc.id = 'bloc-' + id;
    bloc.innerHTML = `
        <div class="transitaire-bloc-header">
            <div style="display:flex;align-items:center;gap:10px;flex:1">
                <i class="fas fa-shipping-fast" style="color:var(--primary)"></i>
                <select class="form-control" id="trans-sel-${id}" style="max-width:280px" required>
                    ${opts}
                </select>
            </div>
            <button type="button" class="btn-icon delete" onclick="supprimerBloc(${id})" data-tooltip="Supprimer ce transitaire">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <div class="colis-table-wrap">
            <table class="colis-saisie-table">
                <thead>
                    <tr>
                        <th>Code colis</th>
                        <th>Poids (kg)</th>
                        <th>Type</th>
                        <th style="width:36px"></th>
                    </tr>
                </thead>
                <tbody id="colis-body-${id}"></tbody>
            </table>
            <button type="button" class="btn btn-outline btn-sm" onclick="ajouterLigneColis(${id})" style="margin-top:8px">
                <i class="fas fa-plus"></i> Ajouter un colis
            </button>
        </div>
    `;
    container.appendChild(bloc);
    ajouterLigneColis(id);
}

function supprimerBloc(id) {
    const bloc = document.getElementById('bloc-' + id);
    if (bloc) bloc.remove();
}

let ligneCounter = 0;
function ajouterLigneColis(blocId) {
    ligneCounter++;
    const lid = ligneCounter;
    const tbody = document.getElementById('colis-body-' + blocId);
    if (!tbody) return;

    const date = document.getElementById('arrivage-date').value;
    const tr = document.createElement('tr');
    tr.id = 'ligne-' + lid;
    tr.innerHTML = `
        <td>
            <input type="text" class="form-control code-input" placeholder="Ex: A109"
                   style="font-family:monospace;text-transform:uppercase"
                   oninput="this.value=this.value.toUpperCase()">
        </td>
        <td>
            <input type="number" class="form-control" placeholder="0.00" step="0.01" min="0" style="width:100px">
        </td>
        <td>
            <select class="form-control" style="width:140px">
                <option value="individuel">Individuel</option>
                <option value="mixte">Mixte</option>
            </select>
        </td>
        <td>
            <button type="button" class="btn-icon delete" onclick="supprimerLigne(${lid})">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    tr.querySelector('.code-input').focus();
}

function supprimerLigne(lid) {
    const row = document.getElementById('ligne-' + lid);
    if (row) row.remove();
}

async function sauvegarderArrivage() {
    const alertEl = document.getElementById('form-alert');
    const date = document.getElementById('arrivage-date').value;

    if (!date) {
        afficherAlerte(alertEl, 'danger', 'Veuillez saisir une date.');
        return;
    }

    const blocs = document.querySelectorAll('.transitaire-bloc');
    if (blocs.length === 0) {
        afficherAlerte(alertEl, 'danger', 'Ajoutez au moins un transitaire.');
        return;
    }

    const payload = { date, transitaires: [] };
    let valid = true;
    let errMsg = '';

    blocs.forEach(function(bloc) {
        const blocId = bloc.id.replace('bloc-', '');
        const transId = parseInt(document.getElementById('trans-sel-' + blocId)?.value);
        if (!transId) { valid = false; errMsg = 'Sélectionnez un transitaire pour chaque bloc.'; return; }

        const colisRows = bloc.querySelectorAll('tbody tr');
        if (colisRows.length === 0) { valid = false; errMsg = 'Chaque transitaire doit avoir au moins un colis.'; return; }

        const colis = [];
        colisRows.forEach(function(row) {
            const inputs = row.querySelectorAll('input, select');
            const code  = (inputs[0]?.value || '').trim().toUpperCase();
            const poids = parseFloat(inputs[1]?.value || 0);
            const type  = inputs[2]?.value || 'individuel';
            if (!code) { valid = false; errMsg = 'Tous les codes colis sont obligatoires.'; return; }
            colis.push({ code, poids, type });
        });
        payload.transitaires.push({ transitaire_id: transId, colis });
    });

    if (!valid) { afficherAlerte(alertEl, 'danger', errMsg); return; }

    const btn = document.querySelector('#form-section .btn-primary');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';

    try {
        const resp = await fetch('../api/save_arrivage.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await resp.json();

        if (data.success) {
            afficherAlerte(alertEl, 'success', data.message);
            setTimeout(function() { location.reload(); }, 1500);
        } else {
            afficherAlerte(alertEl, 'danger', data.error || 'Erreur inconnue');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Enregistrer l\'arrivage';
        }
    } catch(e) {
        afficherAlerte(alertEl, 'danger', 'Erreur réseau.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Enregistrer l\'arrivage';
    }
}

function afficherAlerte(el, type, msg) {
    el.className = 'alert alert-' + type;
    el.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'times-circle') + '"></i> ' + msg;
    el.style.display = 'flex';
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function voirDetailArrivage(arrivageId, dateLabel) {
    document.getElementById('detailArrivageDate').textContent = dateLabel;
    const content = document.getElementById('detailArrivageContent');
    content.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Chargement...</p></div>';
    openModal('modalDetailArrivage');

    fetch('../api/colis_arrivage.php?arrivage_id=' + arrivageId)
        .then(r => r.json())
        .then(data => {
            if (!data.length) {
                content.innerHTML = '<div class="empty-state"><i class="fas fa-boxes"></i><h3>Aucun colis enregistré</h3></div>';
                return;
            }
            const grouped = {};
            data.forEach(function(c) {
                const key = c.transitaire_nom;
                if (!grouped[key]) grouped[key] = [];
                grouped[key].push(c);
            });

            let html = '';
            for (const trans in grouped) {
                html += `<div style="margin-bottom:20px">
                    <div class="bilan-section-title"><i class="fas fa-shipping-fast"></i> ${trans}</div>
                    <div class="table-responsive"><table>
                        <thead><tr><th>Code colis</th><th>Type</th><th>Poids (kg)</th><th>Répartition</th></tr></thead>
                        <tbody>`;
                grouped[trans].forEach(function(c) {
                    const typeBadge = c.type === 'mixte'
                        ? '<span class="badge badge-info">Mixte</span>'
                        : '<span class="badge badge-primary">Individuel</span>';
                    const reptLabel = c.nb_repartitions > 0
                        ? `<span class="badge badge-success">${c.nb_repartitions} client(s)</span>`
                        : '<span class="badge badge-warning">Non réparti</span>';
                    html += `<tr>
                        <td><span class="colis-code">${c.code_complet}</span></td>
                        <td>${typeBadge}</td>
                        <td>${parseFloat(c.poids).toFixed(2)}</td>
                        <td>${reptLabel}</td>
                    </tr>`;
                });
                html += `</tbody></table></div></div>`;
            }
            content.innerHTML = html;
        })
        .catch(function() {
            content.innerHTML = '<div class="alert alert-danger">Erreur de chargement.</div>';
        });
}
</script>

<style>
.transitaire-bloc {
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    margin-bottom: 16px;
    overflow: hidden;
}
.transitaire-bloc-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 16px;
    background: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
}
.colis-table-wrap {
    padding: 12px 16px;
}
.colis-saisie-table {
    margin-bottom: 0;
}
.colis-saisie-table th {
    background: transparent;
    font-size: 12px;
    padding: 6px 8px;
    border-bottom: 1px solid var(--gray-200);
}
.colis-saisie-table td {
    padding: 6px 8px;
    border-bottom: 1px solid var(--gray-100);
}
.colis-saisie-table tr:last-child td { border-bottom: none; }
</style>

<?php require_once '../includes/footer.php'; ?>
