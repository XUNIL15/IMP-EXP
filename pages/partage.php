<?php
$pageTitle  = 'Partage des colis';
$activePage = 'partage';
$root       = '../';
require_once '../includes/header.php';

$db = getDB();

$filterArrivage = (int)($_GET['arrivage_id'] ?? 0);
$filterType     = $_GET['type'] ?? '';
$filterSearch   = trim($_GET['q'] ?? '');
$filterStatut   = $_GET['statut'] ?? '';

$where  = '1=1';
$params = [];
if ($filterArrivage) { $where .= ' AND c.arrivage_id = ?'; $params[] = $filterArrivage; }
if ($filterType)     { $where .= ' AND c.type = ?'; $params[] = $filterType; }
if ($filterSearch)   {
    $where .= ' AND (c.code_complet LIKE ? OR c.code_reel LIKE ?)';
    $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%";
}
if ($filterStatut === 'reparti') {
    $where .= ' AND (SELECT COUNT(*) FROM repartitions WHERE colis_id = c.id) > 0';
} elseif ($filterStatut === 'non_reparti') {
    $where .= ' AND (SELECT COUNT(*) FROM repartitions WHERE colis_id = c.id) = 0';
}

$stmtColis = $db->prepare("
    SELECT c.id, c.code_complet, c.code_reel, c.type, c.poids, c.montant,
           a.date_arrivee, a.id AS arrivage_id,
           t.nom AS transitaire_nom,
           (SELECT COUNT(*) FROM repartitions WHERE colis_id = c.id) AS nb_repartitions,
           (SELECT COALESCE(SUM(poids),0) FROM repartitions WHERE colis_id = c.id) AS poids_reparti,
           (SELECT COALESCE(SUM(montant),0) FROM repartitions WHERE colis_id = c.id) AS montant_reparti,
           (SELECT COUNT(*) FROM repartitions WHERE colis_id = c.id AND statut = 1) AS nb_payes
    FROM colis c
    JOIN arrivages a ON c.arrivage_id = a.id
    LEFT JOIN transitaires t ON c.transitaire_id = t.id
    WHERE $where
    ORDER BY a.date_arrivee DESC, t.nom, c.code_complet
");
$stmtColis->execute($params);
$colisListe = $stmtColis->fetchAll();

$arrivages  = $db->query("
    SELECT a.id, a.date_arrivee
    FROM arrivages a
    ORDER BY a.date_arrivee DESC
")->fetchAll();

$clients    = $db->query("SELECT id, nom FROM clients ORDER BY nom")->fetchAll();
$clientsJson = json_encode($clients, JSON_UNESCAPED_UNICODE);
?>
<div class="page-content">

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-share-alt"></i> Partage des colis</div>
    </div>
    <div class="card-body">
        <form method="get" class="filters-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($filterSearch) ?>"
                       class="form-control" placeholder="Rechercher code..." style="padding-left:30px">
            </div>
            <select name="arrivage_id" class="form-control" style="width:180px">
                <option value="">Tous les arrivages</option>
                <?php foreach ($arrivages as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= $filterArrivage == $a['id'] ? 'selected' : '' ?>>
                        <?= date('d/m/Y', strtotime($a['date_arrivee'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="type" class="form-control" style="width:150px">
                <option value="">Tous types</option>
                <option value="individuel" <?= $filterType === 'individuel' ? 'selected' : '' ?>>Individuel</option>
                <option value="mixte" <?= $filterType === 'mixte' ? 'selected' : '' ?>>Mixte</option>
            </select>
            <select name="statut" class="form-control" style="width:160px">
                <option value="">Tous statuts</option>
                <option value="non_reparti" <?= $filterStatut === 'non_reparti' ? 'selected' : '' ?>>Non répartis</option>
                <option value="reparti" <?= $filterStatut === 'reparti' ? 'selected' : '' ?>>Répartis</option>
            </select>
            <button type="submit" class="btn btn-outline"><i class="fas fa-filter"></i> Filtrer</button>
            <a href="partage.php" class="btn btn-outline"><i class="fas fa-times"></i> Réinitialiser</a>
        </form>

        <div class="table-responsive">
            <table id="tablePartage">
                <thead>
                    <tr>
                        <th>Code colis</th>
                        <th>Type</th>
                        <th>Arrivage</th>
                        <th>Transitaire</th>
                        <th>Poids total</th>
                        <th>Répartition</th>
                        <th>Statut paiement</th>
                        <th class="no-export">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($colisListe): ?>
                        <?php foreach ($colisListe as $c): ?>
                        <?php
                            $poidsReparti = (float)$c['poids_reparti'];
                            $poids        = (float)$c['poids'];
                            $pctReparti   = $poids > 0 ? round(($poidsReparti / $poids) * 100) : 0;
                            $nbRep        = (int)$c['nb_repartitions'];
                            $nbPaye       = (int)$c['nb_payes'];
                        ?>
                        <tr>
                            <td><span class="colis-code"><?= sanitize($c['code_complet']) ?></span></td>
                            <td>
                                <?php if ($c['type'] === 'mixte'): ?>
                                    <span class="badge badge-info">Mixte</span>
                                <?php else: ?>
                                    <span class="badge badge-primary">Individuel</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($c['date_arrivee'])) ?></td>
                            <td><?= sanitize($c['transitaire_nom'] ?? '—') ?></td>
                            <td><?= number_format($poids, 2) ?> kg</td>
                            <td>
                                <?php if ($nbRep === 0): ?>
                                    <span class="badge badge-warning">Non réparti</span>
                                <?php else: ?>
                                    <span class="badge badge-success"><?= $nbRep ?> client<?= $nbRep > 1 ? 's' : '' ?></span>
                                    <?php if ($c['type'] === 'mixte' && $poids > 0): ?>
                                        <div class="progress-wrap" style="margin-top:4px;max-width:100px">
                                            <div class="progress-bar-outer">
                                                <div class="progress-bar-inner" style="width:<?= $pctReparti ?>%"></div>
                                            </div>
                                        </div>
                                        <div style="font-size:11px;color:var(--gray-500)"><?= $poidsReparti ?>/<?= $poids ?> kg</div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($nbRep === 0): ?>
                                    <span class="badge badge-gray">—</span>
                                <?php elseif ($nbPaye === $nbRep): ?>
                                    <span class="badge badge-success"><i class="fas fa-check"></i> Tout payé</span>
                                <?php elseif ($nbPaye > 0): ?>
                                    <span class="badge badge-warning"><?= $nbPaye ?>/<?= $nbRep ?> payé</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Non payé</span>
                                <?php endif; ?>
                            </td>
                            <td class="no-export">
                                <div class="table-actions">
                                    <button class="btn btn-sm btn-primary" data-tooltip="Gérer la répartition"
                                        onclick="ouvrirPartage(<?= htmlspecialchars(json_encode([
                                            'id'           => $c['id'],
                                            'code_complet' => $c['code_complet'],
                                            'type'         => $c['type'],
                                            'poids'        => (float)$c['poids'],
                                            'montant'      => (float)$c['montant'],
                                        ])) ?>)">
                                        <i class="fas fa-share-alt"></i> Répartir
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8">
                            <div class="empty-state">
                                <i class="fas fa-boxes"></i>
                                <h3>Aucun colis trouvé</h3>
                                <p>Enregistrez d'abord un arrivage pour saisir des colis.</p>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- MODAL RÉPARTITION -->
<div class="modal-overlay" id="modalPartage">
    <div class="modal modal-xl">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-share-alt"></i>
                Répartition — <span id="partage-code" class="colis-code"></span>
                <span id="partage-type-badge" style="margin-left:8px"></span>
            </div>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="partage-alert" style="display:none"></div>

            <!-- Infos colis -->
            <div style="display:flex;gap:20px;margin-bottom:16px;padding:12px 16px;background:var(--gray-50);border-radius:var(--radius);border:1px solid var(--gray-200)">
                <div>
                    <div style="font-size:11px;color:var(--gray-500);text-transform:uppercase">Poids total</div>
                    <div style="font-size:18px;font-weight:700;color:var(--primary)" id="partage-poids-total">—</div>
                </div>
                <div>
                    <div style="font-size:11px;color:var(--gray-500);text-transform:uppercase">Montant total</div>
                    <div style="font-size:18px;font-weight:700;color:var(--gray-900)" id="partage-montant-total">—</div>
                </div>
                <div id="partage-restant-wrap" style="display:none">
                    <div style="font-size:11px;color:var(--gray-500);text-transform:uppercase">Poids restant</div>
                    <div style="font-size:18px;font-weight:700" id="partage-poids-restant">—</div>
                </div>
            </div>

            <!-- Lignes de répartition -->
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Poids (kg)</th>
                            <th>Montant (FCFA)</th>
                            <th>Statut</th>
                            <th style="width:36px"></th>
                        </tr>
                    </thead>
                    <tbody id="partage-lignes"></tbody>
                </table>
            </div>

            <button type="button" class="btn btn-outline btn-sm" onclick="ajouterLignePartage()" style="margin-top:10px"
                    id="btn-ajouter-client">
                <i class="fas fa-plus"></i> Ajouter un client
            </button>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline modal-close">Annuler</button>
            <button type="button" class="btn btn-primary" onclick="sauvegarderPartage()">
                <i class="fas fa-save"></i> Enregistrer la répartition
            </button>
        </div>
    </div>
</div>

<script>
const CLIENTS_DATA = <?= $clientsJson ?>;
let currentColis = null;
let partageCounter = 0;

function ouvrirPartage(colis) {
    currentColis = colis;
    partageCounter = 0;

    document.getElementById('partage-code').textContent = colis.code_complet;
    document.getElementById('partage-type-badge').innerHTML = colis.type === 'mixte'
        ? '<span class="badge badge-info">Mixte</span>'
        : '<span class="badge badge-primary">Individuel</span>';
    document.getElementById('partage-poids-total').textContent  = parseFloat(colis.poids).toFixed(2) + ' kg';
    document.getElementById('partage-montant-total').textContent = parseInt(colis.montant || 0).toLocaleString('fr-FR') + ' FCFA';
    document.getElementById('partage-alert').style.display = 'none';

    const restantWrap = document.getElementById('partage-restant-wrap');
    restantWrap.style.display = colis.type === 'mixte' ? 'block' : 'none';

    const btnAjouter = document.getElementById('btn-ajouter-client');
    btnAjouter.style.display = colis.type === 'individuel' ? 'none' : '';

    document.getElementById('partage-lignes').innerHTML = '';

    openModal('modalPartage');

    fetch('../api/repartitions.php?colis_id=' + colis.id)
        .then(r => r.json())
        .then(data => {
            if (data.length) {
                data.forEach(function(r) {
                    ajouterLignePartage(r.client_id, r.poids, r.montant, r.statut);
                });
            } else {
                if (colis.type === 'individuel') {
                    ajouterLignePartage(null, colis.poids, colis.montant, 0);
                } else {
                    ajouterLignePartage();
                }
            }
            majPoidsRestant();
        });
}

function ajouterLignePartage(clientId, poids, montant, statut) {
    if (currentColis.type === 'individuel' && partageCounter >= 1) return;

    partageCounter++;
    const lid = partageCounter;
    const tbody = document.getElementById('partage-lignes');

    let opts = '<option value="">-- Sélectionner --</option>';
    CLIENTS_DATA.forEach(function(c) {
        opts += `<option value="${c.id}" ${c.id == clientId ? 'selected' : ''}>${c.nom}</option>`;
    });

    const poidsVal   = poids   != null ? parseFloat(poids).toFixed(2) : '';
    const montantVal = montant != null ? Math.round(montant) : '';
    const statutVal  = statut == 1 ? 1 : 0;
    const isIndiv    = currentColis.type === 'individuel';

    const tr = document.createElement('tr');
    tr.id = 'partage-ligne-' + lid;
    tr.innerHTML = `
        <td>
            <select class="form-control" id="pl-client-${lid}" required>
                ${opts}
            </select>
        </td>
        <td>
            <input type="number" class="form-control pl-poids" id="pl-poids-${lid}"
                   value="${poidsVal}" step="0.01" min="0" style="width:100px"
                   ${isIndiv ? 'readonly' : ''}
                   oninput="calcMontantLigne(${lid}); majPoidsRestant()">
        </td>
        <td>
            <input type="number" class="form-control pl-montant" id="pl-montant-${lid}"
                   value="${montantVal}" step="1" min="0" style="width:130px">
        </td>
        <td>
            <select class="form-control pl-statut" id="pl-statut-${lid}" style="width:130px">
                <option value="0" ${statutVal == 0 ? 'selected' : ''}>❌ Non payé</option>
                <option value="1" ${statutVal == 1 ? 'selected' : ''}>✔ Payé</option>
            </select>
        </td>
        <td>
            ${isIndiv ? '' : `<button type="button" class="btn-icon delete" onclick="supprimerLignePartage(${lid})"><i class="fas fa-trash"></i></button>`}
        </td>
    `;
    tbody.appendChild(tr);
}

function supprimerLignePartage(lid) {
    const row = document.getElementById('partage-ligne-' + lid);
    if (row) { row.remove(); partageCounter = Math.max(0, partageCounter - 1); }
    majPoidsRestant();
}

function calcMontantLigne(lid) {
    const poids   = parseFloat(document.getElementById('pl-poids-' + lid)?.value || 0);
    const poidsT  = parseFloat(currentColis.poids || 0);
    const montantT = parseFloat(currentColis.montant || 0);
    if (poidsT > 0 && montantT > 0) {
        const montant = Math.round((poids / poidsT) * montantT);
        const el = document.getElementById('pl-montant-' + lid);
        if (el) el.value = montant;
    }
}

function majPoidsRestant() {
    if (!currentColis || currentColis.type !== 'mixte') return;
    const poidsTotal = parseFloat(currentColis.poids || 0);
    let sumPoids = 0;
    document.querySelectorAll('.pl-poids').forEach(function(el) {
        sumPoids += parseFloat(el.value || 0);
    });
    const restant = poidsTotal - sumPoids;
    const el = document.getElementById('partage-poids-restant');
    if (el) {
        el.textContent = restant.toFixed(2) + ' kg';
        el.style.color = restant < -0.001 ? 'var(--danger)' : restant > 0.001 ? 'var(--warning)' : 'var(--success)';
    }
}

async function sauvegarderPartage() {
    const alertEl = document.getElementById('partage-alert');
    const rows = document.querySelectorAll('#partage-lignes tr');

    if (!rows.length) {
        afficherAlerteModal(alertEl, 'danger', 'Ajoutez au moins un client.');
        return;
    }

    const repartitions = [];
    let valid = true;
    let sumPoids = 0;

    rows.forEach(function(row) {
        const lid = row.id.replace('partage-ligne-', '');
        const clientId = parseInt(document.getElementById('pl-client-' + lid)?.value);
        const poids    = parseFloat(document.getElementById('pl-poids-' + lid)?.value || 0);
        const montant  = parseFloat(document.getElementById('pl-montant-' + lid)?.value || 0);
        const statut   = parseInt(document.getElementById('pl-statut-' + lid)?.value || 0);

        if (!clientId) { valid = false; return; }
        repartitions.push({ client_id: clientId, poids, montant, statut });
        sumPoids += poids;
    });

    if (!valid) {
        afficherAlerteModal(alertEl, 'danger', 'Sélectionnez un client pour chaque ligne.');
        return;
    }

    const poidsTotal = parseFloat(currentColis.poids || 0);
    if (poidsTotal > 0 && Math.abs(sumPoids - poidsTotal) > 0.01) {
        afficherAlerteModal(alertEl, 'danger',
            `La somme des poids (${sumPoids.toFixed(2)} kg) doit être égale au poids du colis (${poidsTotal.toFixed(2)} kg).`);
        return;
    }

    const btn = document.querySelector('#modalPartage .btn-primary');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';

    try {
        const resp = await fetch('../api/repartitions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ colis_id: currentColis.id, repartitions })
        });
        const data = await resp.json();

        if (data.success) {
            afficherAlerteModal(alertEl, 'success', data.message);
            setTimeout(function() {
                closeModal('modalPartage');
                location.reload();
            }, 1200);
        } else {
            afficherAlerteModal(alertEl, 'danger', data.error || 'Erreur inconnue');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Enregistrer la répartition';
        }
    } catch(e) {
        afficherAlerteModal(alertEl, 'danger', 'Erreur réseau.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Enregistrer la répartition';
    }
}

function afficherAlerteModal(el, type, msg) {
    el.className = 'alert alert-' + type;
    el.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'times-circle') + '"></i> ' + msg;
    el.style.display = 'flex';
}
</script>

<?php require_once '../includes/footer.php'; ?>
