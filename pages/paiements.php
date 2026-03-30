<?php
$pageTitle  = 'Paiements';
$activePage = 'paiements';
$root       = '../';
require_once '../includes/header.php';

$db = getDB();
$msg = '';
$msgType = 'success';

// Enregistrement paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    if ($action === 'create') {
        $cpId       = (int)($_POST['colis_proprietaire_id'] ?? 0);
        $montant    = (float)($_POST['montant'] ?? 0);
        $datePmt    = $_POST['date_paiement'] ?? date('Y-m-d');
        $mode       = $_POST['mode_paiement'] ?? 'espece';
        $reference  = sanitize($_POST['reference'] ?? '');
        $notes      = sanitize($_POST['notes'] ?? '');

        if (!$cpId || $montant <= 0) {
            $msg = 'Veuillez renseigner un montant valide.';
            $msgType = 'danger';
        } else {
            $stmtCP = $db->prepare("SELECT cp.*, cl.id as client_id FROM colis_proprietaires cp JOIN clients cl ON cp.client_id = cl.id WHERE cp.id=?");
            $stmtCP->execute([$cpId]);
            $cp = $stmtCP->fetch();

            if (!$cp) {
                $msg = 'Ligne introuvable.';
                $msgType = 'danger';
            } else {
                // Calculer ce qui a déjà été payé pour cette ligne
                $stmtDejaP = $db->prepare("SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE colis_proprietaire_id=?");
                $stmtDejaP->execute([$cpId]);
                $dejaePaye = (float)$stmtDejaP->fetchColumn();

                $montantDu     = (float)$cp['montant_du'];
                $soldeRestant  = $montantDu - $dejaePaye;

                // Bloquer si le paiement dépasse le solde restant dû
                if ($montantDu > 0 && $montant > $soldeRestant + 0.01) {
                    if ($soldeRestant <= 0) {
                        $msg = 'Ce colis est déjà intégralement payé. Aucun paiement supplémentaire n\'est accepté.';
                    } else {
                        $msg = sprintf(
                            'Paiement excessif : le solde restant dû est de <strong>%s FCFA</strong>. Vous ne pouvez pas enregistrer un paiement supérieur.',
                            number_format($soldeRestant, 0, ',', ' ')
                        );
                    }
                    $msgType = 'danger';
                } else {
                    $db->prepare("INSERT INTO paiements (client_id, colis_proprietaire_id, montant, date_paiement, mode_paiement, reference, notes) VALUES (?,?,?,?,?,?,?)")
                       ->execute([$cp['client_id'], $cpId, $montant, $datePmt, $mode, $reference, $notes]);

                    // Mettre à jour montant_paye et statut
                    $totalPaye = $db->prepare("SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE colis_proprietaire_id=?");
                    $totalPaye->execute([$cpId]);
                    $tp = (float)$totalPaye->fetchColumn();

                    $statut = 'non_paye';
                    if ($tp >= $montantDu && $montantDu > 0) $statut = 'paye';
                    elseif ($tp > 0) $statut = 'partiel';

                    $db->prepare("UPDATE colis_proprietaires SET montant_paye=?, statut=? WHERE id=?")
                       ->execute([$tp, $statut, $cpId]);

                    $msg = 'Paiement de ' . number_format($montant, 0, ',', ' ') . ' FCFA enregistré avec succès.';
                }
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM paiements WHERE id=?")->execute([$id]);
        $msg = 'Paiement supprimé.';
    }
}

// FILTRES
$filterClient = (int)($_GET['client_id'] ?? 0);
$filterCpId   = (int)($_GET['cp_id'] ?? 0);
$filterDate   = $_GET['date'] ?? '';
$filterMode   = $_GET['mode'] ?? '';
$clientNom    = $_GET['client'] ?? '';

