<?php
$pageTitle  = 'Colis';
$activePage = 'colis';
$root       = '../';
require_once '../includes/header.php';

$db = getDB();
$msg = '';
$msgType = 'success';

// ============================================================
// TRAITEMENT FORMULAIRE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $arrivage_id = (int)($_POST['arrivage_id'] ?? 0);
        $code_reel   = strtoupper(trim($_POST['code_reel'] ?? ''));
        $type        = $_POST['type'] ?? 'individuel';
        $poids       = (float)($_POST['poids'] ?? 0);
        $montant     = (float)($_POST['montant'] ?? 0);

        if (!$arrivage_id || !$code_reel) {
            $msg = 'Arrivage et code colis sont obligatoires.';
            $msgType = 'danger';
        } else {
            // Récupérer l'arrivage complet (poids_total, cout_total, date)
            $stmtArr = $db->prepare("SELECT * FROM arrivages WHERE id=?");
            $stmtArr->execute([$arrivage_id]);
            $arrivage = $stmtArr->fetch();

            if (!$arrivage) {
                $msg = 'Arrivage introuvable.';
                $msgType = 'danger';
            } else {
                $dateArrivee = $arrivage['date_arrivee'];
                $codeComplet = genererCodeComplet($code_reel, $dateArrivee);

                // Calculer poids et montant déjà utilisés pour cet arrivage
                // (exclure le colis courant en cas de modification)
                $excludeId = ($action === 'update') ? (int)($_POST['id'] ?? 0) : 0;
                $stmtUsed = $db->prepare("
                    SELECT COALESCE(SUM(poids), 0) as poids_used,
                           COALESCE(SUM(montant), 0) as montant_used
                    FROM colis
                    WHERE arrivage_id = ? AND id != ?
                ");
                $stmtUsed->execute([$arrivage_id, $excludeId]);
                $used = $stmtUsed->fetch();

                $poidsRestant   = (float)$arrivage['poids_total'] - (float)$used['poids_used'];
                $montantRestant = (float)$arrivage['cout_total']  - (float)$used['montant_used'];

                // Validation : poids du colis > poids restant de l'arrivage ?
                // (uniquement si l'arrivage a un poids_total déclaré)
                if ((float)$arrivage['poids_total'] > 0 && $poids > 0 && $poids > $poidsRestant + 0.001) {
                    $msg = sprintf(
                        'Poids invalide : ce colis fait <strong>%.2f kg</strong> mais il ne reste que <strong>%.2f kg</strong> disponible sur cet arrivage (total déclaré : %.2f kg, déjà utilisé : %.2f kg).',
                        $poids, $poidsRestant, (float)$arrivage['poids_total'], (float)$used['poids_used']
                    );
                    $msgType = 'danger';
                }
                // Validation : montant du colis > coût restant de l'arrivage ?
                // (uniquement si l'arrivage a un cout_total déclaré)
                elseif ((float)$arrivage['cout_total'] > 0 && $montant > 0 && $montant > $montantRestant + 0.01) {
                    $msg = sprintf(
                        'Montant invalide : ce colis coûte <strong>%s %s</strong> mais il ne reste que <strong>%s %s</strong> disponible sur cet arrivage (total déclaré : %s %s, déjà utilisé : %s %s).',
                        number_format($montant, 0, ',', ' '), $arrivage['devise'],
                        number_format($montantRestant, 0, ',', ' '), $arrivage['devise'],
                        number_format((float)$arrivage['cout_total'], 0, ',', ' '), $arrivage['devise'],
                        number_format((float)$used['montant_used'], 0, ',', ' '), $arrivage['devise']
                    );
                    $msgType = 'danger';
                }

                // Validation spécifique colis MIXTE : cohérence des propriétaires
                if ($msgType === 'success' && $type === 'mixte' && $action === 'create') {
                    $proprietaires = $_POST['proprietaires'] ?? [];
                    $sumPropPoids   = 0;
                    $sumPropMontant = 0;
                    foreach ($proprietaires as $prop) {
                        $sumPropPoids   += (float)($prop['poids']      ?? 0);
                        $sumPropMontant += (float)($prop['montant_du'] ?? 0);
                    }
                    // La somme des poids des propriétaires ne peut pas dépasser le poids du colis
                    if ($poids > 0 && $sumPropPoids > $poids + 0.001) {
                        $msg = sprintf(
                            'Colis mixte incohérent : la somme des poids des propriétaires (<strong>%.2f kg</strong>) dépasse le poids total du colis (<strong>%.2f kg</strong>).',
                            $sumPropPoids, $poids
                        );
                        $msgType = 'danger';
                    }
                    // La somme des montants dus ne peut pas dépasser le montant du colis
                    elseif ($montant > 0 && $sumPropMontant > $montant + 0.01) {
                        $msg = sprintf(
                            'Colis mixte incohérent : la somme des montants dus aux propriétaires (<strong>%s %s</strong>) dépasse le montant total du colis (<strong>%s %s</strong>).',
                            number_format($sumPropMontant, 0, ',', ' '), $arrivage['devise'],
                            number_format($montant, 0, ',', ' '), $arrivage['devise']
                        );
                        $msgType = 'danger';
                    }
                }

                if ($msgType === 'success' && $action === 'create') {
                    try {
                        $stmt = $db->prepare("INSERT INTO colis (arrivage_id, code_reel, code_complet, type, poids, montant) VALUES (?,?,?,?,?,?)");
                        $stmt->execute([$arrivage_id, $code_reel, $codeComplet, $type, $poids, $montant]);
                        $colisId = $db->lastInsertId();

                        // Traiter les propriétaires
                        $proprietaires = $_POST['proprietaires'] ?? [];
                        foreach ($proprietaires as $prop) {
                            $clientId    = (int)($prop['client_id'] ?? 0);
                            $propPoids   = (float)($prop['poids'] ?? 0);
                            $montantDu   = (float)($prop['montant_du'] ?? 0);
                            $montantPaye = (float)($prop['montant_paye'] ?? 0);
                            if (!$clientId) continue;

                            $statutProp = 'non_paye';
                            if ($montantPaye >= $montantDu && $montantDu > 0) $statutProp = 'paye';
                            elseif ($montantPaye > 0) $statutProp = 'partiel';

                            $stmtProp = $db->prepare("INSERT INTO colis_proprietaires (colis_id, client_id, poids, montant_du, montant_paye, statut) VALUES (?,?,?,?,?,?)");
                            $stmtProp->execute([$colisId, $clientId, $propPoids, $montantDu, $montantPaye, $statutProp]);

                            // Enregistrer paiement si > 0
                            if ($montantPaye > 0) {
                                $cpId = $db->lastInsertId();
                                $stmtPmt = $db->prepare("INSERT INTO paiements (client_id, colis_proprietaire_id, montant, date_paiement, mode_paiement) VALUES (?,?,?,?,?)");
                                $stmtPmt->execute([$clientId, $cpId, $montantPaye, date('Y-m-d'), 'espece']);
                            }
                        }

                        $msg = "Colis <strong>$codeComplet</strong> enregistré avec succès.";
                    } catch (PDOException $e) {
                        $msg = 'Code colis déjà existant ou erreur : ' . $e->getMessage();
                        $msgType = 'danger';
                    }
                } elseif ($msgType === 'success' && $action === 'update') {
                    $id = (int)($_POST['id'] ?? 0);
                    $stmt = $db->prepare("UPDATE colis SET type=?, poids=?, montant=? WHERE id=?");
                    $stmt->execute([$type, $poids, $montant, $id]);
                    $msg = 'Colis modifié avec succès.';
                }
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM colis WHERE id=?")->execute([$id]);
        $msg = 'Colis supprimé.';
    }
}

// ============================================================
// FILTRES
// ============================================================
$filterArrivage = (int)($_GET['arrivage_id'] ?? 0);
$filterType     = $_GET['type'] ?? '';
$filterSearch   = trim($_GET['q'] ?? '');

$where  = '1=1';
$params = [];
if ($filterArrivage) { $where .= ' AND c.arrivage_id = ?'; $params[] = $filterArrivage; }
if ($filterType) { $where .= ' AND c.type = ?'; $params[] = $filterType; }
if ($filterSearch) { $where .= ' AND (c.code_complet LIKE ? OR c.code_reel LIKE ?)'; $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%"; }

$stmtColis = $db->prepare("
    SELECT c.*, a.date_arrivee, t.nom as transitaire_nom,
           COUNT(cp.id) as nb_proprietaires,
           COALESCE(SUM(cp.solde), 0) as total_solde
    FROM colis c
    JOIN arrivages a ON c.arrivage_id = a.id
    JOIN transitaires t ON a.transitaire_id = t.id
    LEFT JOIN colis_proprietaires cp ON cp.colis_id = c.id
    WHERE $where
    GROUP BY c.id
    ORDER BY a.date_arrivee DESC, c.id DESC
");
$stmtColis->execute($params);
$colisListe = $stmtColis->fetchAll();

$arrivages  = $db->query("
    SELECT a.id, a.date_arrivee, a.poids_total, a.cout_total, a.devise, t.nom as transitaire_nom,
           COALESCE((SELECT SUM(poids)   FROM colis WHERE arrivage_id = a.id), 0) as poids_used,
           COALESCE((SELECT SUM(montant) FROM colis WHERE arrivage_id = a.id), 0) as montant_used
    FROM arrivages a
    JOIN transitaires t ON a.transitaire_id = t.id
    ORDER BY a.date_arrivee DESC
")->fetchAll();
$clients    = $db->query("SELECT id, nom FROM clients ORDER BY nom")->fetchAll();
$clientsJson = json_encode($clients, JSON_UNESCAPED_UNICODE);
?>
<div class="page-content">

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>" data-auto-dismiss="4000">
            <i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'times-circle' ?>"></i> <?= $msg ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-boxes"></i> Gestion des colis</div>
            <button class="btn btn-primary" onclick="openModal('modalColis')">
                <i class="fas fa-plus"></i> Nouveau colis
            </button>
        </div>
        <div class="card-body">
            <form method="get" class="filters-bar">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($filterSearch) ?>" class="form-control" placeholder="Rechercher code...">
                </div>
                <select name="arrivage_id" class="form-control" style="width:220px">
                    <option value="">Tous les arrivages</option>
                    <?php foreach ($arrivages as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $filterArrivage == $a['id'] ? 'selected' : '' ?>>
                            <?= date('d/m/Y', strtotime($a['date_arrivee'])) ?> - <?= sanitize($a['transitaire_nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="type" class="form-control">
                    <option value="">Tous types</option>
                    <option value="individuel" <?= $filterType === 'individuel' ? 'selected' : '' ?>>Individuel</option>
                    <option value="mixte" <?= $filterType === 'mixte' ? 'selected' : '' ?>>Mixte</option>
                </select>
                <button type="submit" class="btn btn-outline"><i class="fas fa-filter"></i> Filtrer</button>
                <a href="colis.php" class="btn btn-outline"><i class="fas fa-times"></i> Réinitialiser</a>
                <button type="button" class="btn btn-outline" onclick="exportTableToCSV('tableColis','colis')">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
            </form>

            <div class="table-responsive">
                <table id="tableColis">
                    <thead>
                        <tr>
                            <th>Code colis</th>
                            <th>Type</th>
                            <th>Arrivage</th>
                            <th>Transitaire</th>
                            <th>Poids (kg)</th>
                            <th>Montant</th>
                            <th>Propriétaires</th>
                            <th>Solde restant</th>
                            <th class="no-export">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($colisListe): ?>
                            <?php foreach ($colisListe as $c): ?>
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
                                <td><?= sanitize($c['transitaire_nom']) ?></td>
                                <td><?= number_format((float)$c['poids'], 2) ?></td>
                                <td><?= number_format((float)$c['montant'], 0, ',', ' ') ?> FCFA</td>
                                <td><span class="badge badge-gray"><?= $c['nb_proprietaires'] ?></span></td>
                                <td style="color:<?= (float)$c['total_solde'] > 0 ? 'var(--danger)' : 'var(--success)' ?>;font-weight:700">
                                    <?= number_format((float)$c['total_solde'], 0, ',', ' ') ?> FCFA
                                </td>
                                <td class="no-export">
                                    <div class="table-actions">
                                        <button class="btn-icon view" data-tooltip="Détails propriétaires"
                                            onclick="voirProprietaires(<?= $c['id'] ?>, '<?= sanitize($c['code_complet']) ?>')">
                                            <i class="fas fa-users"></i>
                                        </button>
                                        <button class="btn-icon edit" data-tooltip="Modifier"
                                            onclick="openEditColis(<?= htmlspecialchars(json_encode($c)) ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="post" style="display:inline" onsubmit="return confirmDelete()">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="btn-icon delete" data-tooltip="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="9">
                                <div class="empty-state">
                                    <i class="fas fa-boxes"></i>
                                    <h3>Aucun colis trouvé</h3>
                                </div>
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL NOUVEAU COLIS -->
<div class="modal-overlay" id="modalColis">
    <div class="modal modal-xl">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-box"></i> <span id="modalColisTitre">Nouveau colis</span></div>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <form method="post">
            <input type="hidden" name="action" id="colis_action" value="create">
            <input type="hidden" name="id" id="colis_id" value="">
            <div class="modal-body">
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Arrivage <span class="required">*</span></label>
                        <select name="arrivage_id" id="colis_arrivage" class="form-control" required onchange="majDateArrivage(this)">
                            <option value="">-- Sélectionner un arrivage --</option>
                            <?php foreach ($arrivages as $a): ?>
                                <option value="<?= $a['id'] ?>"
                                    data-date="<?= $a['date_arrivee'] ?>"
                                    data-poids-total="<?= (float)$a['poids_total'] ?>"
                                    data-cout-total="<?= (float)$a['cout_total'] ?>"
                                    data-poids-used="<?= (float)$a['poids_used'] ?>"
                                    data-montant-used="<?= (float)$a['montant_used'] ?>"
                                    data-devise="<?= $a['devise'] ?>">
                                    <?= date('d/m/Y', strtotime($a['date_arrivee'])) ?> - <?= sanitize($a['transitaire_nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Code réel du colis <span class="required">*</span></label>
                        <input type="text" name="code_reel" id="colis_code_reel" class="form-control" 
                               placeholder="Ex: A109" required oninput="majCodeComplet()">
                        <div class="form-help">
                            Code complet généré : <strong id="preview_code" class="colis-code">-</strong>
                        </div>
                    </div>
                </div>

                <!-- PANNEAU CAPACITE ARRIVAGE -->
                <div id="capacite-panel" style="display:none;margin-bottom:14px;padding:12px 16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;font-size:13px">
                    <div style="font-weight:700;color:#1e40af;margin-bottom:8px">
                        <i class="fas fa-info-circle"></i> Capacité restante sur cet arrivage
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <div>
                            <div style="color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.5px">Poids disponible</div>
                            <div id="cap-poids-restant" style="font-size:16px;font-weight:700;color:#1e40af">-</div>
                            <div id="cap-poids-bar" style="height:6px;background:#e2e8f0;border-radius:99px;margin-top:4px">
                                <div id="cap-poids-fill" style="height:100%;background:#3b82f6;border-radius:99px;transition:width .3s"></div>
                            </div>
                            <div id="cap-poids-detail" style="font-size:11px;color:#64748b;margin-top:2px"></div>
                        </div>
                        <div>
                            <div style="color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.5px">Montant disponible</div>
                            <div id="cap-montant-restant" style="font-size:16px;font-weight:700;color:#1e40af">-</div>
                            <div id="cap-montant-bar" style="height:6px;background:#e2e8f0;border-radius:99px;margin-top:4px">
                                <div id="cap-montant-fill" style="height:100%;background:#3b82f6;border-radius:99px;transition:width .3s"></div>
                            </div>
                            <div id="cap-montant-detail" style="font-size:11px;color:#64748b;margin-top:2px"></div>
                        </div>
                    </div>
                </div>

                <div class="form-row cols-3">
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select name="type" id="colis_type" class="form-control" onchange="toggleTypeColis(this)">
                            <option value="individuel">Colis individuel</option>
                            <option value="mixte">Colis mixte</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Poids (kg)</label>
                        <input type="number" name="poids" id="colis_poids" class="form-control" 
                               min="0" step="0.01" value="0"
                               oninput="calcMontantProprietaires(); validerCapacite()">
                        <div id="poids-error" class="form-error" style="display:none"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Montant total</label>
                        <input type="number" name="montant" id="colis_montant" class="form-control" 
                               min="0" step="1" value="0"
                               oninput="calcMontantProprietaires(); validerCapacite()">
                        <div id="montant-error" class="form-error" style="display:none"></div>
                    </div>
                </div>

                <!-- SECTION INDIVIDUEL -->
                <div id="section-individuel">
                    <div class="proprietaires-section">
                        <div class="section-title"><i class="fas fa-user"></i> Propriétaire unique</div>
                        <div class="form-row cols-3">
                            <div class="form-group">
                                <label class="form-label">Client</label>
                                <select name="proprietaires[0][client_id]" class="form-control">
                                    <option value="">-- Sélectionner --</option>
                                    <?php foreach ($clients as $cl): ?>
                                        <option value="<?= $cl['id'] ?>"><?= sanitize($cl['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Montant payé immédiatement</label>
                                <input type="number" name="proprietaires[0][montant_paye]" class="form-control" min="0" value="0">
                            </div>
                            <div class="form-group">
                                <input type="hidden" name="proprietaires[0][poids]" value="0">
                                <input type="hidden" name="proprietaires[0][montant_du]" value="0">
                                <label class="form-label" style="visibility:hidden">-</label>
                                <div class="form-help" style="margin-top:8px">
                                    Le montant dû sera celui du colis complet.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION MIXTE -->
                <div id="section-mixte" style="display:none">
                    <div class="proprietaires-section">
                        <div class="section-title"><i class="fas fa-users"></i> Propriétaires (colis mixte)</div>
                        <div style="font-size:12px;color:var(--gray-500);margin-bottom:6px">
                            Les montants sont calcules proportionnellement au poids du colis. Formule : <strong>(poids_part / poids_colis) x montant_colis</strong>.
                        </div>
                        <div id="poids-mixte-warn" class="form-error" style="display:none;margin-bottom:10px"></div>
                        <div id="proprietaires-container"></div>
                        <button type="button" class="btn btn-outline btn-sm add-proprietaire-btn"
                                onclick='ajouterProprietaire(<?= $clientsJson ?>)'>
                            <i class="fas fa-plus"></i> Ajouter propriétaire
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline modal-close">Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT COLIS (simplifié) -->
<div class="modal-overlay" id="modalEditColis">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-edit"></i> Modifier colis</div>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_colis_id">
            <input type="hidden" name="arrivage_id" id="edit_colis_arrivage">
            <input type="hidden" name="code_reel" id="edit_colis_code_reel">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Code complet</label>
                    <span class="colis-code" id="edit_colis_code_display">-</span>
                </div>
                <div class="form-row cols-3">
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select name="type" id="edit_colis_type" class="form-control">
                            <option value="individuel">Individuel</option>
                            <option value="mixte">Mixte</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Poids (kg)</label>
                        <input type="number" name="poids" id="edit_colis_poids" class="form-control" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Montant</label>
                        <input type="number" name="montant" id="edit_colis_montant" class="form-control" step="1" min="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline modal-close">Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL PROPRIETAIRES -->
<div class="modal-overlay" id="modalProprio">
    <div class="modal modal-xl">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-users"></i> Propriétaires - <span id="proprioColisCode"></span></div>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="proprioContent">
            <div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Chargement...</p></div>
        </div>
    </div>
</div>

<script>
let colisDateArrivage   = '';
let colisPoidsTotal     = 0;
let colisPoidsUsed      = 0;
let colisMontantTotal   = 0;
let colisMontantUsed    = 0;
let colisDevise         = 'FCFA';

function majDateArrivage(sel) {
    const opt = sel.options[sel.selectedIndex];
    colisDateArrivage  = opt.getAttribute('data-date') || '';
    colisPoidsTotal    = parseFloat(opt.getAttribute('data-poids-total') || 0);
    colisPoidsUsed     = parseFloat(opt.getAttribute('data-poids-used') || 0);
    colisMontantTotal  = parseFloat(opt.getAttribute('data-cout-total') || 0);
    colisMontantUsed   = parseFloat(opt.getAttribute('data-montant-used') || 0);
    colisDevise        = opt.getAttribute('data-devise') || 'FCFA';

    majCodeComplet();
    majCapacitePanel();
}

function majCapacitePanel() {
    const panel = document.getElementById('capacite-panel');
    if (!panel) return;

    if (!colisPoidsTotal && !colisMontantTotal) {
        panel.style.display = 'none';
        return;
    }
    panel.style.display = 'block';

    const poidsRestant   = Math.max(0, colisPoidsTotal - colisPoidsUsed);
    const montantRestant = Math.max(0, colisMontantTotal - colisMontantUsed);
    const pctPoids   = colisPoidsTotal > 0 ? Math.round((colisPoidsUsed / colisPoidsTotal) * 100) : 0;
    const pctMontant = colisMontantTotal > 0 ? Math.round((colisMontantUsed / colisMontantTotal) * 100) : 0;

    document.getElementById('cap-poids-restant').textContent  = poidsRestant.toFixed(2) + ' kg';
    document.getElementById('cap-poids-fill').style.width     = pctPoids + '%';
    document.getElementById('cap-poids-fill').style.background = pctPoids >= 90 ? '#dc2626' : pctPoids >= 70 ? '#d97706' : '#3b82f6';
    document.getElementById('cap-poids-detail').textContent   = colisPoidsUsed.toFixed(2) + ' kg utilisés sur ' + colisPoidsTotal.toFixed(2) + ' kg';

    document.getElementById('cap-montant-restant').textContent = parseInt(montantRestant).toLocaleString('fr-FR') + ' ' + colisDevise;
    document.getElementById('cap-montant-fill').style.width    = pctMontant + '%';
    document.getElementById('cap-montant-fill').style.background = pctMontant >= 90 ? '#dc2626' : pctMontant >= 70 ? '#d97706' : '#3b82f6';
    document.getElementById('cap-montant-detail').textContent  = parseInt(colisMontantUsed).toLocaleString('fr-FR') + ' utilisés sur ' + parseInt(colisMontantTotal).toLocaleString('fr-FR') + ' ' + colisDevise;

    // Re-valider si des valeurs sont déjà saisies
    validerCapacite();
}

function validerCapacite() {
    const poids   = parseFloat(document.getElementById('colis_poids')?.value || 0);
    const montant = parseFloat(document.getElementById('colis_montant')?.value || 0);
    const poidsInput   = document.getElementById('colis_poids');
    const montantInput = document.getElementById('colis_montant');
    const poidsErr     = document.getElementById('poids-error');
    const montantErr   = document.getElementById('montant-error');
    const btnSubmit    = document.querySelector('#modalColis [type="submit"]');

    let hasError = false;

    if (colisPoidsTotal > 0 && poids > 0) {
        const restant = Math.max(0, colisPoidsTotal - colisPoidsUsed);
        if (poids > restant + 0.001) {
            poidsErr.textContent = 'Dépassement : max autorisé ' + restant.toFixed(2) + ' kg (total arrivage : ' + colisPoidsTotal.toFixed(2) + ' kg)';
            poidsErr.style.display = 'block';
            poidsInput.style.borderColor = 'var(--danger)';
            hasError = true;
        } else {
            poidsErr.style.display = 'none';
            poidsInput.style.borderColor = '';
        }
    }

    if (colisMontantTotal > 0 && montant > 0) {
        const restant = Math.max(0, colisMontantTotal - colisMontantUsed);
        if (montant > restant + 0.01) {
            montantErr.textContent = 'Dépassement : max autorisé ' + parseInt(restant).toLocaleString('fr-FR') + ' ' + colisDevise + ' (total arrivage : ' + parseInt(colisMontantTotal).toLocaleString('fr-FR') + ')';
            montantErr.style.display = 'block';
            montantInput.style.borderColor = 'var(--danger)';
            hasError = true;
        } else {
            montantErr.style.display = 'none';
            montantInput.style.borderColor = '';
        }
    }

    if (btnSubmit) btnSubmit.disabled = hasError;
    return !hasError;
}

function majCodeComplet() {
    const code = document.getElementById('colis_code_reel')?.value || '';
    const dateStr = colisDateArrivage;
    const full = genererCodeComplet(code, dateStr);
    const el = document.getElementById('preview_code');
    if (el) el.textContent = full || '-';

    // Sync montant_du pour individuel
    const montantEl = document.querySelector('[name="proprietaires[0][montant_du]"]');
    const montantTotal = document.getElementById('colis_montant')?.value || 0;
    if (montantEl) montantEl.value = montantTotal;
}

document.getElementById('colis_montant')?.addEventListener('input', function() {
    const montantEl = document.querySelector('[name="proprietaires[0][montant_du]"]');
    if (montantEl) montantEl.value = this.value;
});

function openEditColis(data) {
    document.getElementById('edit_colis_id').value        = data.id;
    document.getElementById('edit_colis_arrivage').value  = data.arrivage_id;
    document.getElementById('edit_colis_code_reel').value = data.code_reel;
    document.getElementById('edit_colis_code_display').textContent = data.code_complet;
    document.getElementById('edit_colis_type').value      = data.type;
    document.getElementById('edit_colis_poids').value     = data.poids;
    document.getElementById('edit_colis_montant').value   = data.montant;

    // Charger la capacité pour l'arrivage en cours (mode édition)
    // Les limites sont indicatives car on exclut le colis lui-même côté serveur
    const arrSel = document.getElementById('colis_arrivage');
    if (arrSel) {
        const opt = Array.from(arrSel.options).find(o => o.value == data.arrivage_id);
        if (opt) {
            colisPoidsTotal   = parseFloat(opt.getAttribute('data-poids-total') || 0);
            colisPoidsUsed    = parseFloat(opt.getAttribute('data-poids-used') || 0) - parseFloat(data.poids || 0);
            colisMontantTotal = parseFloat(opt.getAttribute('data-cout-total') || 0);
            colisMontantUsed  = parseFloat(opt.getAttribute('data-montant-used') || 0) - parseFloat(data.montant || 0);
            colisDevise       = opt.getAttribute('data-devise') || 'FCFA';
        }
    }
    openModal('modalEditColis');
}

function voirProprietaires(colisId, codeComplet) {
    document.getElementById('proprioColisCode').textContent = codeComplet;
    document.getElementById('proprioContent').innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Chargement...</p></div>';
    openModal('modalProprio');

    fetch('../api/proprietaires.php?colis_id=' + colisId)
        .then(r => r.json())
        .then(data => {
            if (!data.length) {
                document.getElementById('proprioContent').innerHTML = '<div class="empty-state"><i class="fas fa-users"></i><p>Aucun propriétaire enregistré.</p></div>';
                return;
            }
            let html = '<div class="table-responsive"><table><thead><tr><th>Client</th><th>Poids (kg)</th><th>Montant dû</th><th>Payé</th><th>Solde</th><th>Statut</th><th>Actions</th></tr></thead><tbody>';
            data.forEach(function(p) {
                const pct = p.montant_du > 0 ? Math.round((p.montant_paye / p.montant_du) * 100) : 0;
                const statuts = { paye: 'badge-success', partiel: 'badge-warning', non_paye: 'badge-danger' };
                const statLabels = { paye: 'Payé', partiel: 'Partiel', non_paye: 'Non payé' };
                html += `<tr>
                    <td><strong>${p.client_nom}</strong><br><small>${p.client_telephone || ''}</small></td>
                    <td>${parseFloat(p.poids).toFixed(2)}</td>
                    <td>${parseInt(p.montant_du).toLocaleString('fr-FR')} FCFA</td>
                    <td>${parseInt(p.montant_paye).toLocaleString('fr-FR')} FCFA
                        <div class="progress-wrap">
                            <div class="progress-bar-outer"><div class="progress-bar-inner" style="width:${pct}%"></div></div>
                        </div>
                    </td>
                    <td style="font-weight:700;color:${p.solde > 0 ? 'var(--danger)' : 'var(--success)'}">
                        ${parseInt(p.solde).toLocaleString('fr-FR')} FCFA
                    </td>
                    <td><span class="badge ${statuts[p.statut] || 'badge-gray'}">${statLabels[p.statut] || p.statut}</span></td>
                    <td>
                        <a href="../pages/paiements.php?cp_id=${p.id}&client=${encodeURIComponent(p.client_nom)}" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> Paiement
                        </a>
                    </td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            document.getElementById('proprioContent').innerHTML = html;
        })
        .catch(function() {
            document.getElementById('proprioContent').innerHTML = '<div class="alert alert-danger">Erreur de chargement.</div>';
        });
}
</script>

<?php require_once '../includes/footer.php'; ?>
