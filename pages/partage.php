<?php
$pageTitle  = 'Partage des colis';
$activePage = 'partage';
$root       = '../';
require_once '../includes/header.php';

$db = getDB();

$arrivageId = (int)($_GET['arrivage_id'] ?? 0);
$transId    = (int)($_GET['trans_id'] ?? 0);
$modeDetail = $arrivageId && $transId;

// ============================================================
// VUE DÉTAIL : colis d'un transitaire pour un arrivage
// ============================================================
if ($modeDetail) {
    $stmtInfo = $db->prepare("
        SELECT a.date_arrivee, t.nom AS trans_nom
        FROM arrivages a, transitaires t
        WHERE a.id = ? AND t.id = ?
    ");
    $stmtInfo->execute([$arrivageId, $transId]);
    $info = $stmtInfo->fetch();
    if (!$info) {
        header('Location: partage.php'); exit;
    }

    $stmtColis = $db->prepare("
        SELECT c.id, c.code_complet, c.type, c.poids, c.montant,
               COUNT(r.id) AS nb_repartitions,
               COALESCE(SUM(r.poids), 0) AS poids_reparti,
               SUM(CASE WHEN r.statut=1 THEN 1 ELSE 0 END) AS nb_payes
        FROM colis c
        LEFT JOIN repartitions r ON r.colis_id = c.id
        WHERE c.arrivage_id = ? AND c.transitaire_id = ?
        GROUP BY c.id
        ORDER BY c.code_complet
    ");
    $stmtColis->execute([$arrivageId, $transId]);
    $colisListe = $stmtColis->fetchAll();

    $clients    = $db->query("SELECT id, nom FROM clients ORDER BY nom")->fetchAll();
    $clientsJson = json_encode($clients, JSON_UNESCAPED_UNICODE);
}

// ============================================================
// VUE LISTE : groupes date + transitaire
// ============================================================
if (!$modeDetail) {
    $groupes = $db->query("
        SELECT
            a.id AS arrivage_id,
            a.date_arrivee,
            t.id AS trans_id,
            t.nom AS trans_nom,
            COUNT(c.id) AS nb_colis,
            COALESCE(SUM(c.poids), 0) AS poids_total,
            SUM(CASE WHEN (SELECT COUNT(*) FROM repartitions WHERE colis_id = c.id) > 0 THEN 1 ELSE 0 END) AS nb_reparti,
            SUM(CASE WHEN (SELECT COUNT(*) FROM repartitions rr WHERE rr.colis_id = c.id AND rr.statut = 1) > 0 AND
                          (SELECT COUNT(*) FROM repartitions WHERE colis_id = c.id) =
                          (SELECT COUNT(*) FROM repartitions WHERE colis_id = c.id AND statut = 1) THEN 1 ELSE 0 END) AS nb_tout_paye
        FROM colis c
        JOIN arrivages a ON c.arrivage_id = a.id
        LEFT JOIN transitaires t ON c.transitaire_id = t.id
        GROUP BY a.id, t.id
        ORDER BY a.date_arrivee DESC, t.nom
    ")->fetchAll();
}
?>
<div class="page-content">

<?php if ($modeDetail): ?>
<!-- ================================================
     VUE DÉTAIL : liste des colis du groupe
     ================================================ -->

<div class="detail-breadcrumb">
    <a href="partage.php" class="breadcrumb-back">
        <i class="fas fa-arrow-left"></i> Retour aux groupes
    </a>
    <span class="breadcrumb-sep">/</span>
    <span class="breadcrumb-date"><?= date('d/m/Y', strtotime($info['date_arrivee'])) ?></span>
    <span class="breadcrumb-sep">/</span>
    <span class="breadcrumb-trans"><?= sanitize($info['trans_nom']) ?></span>
</div>

<div class="detail-header-card">
    <div class="detail-header-left">
        <div class="detail-icon"><i class="fas fa-shipping-fast"></i></div>
        <div>
            <div class="detail-trans-name"><?= sanitize($info['trans_nom']) ?></div>
            <div class="detail-meta">
                <i class="fas fa-calendar-day"></i>
                <?= date('d/m/Y', strtotime($info['date_arrivee'])) ?>
                &nbsp;·&nbsp;
                <i class="fas fa-boxes"></i>
                <?= count($colisListe) ?> colis
            </div>
        </div>
    </div>
    <a href="fiche.php?arrivage_id=<?= $arrivageId ?>" class="btn btn-outline btn-sm">
        <i class="fas fa-table"></i> Voir fiche complète
    </a>
</div>

<?php if (empty($colisListe)): ?>
<div class="card"><div class="card-body">
    <div class="empty-state"><i class="fas fa-boxes"></i><h3>Aucun colis</h3></div>
</div></div>
<?php else: ?>

<div id="partage-alert-global" style="display:none;margin-bottom:12px"></div>

<div class="colis-detail-grid">
    <?php foreach ($colisListe as $c): ?>
    <?php
        $nbRep   = (int)$c['nb_repartitions'];
        $nbPaye  = (int)$c['nb_payes'];
        $pRep    = (float)$c['poids_reparti'];
        $pTotal  = (float)$c['poids'];
        $pct     = $pTotal > 0 ? round(($pRep / $pTotal) * 100) : 0;
        if ($nbRep === 0) {
            $statClass = 'status-pending'; $statLabel = 'Non réparti';
        } elseif ($nbPaye === $nbRep) {
            $statClass = 'status-done'; $statLabel = 'Tout payé';
        } elseif ($nbPaye > 0) {
            $statClass = 'status-partial'; $statLabel = 'Partiel';
        } else {
            $statClass = 'status-unpaid'; $statLabel = 'Non payé';
        }
    ?>
    <div class="colis-detail-card" id="card-<?= $c['id'] ?>">
        <div class="cdc-header">
            <div class="cdc-header-left">
                <span class="cdc-code"><?= sanitize($c['code_complet']) ?></span>
                <span class="cdc-type <?= $c['type'] === 'mixte' ? 'type-mixte' : 'type-indiv' ?>">
                    <?= $c['type'] === 'mixte' ? 'MIX' : 'IND' ?>
                </span>
            </div>
            <span class="cdc-status <?= $statClass ?>"><?= $statLabel ?></span>
        </div>

        <div class="cdc-poids-bar">
            <div class="cdc-poids-info">
                <span><i class="fas fa-weight-hanging"></i> <?= number_format($pTotal, 2) ?> kg total</span>
                <?php if ($nbRep > 0): ?>
                <span style="color:var(--gray-500)"><?= number_format($pRep, 2) ?> kg répartis</span>
                <?php endif; ?>
            </div>
            <?php if ($c['type'] === 'mixte' && $nbRep > 0): ?>
            <div class="progress-bar-outer" style="margin-top:4px">
                <div class="progress-bar-inner" style="width:<?= $pct ?>%;background:<?= $pct >= 100 ? 'var(--success)' : 'var(--primary-light)' ?>"></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Zone répartitions (chargée dynamiquement) -->
        <div class="cdc-repartitions" id="reps-<?= $c['id'] ?>">
            <?php if ($nbRep > 0): ?>
            <div class="reps-loading-hint"><i class="fas fa-spinner fa-spin"></i> Chargement...</div>
            <?php else: ?>
            <div class="reps-empty">Aucun client assigné pour l'instant</div>
            <?php endif; ?>
        </div>

        <div class="cdc-footer">
            <button class="btn btn-primary btn-sm" onclick="ouvrirPartage(<?= htmlspecialchars(json_encode([
                'id'      => $c['id'],
                'code_complet' => $c['code_complet'],
                'type'    => $c['type'],
                'poids'   => $pTotal,
                'montant' => (float)$c['montant'],
            ])) ?>)">
                <i class="fas fa-edit"></i>
                <?= $nbRep > 0 ? 'Modifier la répartition' : 'Répartir ce colis' ?>
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ================================================
     VUE LISTE : groupes date + transitaire
     ================================================ -->

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-share-alt"></i> Partage des colis</div>
    </div>
    <div class="card-body" style="padding:0">

        <?php if (empty($groupes)): ?>
        <div class="empty-state" style="padding:48px">
            <i class="fas fa-boxes"></i>
            <h3>Aucun colis</h3>
            <p>Enregistrez un arrivage pour commencer.</p>
        </div>
        <?php else: ?>

        <?php
            $lastDate = null;
        ?>
        <?php foreach ($groupes as $g): ?>
        <?php
            $date = $g['date_arrivee'];
            $nbC  = (int)$g['nb_colis'];
            $nbR  = (int)$g['nb_reparti'];
            $nbP  = (int)$g['nb_tout_paye'];
            $pct  = $nbC > 0 ? round(($nbR / $nbC) * 100) : 0;

            if ($nbR === 0)        { $stCls = 'gr-pending'; $stLbl = 'À répartir'; }
            elseif ($nbR < $nbC)   { $stCls = 'gr-partial'; $stLbl = $nbR.'/'.$nbC.' répartis'; }
            else                   { $stCls = 'gr-done';    $stLbl = 'Tout réparti'; }
        ?>

        <?php if ($date !== $lastDate): ?>
            <?php if ($lastDate !== null): ?>
                <div style="height:1px;background:var(--gray-200)"></div>
            <?php endif; ?>
            <div class="groupe-date-separator">
                <i class="fas fa-calendar-day"></i>
                <?= date('l d/m/Y', strtotime($date)) ?>
            </div>
            <?php $lastDate = $date; ?>
        <?php endif; ?>

        <a href="partage.php?arrivage_id=<?= $g['arrivage_id'] ?>&trans_id=<?= $g['trans_id'] ?>"
           class="groupe-row">
            <div class="gr-icon">
                <i class="fas fa-shipping-fast"></i>
            </div>
            <div class="gr-main">
                <div class="gr-trans-name"><?= sanitize($g['trans_nom'] ?? '—') ?></div>
                <div class="gr-meta">
                    <span><i class="fas fa-boxes"></i> <?= $nbC ?> colis</span>
                    <span><i class="fas fa-weight-hanging"></i> <?= number_format((float)$g['poids_total'], 1) ?> kg</span>
                    <?php if ($nbR > 0): ?>
                    <span><i class="fas fa-users"></i> <?= $nbR ?> réparti<?= $nbR > 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="gr-right">
                <span class="gr-status <?= $stCls ?>"><?= $stLbl ?></span>
                <div class="gr-progress">
                    <div class="progress-bar-outer" style="width:80px">
                        <div class="progress-bar-inner" style="width:<?= $pct ?>%;background:<?= $pct >= 100 ? 'var(--success)' : 'var(--primary-light)' ?>"></div>
                    </div>
                    <span class="gr-pct"><?= $pct ?>%</span>
                </div>
                <i class="fas fa-chevron-right gr-arrow"></i>
            </div>
        </a>
        <?php endforeach; ?>

        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