$where  = '1=1';
$params = [];
if ($filterClient) { $where .= ' AND p.client_id=?'; $params[] = $filterClient; }
if ($filterCpId)   { $where .= ' AND p.colis_proprietaire_id=?'; $params[] = $filterCpId; }
if ($filterDate)   { $where .= ' AND p.date_paiement=?'; $params[] = $filterDate; }
if ($filterMode)   { $where .= ' AND p.mode_paiement=?'; $params[] = $filterMode; }

$stmtPmt = $db->prepare("
    SELECT p.*, cl.nom as client_nom, c.code_complet, cp.montant_du, cp.solde
    FROM paiements p
    JOIN clients cl ON p.client_id = cl.id
    JOIN colis_proprietaires cp ON p.colis_proprietaire_id = cp.id
    JOIN colis c ON cp.colis_id = c.id
    WHERE $where
    ORDER BY p.date_paiement DESC, p.id DESC
");
$stmtPmt->execute($params);
$paiements = $stmtPmt->fetchAll();

// Total filtré
$totalFiltré = array_sum(array_column($paiements, 'montant'));

// Pour modal : liste colis_proprietaires non soldés
$stmtCP = $db->query("
    SELECT cp.id, cp.montant_du, cp.montant_paye, cp.solde, cp.statut,
           cl.nom as client_nom, c.code_complet
    FROM colis_proprietaires cp
    JOIN clients cl ON cp.client_id = cl.id
    JOIN colis c ON cp.colis_id = c.id
    WHERE cp.statut != 'paye'
    ORDER BY c.code_complet
");
$cpList = $stmtCP->fetchAll();

$clients = $db->query("SELECT id, nom FROM clients ORDER BY nom")->fetchAll();
?>
<div class="page-content">
    <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>" data-auto-dismiss="4000">
            <i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'times-circle' ?>"></i> <?= $msg ?>
        </div>
    <?php endif; ?>

    <?php if ($filterCpId && $clientNom): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            Affichage des paiements pour <strong><?= sanitize($clientNom) ?></strong>
            <a href="paiements.php" style="margin-left:10px" class="btn btn-sm btn-outline">Voir tout</a>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

        <!-- LISTE PAIEMENTS -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-money-bill-wave"></i> Historique des paiements</div>
                <div style="display:flex;gap:8px">
                    <button class="btn btn-primary btn-sm" onclick="openModal('modalPaiement')">
                        <i class="fas fa-plus"></i> Nouveau paiement
                    </button>
                    <button class="btn btn-outline btn-sm" onclick="exportTableToCSV('tablePaiements','paiements')">
                        <i class="fas fa-file-csv"></i> CSV
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Filtres -->
                <form method="get" class="filters-bar">
                    <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>" class="form-control" style="width:150px">
                    <select name="client_id" class="form-control" style="width:180px">
                        <option value="">Tous les clients</option>
                        <?php foreach ($clients as $cl): ?>
                            <option value="<?= $cl['id'] ?>" <?= $filterClient == $cl['id'] ? 'selected' : '' ?>>
                                <?= sanitize($cl['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="mode" class="form-control">
                        <option value="">Tous modes</option>
                        <option value="espece" <?= $filterMode === 'espece' ? 'selected' : '' ?>>Espèce</option>
                        <option value="virement" <?= $filterMode === 'virement' ? 'selected' : '' ?>>Virement</option>
                        <option value="mobile_money" <?= $filterMode === 'mobile_money' ? 'selected' : '' ?>>Mobile Money</option>
                        <option value="cheque" <?= $filterMode === 'cheque' ? 'selected' : '' ?>>Chèque</option>
                    </select>
                    <button type="submit" class="btn btn-outline"><i class="fas fa-filter"></i> Filtrer</button>
                    <a href="paiements.php" class="btn btn-outline"><i class="fas fa-times"></i></a>
                </form>

                <?php if ($paiements): ?>
                    <div style="margin-bottom:12px;font-size:13px;color:var(--gray-500)">
                        Total affiché : <strong style="color:var(--success)"><?= number_format($totalFiltré, 0, ',', ' ') ?> FCFA</strong>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table id="tablePaiements">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Client</th>
                                <th>Colis</th>
                                <th>Montant payé</th>
                                <th>Solde restant</th>
                                <th>Mode</th>
                                <th>Référence</th>
                                <th class="no-export">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($paiements): ?>
                                <?php foreach ($paiements as $p): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($p['date_paiement'])) ?></td>
                                    <td><?= sanitize($p['client_nom']) ?></td>
                                    <td><span class="colis-code"><?= sanitize($p['code_complet']) ?></span></td>
                                    <td style="font-weight:700;color:var(--success)">
                                        <?= number_format((float)$p['montant'], 0, ',', ' ') ?> FCFA
                                    </td>
                                    <td style="color:<?= (float)$p['solde'] > 0 ? 'var(--danger)' : 'var(--success)' ?>;font-weight:700">
                                        <?= number_format((float)$p['solde'], 0, ',', ' ') ?> FCFA
                                    </td>
                                    <td>
                                        <span class="badge badge-gray">
                                            <?= ucfirst(str_replace('_', ' ', $p['mode_paiement'])) ?>
                                        </span>
                                    </td>
                                    <td><?= sanitize($p['reference'] ?: '-') ?></td>
                                    <td class="no-export">
                                        <div class="table-actions">
                                            <button class="btn-icon view" data-tooltip="Reçu PDF"
                                                onclick="genererRecu(<?= htmlspecialchars(json_encode($p)) ?>)">
                                                <i class="fas fa-file-pdf"></i>
                                            </button>
                                            <form method="post" style="display:inline" onsubmit="return confirmDelete('Supprimer ce paiement ?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                                <button type="submit" class="btn-icon delete"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8">
                                    <div class="empty-state">
                                        <i class="fas fa-money-bill-wave"></i>
                                        <h3>Aucun paiement</h3>
                                    </div>
                                </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- PANEL DETTES EN COURS -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-exclamation-circle"></i> Dettes en cours</div>
            </div>
            <div class="card-body" style="padding:10px 0 0">
                <ul class="paiement-timeline" style="padding:0 16px">
                    <?php foreach ($cpList as $cp): ?>
                    <li>
                        <div class="paiement-dot"><i class="fas fa-user"></i></div>
                        <div class="paiement-info">
                            <div style="font-weight:600"><?= sanitize($cp['client_nom']) ?></div>
                            <div class="paiement-date"><?= sanitize($cp['code_complet']) ?></div>
                            <div class="paiement-montant" style="color:var(--danger)">
                                Solde : <?= number_format((float)$cp['solde'], 0, ',', ' ') ?> FCFA
                            </div>
                            <button class="btn btn-primary btn-sm" style="margin-top:5px"
                                onclick="prefillPaiement(<?= $cp['id'] ?>, '<?= sanitize($cp['client_nom']) ?>', '<?= sanitize($cp['code_complet']) ?>', <?= (float)$cp['solde'] ?>)">
                                <i class="fas fa-plus"></i> Payer
                            </button>
                        </div>
                    </li>
                    <?php endforeach; ?>
                    <?php if (!$cpList): ?>
                    <li style="padding:20px;text-align:center;color:var(--success)">
                        <i class="fas fa-check-circle fa-2x"></i><br>
                        <span>Aucune dette en cours</span>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PAIEMENT -->
<div class="modal-overlay" id="modalPaiement">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-money-bill-wave"></i> Enregistrer un paiement</div>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Colis / Propriétaire <span class="required">*</span></label>
                    <select name="colis_proprietaire_id" id="pmt_cp" class="form-control" required
                            onchange="majSoldeModal(this)">
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($cpList as $cp): ?>
                            <option value="<?= $cp['id'] ?>"
                                    data-solde="<?= (float)$cp['solde'] ?>"
                                    data-montant-du="<?= (float)$cp['montant_du'] ?>">
                                <?= sanitize($cp['code_complet']) ?> - <?= sanitize($cp['client_nom']) ?>
                                (Solde: <?= number_format((float)$cp['solde'], 0, ',', ' ') ?> FCFA)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Montant payé <span class="required">*</span></label>
                        <input type="number" name="montant" id="pmt_montant" class="form-control" min="1" required
                               oninput="validerMontantPaiement(this)">
                        <div id="pmt_montant_info" style="font-size:12px;color:var(--gray-500);margin-top:4px"></div>
                        <div id="pmt_montant_error" class="form-error" style="display:none"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date</label>
                        <input type="date" name="date_paiement" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Mode de paiement</label>
                        <select name="mode_paiement" class="form-control">
                            <option value="espece">Espèce</option>
                            <option value="virement">Virement</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="cheque">Chèque</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Référence</label>
                        <input type="text" name="reference" class="form-control" placeholder="N° reçu, virement...">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline modal-close">Annuler</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
let pmtSoldeMax = 0;

function majSoldeModal(sel) {
    const opt = sel.options[sel.selectedIndex];
    pmtSoldeMax = parseFloat(opt.getAttribute('data-solde') || 0);
    const montantDu = parseFloat(opt.getAttribute('data-montant-du') || 0);

    const infoEl = document.getElementById('pmt_montant_info');
    if (infoEl) {
        if (pmtSoldeMax > 0) {
            infoEl.textContent = 'Solde restant : ' + parseInt(pmtSoldeMax).toLocaleString('fr-FR') + ' FCFA (montant total du : ' + parseInt(montantDu).toLocaleString('fr-FR') + ' FCFA)';
            infoEl.style.color = 'var(--gray-500)';
        } else if (montantDu > 0) {
            infoEl.textContent = 'Ce colis est deja integralement regle.';
            infoEl.style.color = 'var(--success)';
        } else {
            infoEl.textContent = '';
        }
    }

    const montantEl = document.getElementById('pmt_montant');
    if (montantEl && pmtSoldeMax > 0) {
        montantEl.max = Math.round(pmtSoldeMax);
        montantEl.value = Math.round(pmtSoldeMax);
    }
    validerMontantPaiement(montantEl);
}

function validerMontantPaiement(input) {
    if (!input) return;
    const val = parseFloat(input.value || 0);
    const errEl = document.getElementById('pmt_montant_error');
    const btnSubmit = document.querySelector('#modalPaiement [type="submit"]');

    if (pmtSoldeMax > 0 && val > pmtSoldeMax + 0.01) {
        input.style.borderColor = 'var(--danger)';
        if (errEl) {
            errEl.textContent = 'Montant trop eleve : maximum autorise ' + parseInt(pmtSoldeMax).toLocaleString('fr-FR') + ' FCFA';
            errEl.style.display = 'block';
        }
        if (btnSubmit) btnSubmit.disabled = true;
    } else {
        input.style.borderColor = '';
        if (errEl) errEl.style.display = 'none';
        if (btnSubmit) btnSubmit.disabled = false;
    }
}

function prefillPaiement(cpId, clientNom, codeComplet, solde) {
    const sel = document.getElementById('pmt_cp');
    if (sel) {
        sel.value = cpId;
        majSoldeModal(sel);
    } else {
        document.getElementById('pmt_montant').value = Math.round(solde);
    }
    openModal('modalPaiement');
}

function genererRecu(data) {
    exportRecuPDF({
        client    : data.client_nom,
        colis     : data.code_complet,
        montant   : parseInt(data.montant).toLocaleString('fr-FR') + ' FCFA',
        mode      : data.mode_paiement,
        date      : new Date(data.date_paiement).toLocaleDateString('fr-FR'),
        reference : data.reference || ('PMT-' + data.id),
        solde     : parseInt(data.solde).toLocaleString('fr-FR') + ' FCFA'
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