</div>

<!-- ================================================
     MODAL RÉPARTITION
     ================================================ -->
<?php if ($modeDetail): ?>
<div class="modal-overlay" id="modalPartage">
    <div class="modal modal-xl">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-share-alt"></i>
                <span id="partage-code" class="colis-code"></span>
                <span id="partage-type-badge" style="margin-left:8px"></span>
            </div>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="partage-alert" style="display:none"></div>

            <div style="display:flex;gap:20px;margin-bottom:16px;padding:12px 16px;background:var(--gray-50);border-radius:var(--radius);border:1px solid var(--gray-200);flex-wrap:wrap">
                <div>
                    <div style="font-size:11px;color:var(--gray-500);text-transform:uppercase">Poids total</div>
                    <div style="font-size:20px;font-weight:700;color:var(--primary)" id="partage-poids-total">—</div>
                </div>
                <div>
                    <div style="font-size:11px;color:var(--gray-500);text-transform:uppercase">Montant total</div>
                    <div style="font-size:20px;font-weight:700;color:var(--gray-900)" id="partage-montant-total">—</div>
                </div>
                <div id="partage-restant-wrap" style="display:none">
                    <div style="font-size:11px;color:var(--gray-500);text-transform:uppercase">Poids restant</div>
                    <div style="font-size:20px;font-weight:700" id="partage-poids-restant">—</div>
                </div>
            </div>

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
            <button type="button" class="btn btn-outline btn-sm" onclick="ajouterLignePartage()"
                    style="margin-top:10px" id="btn-ajouter-client">
                <i class="fas fa-plus"></i> Ajouter un client
            </button>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline modal-close">Annuler</button>
            <button type="button" class="btn btn-primary" onclick="sauvegarderPartage()">
                <i class="fas fa-save"></i> Enregistrer
            </button>
        </div>
    </div>
</div>

<script>
const CLIENTS_DATA = <?= $clientsJson ?? '[]' ?>;
let currentColis = null;
let partageCounter = 0;

document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($colisListe as $c): ?>
    <?php if ((int)$c['nb_repartitions'] > 0): ?>
    chargerRepartitions(<?= $c['id'] ?>);
    <?php else: ?>
    document.getElementById('reps-<?= $c['id'] ?>').innerHTML =
        '<div class="reps-empty">Aucun client assigné</div>';
    <?php endif; ?>
    <?php endforeach; ?>
});

function chargerRepartitions(colisId) {
    const zone = document.getElementById('reps-' + colisId);
    fetch('../api/repartitions.php?colis_id=' + colisId)
        .then(r => r.json())
        .then(data => {
            if (!data.length) {
                zone.innerHTML = '<div class="reps-empty">Aucun client assigné</div>';
                return;
            }
            let html = '<div class="reps-mini-table"><table>';
            html += '<thead><tr><th>Client</th><th>Poids</th><th>Montant</th><th>Statut</th></tr></thead><tbody>';
            data.forEach(function(r, i) {
                const statut = r.statut == 1
                    ? '<span class="fiche-check">✓</span>'
                    : '<span class="fiche-cross">✗</span>';
                html += `<tr>
                    <td><strong>${r.client_nom}</strong></td>
                    <td>${parseFloat(r.poids).toFixed(2)} kg</td>
                    <td>${parseInt(r.montant || 0).toLocaleString('fr-FR')} FCFA</td>
                    <td style="text-align:center">${statut}</td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            zone.innerHTML = html;
        })
        .catch(function() {
            zone.innerHTML = '<div class="reps-empty" style="color:var(--danger)">Erreur de chargement</div>';
        });
}

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
    document.getElementById('partage-lignes').innerHTML = '';

    const restantWrap = document.getElementById('partage-restant-wrap');
    restantWrap.style.display = colis.type === 'mixte' ? 'block' : 'none';
    document.getElementById('btn-ajouter-client').style.display = colis.type === 'individuel' ? 'none' : '';

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
        <td><select class="form-control" id="pl-client-${lid}" required>${opts}</select></td>
        <td><input type="number" class="form-control pl-poids" id="pl-poids-${lid}"
               value="${poidsVal}" step="0.01" min="0" style="width:100px"
               ${isIndiv ? 'readonly' : ''} oninput="calcMontantLigne(${lid}); majPoidsRestant()"></td>
        <td><input type="number" class="form-control pl-montant" id="pl-montant-${lid}"
               value="${montantVal}" step="1" min="0" style="width:130px"></td>
        <td><select class="form-control pl-statut" id="pl-statut-${lid}" style="width:130px">
               <option value="0" ${statutVal==0?'selected':''}>❌ Non payé</option>
               <option value="1" ${statutVal==1?'selected':''}>✔ Payé</option>
            </select></td>
        <td>${isIndiv ? '' : `<button type="button" class="btn-icon delete" onclick="supprimerLignePartage(${lid})"><i class="fas fa-trash"></i></button>`}</td>
    `;
    tbody.appendChild(tr);
}

function supprimerLignePartage(lid) {
    const row = document.getElementById('partage-ligne-' + lid);
    if (row) { row.remove(); partageCounter = Math.max(0, partageCounter-1); }
    majPoidsRestant();
}

function calcMontantLigne(lid) {
    const poids    = parseFloat(document.getElementById('pl-poids-' + lid)?.value || 0);
    const poidsT   = parseFloat(currentColis.poids || 0);
    const montantT = parseFloat(currentColis.montant || 0);
    if (poidsT > 0 && montantT > 0) {
        const el = document.getElementById('pl-montant-' + lid);
        if (el) el.value = Math.round((poids / poidsT) * montantT);
    }
}

function majPoidsRestant() {
    if (!currentColis || currentColis.type !== 'mixte') return;
    const poidsTotal = parseFloat(currentColis.poids || 0);
    let sum = 0;
    document.querySelectorAll('.pl-poids').forEach(el => sum += parseFloat(el.value || 0));
    const restant = poidsTotal - sum;
    const el = document.getElementById('partage-poids-restant');
    if (el) {
        el.textContent = restant.toFixed(2) + ' kg';
        el.style.color = restant < -0.001 ? 'var(--danger)' : restant > 0.001 ? 'var(--warning)' : 'var(--success)';
    }
}

async function sauvegarderPartage() {
    const alertEl = document.getElementById('partage-alert');
    const rows = document.querySelectorAll('#partage-lignes tr');
    if (!rows.length) { afficherAlert(alertEl, 'danger', 'Ajoutez au moins un client.'); return; }

    const repartitions = [];
    let valid = true, sumPoids = 0;
    rows.forEach(function(row) {
        const lid = row.id.replace('partage-ligne-', '');
        const clientId = parseInt(document.getElementById('pl-client-'+lid)?.value);
        const poids    = parseFloat(document.getElementById('pl-poids-'+lid)?.value || 0);
        const montant  = parseFloat(document.getElementById('pl-montant-'+lid)?.value || 0);
        const statut   = parseInt(document.getElementById('pl-statut-'+lid)?.value || 0);
        if (!clientId) { valid = false; return; }
        repartitions.push({ client_id: clientId, poids, montant, statut });
        sumPoids += poids;
    });

    if (!valid) { afficherAlert(alertEl, 'danger', 'Sélectionnez un client pour chaque ligne.'); return; }

    const poidsTotal = parseFloat(currentColis.poids || 0);
    if (poidsTotal > 0 && Math.abs(sumPoids - poidsTotal) > 0.01) {
        afficherAlert(alertEl, 'danger',
            `Somme des poids (${sumPoids.toFixed(2)} kg) ≠ poids du colis (${poidsTotal.toFixed(2)} kg)`);
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
            afficherAlert(alertEl, 'success', 'Répartition enregistrée !');
            closeModal('modalPartage');
            chargerRepartitions(currentColis.id);
            updateCardStatus(currentColis.id, repartitions);
            showToast('Répartition enregistrée avec succès', 'success');
        } else {
            afficherAlert(alertEl, 'danger', data.error || 'Erreur inconnue');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Enregistrer';
        }
    } catch(e) {
        afficherAlert(alertEl, 'danger', 'Erreur réseau.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Enregistrer';
    }
}

function updateCardStatus(colisId, reps) {
    const card = document.getElementById('card-' + colisId);
    if (!card) return;
    const nbPaye = reps.filter(r => r.statut == 1).length;
    const statusEl = card.querySelector('.cdc-status');
    if (!statusEl) return;
    if (nbPaye === reps.length) {
        statusEl.className = 'cdc-status status-done'; statusEl.textContent = 'Tout payé';
    } else if (nbPaye > 0) {
        statusEl.className = 'cdc-status status-partial'; statusEl.textContent = 'Partiel';
    } else {
        statusEl.className = 'cdc-status status-unpaid'; statusEl.textContent = 'Non payé';
    }
    const btn = card.querySelector('.btn-primary');
    if (btn) btn.innerHTML = '<i class="fas fa-edit"></i> Modifier la répartition';
}

function afficherAlert(el, type, msg) {
    el.className = 'alert alert-' + type;
    el.innerHTML = '<i class="fas fa-' + (type==='success'?'check-circle':'times-circle') + '"></i> ' + msg;
    el.style.display = 'flex';
}
</script>
<?php endif; ?>

<style>
/* ================================================
   PARTAGE — Vue liste (groupes)
   ================================================ */
.groupe-date-separator {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px 6px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .8px;
    color: var(--gray-500);
    background: var(--gray-50);
    border-bottom: 1px solid var(--gray-100);
}
.groupe-row {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 14px 20px;
    border-bottom: 1px solid var(--gray-100);
    text-decoration: none;
    color: inherit;
    transition: background .15s;
    cursor: pointer;
}
.groupe-row:last-child { border-bottom: none; }
.groupe-row:hover { background: var(--gray-50); }

.gr-icon {
    width: 42px; height: 42px;
    border-radius: var(--radius);
    background: #eff6ff;
    color: var(--primary);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.gr-main { flex: 1; min-width: 0; }
.gr-trans-name {
    font-size: 15px;
    font-weight: 600;
    color: var(--gray-900);
    margin-bottom: 4px;
}
.gr-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    font-size: 12px;
    color: var(--gray-500);
}
.gr-meta i { margin-right: 4px; }

.gr-right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}
.gr-status {
    font-size: 12px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 99px;
    white-space: nowrap;
}
.gr-pending { background:#fef9c3; color:#92400e; }
.gr-partial { background:#e0f2fe; color:#0369a1; }
.gr-done    { background:#dcfce7; color:#15803d; }

.gr-progress { display: flex; align-items: center; gap: 6px; }
.gr-pct { font-size: 12px; color: var(--gray-500); width: 32px; text-align: right; }
.gr-arrow { color: var(--gray-300); font-size: 13px; }

/* ================================================
   PARTAGE — Vue détail (colis)
   ================================================ */
.detail-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    font-size: 13px;
    color: var(--gray-500);
}
.breadcrumb-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--primary);
    font-weight: 500;
    text-decoration: none;
}
.breadcrumb-back:hover { text-decoration: underline; }
.breadcrumb-sep { color: var(--gray-300); }
.breadcrumb-date, .breadcrumb-trans { font-weight: 600; color: var(--gray-700); }

.detail-header-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 16px 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.detail-header-left { display: flex; align-items: center; gap: 14px; }
.detail-icon {
    width: 48px; height: 48px;
    border-radius: var(--radius);
    background: var(--primary);
    color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}
.detail-trans-name { font-size: 18px; font-weight: 700; color: var(--gray-900); }
.detail-meta { font-size: 13px; color: var(--gray-500); margin-top: 3px; display:flex; gap:12px; }
.detail-meta i { margin-right: 4px; color: var(--primary); }

/* Grille des cartes colis */
.colis-detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
}

.colis-detail-card {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.cdc-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 14px;
    background: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
}
.cdc-header-left { display: flex; align-items: center; gap: 8px; }
.cdc-code {
    font-family: 'Courier New', monospace;
    font-size: 14px;
    font-weight: 700;
    color: var(--primary-dark);
    background: white;
    border: 1px solid var(--gray-200);
    padding: 2px 8px;
    border-radius: 4px;
}
.cdc-type {
    font-size: 10px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.type-mixte { background: #e0f2fe; color: #0369a1; }
.type-indiv { background: #dbeafe; color: var(--primary-dark); }

.cdc-status {
    font-size: 11px;
    font-weight: 600;
    padding: 3px 9px;
    border-radius: 99px;
}
.status-pending { background:#fef9c3; color:#92400e; }
.status-done    { background:#dcfce7; color:#15803d; }
.status-partial { background:#e0f2fe; color:#0369a1; }
.status-unpaid  { background:#fee2e2; color:#b91c1c; }

.cdc-poids-bar {
    padding: 8px 14px;
    border-bottom: 1px solid var(--gray-100);
    font-size: 12px;
    color: var(--gray-500);
}
.cdc-poids-info { display: flex; justify-content: space-between; }

.cdc-repartitions {
    flex: 1;
    padding: 0;
}
.reps-empty {
    padding: 16px 14px;
    font-size: 12px;
    color: var(--gray-400);
    text-align: center;
    font-style: italic;
}
.reps-mini-table { padding: 0; }
.reps-mini-table table { font-size: 12px; }
.reps-mini-table thead th {
    padding: 6px 10px;
    background: var(--gray-50);
    font-size: 11px;
    font-weight: 600;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: .3px;
    border-bottom: 1px solid var(--gray-100);
}
.reps-mini-table tbody td {
    padding: 7px 10px;
    border-bottom: 1px solid var(--gray-100);
}
.reps-mini-table tbody tr:last-child td { border-bottom: none; }
.reps-mini-table .fiche-check { color: var(--success); font-weight: 700; font-size: 15px; }
.reps-mini-table .fiche-cross { color: var(--danger); opacity: .5; font-size: 13px; }

.cdc-footer {
    padding: 10px 14px;
    border-top: 1px solid var(--gray-100);
    background: var(--gray-50);
}
.cdc-footer .btn { width: 100%; justify-content: center; }
</style>

<?php require_once '../includes/footer.php'; ?>
