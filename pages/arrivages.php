<?php
$pageTitle  = 'Arrivages';
$activePage = 'arrivages';
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
        $transitaire_id = (int)($_POST['transitaire_id'] ?? 0);
        $date_arrivee   = $_POST['date_arrivee'] ?? '';
        $nb_colis       = (int)($_POST['nb_colis_total'] ?? 0);
        $poids_total    = (float)($_POST['poids_total'] ?? 0);
        $cout_total     = (float)($_POST['cout_total'] ?? 0);
        $devise         = $_POST['devise'] ?? 'FCFA';
        $notes          = sanitize($_POST['notes'] ?? '');

        if (!$transitaire_id || !$date_arrivee) {
            $msg = 'Transitaire et date sont obligatoires.';
            $msgType = 'danger';
        } else {
            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO arrivages (transitaire_id, date_arrivee, nb_colis_total, poids_total, cout_total, devise, notes) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$transitaire_id, $date_arrivee, $nb_colis, $poids_total, $cout_total, $devise, $notes]);
                $msg = 'Arrivage enregistré avec succès.';
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $db->prepare("UPDATE arrivages SET transitaire_id=?, date_arrivee=?, nb_colis_total=?, poids_total=?, cout_total=?, devise=?, notes=? WHERE id=?");
                $stmt->execute([$transitaire_id, $date_arrivee, $nb_colis, $poids_total, $cout_total, $devise, $notes, $id]);
                $msg = 'Arrivage modifié avec succès.';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM arrivages WHERE id=?")->execute([$id]);
        $msg = 'Arrivage supprimé.';
    }
}

// ============================================================
// FILTRES
// ============================================================
$filterDate   = $_GET['date'] ?? '';
$filterTrans  = (int)($_GET['transitaire_id'] ?? 0);

$where = '1=1';
$params = [];
if ($filterDate) { $where .= ' AND a.date_arrivee = ?'; $params[] = $filterDate; }
if ($filterTrans) { $where .= ' AND a.transitaire_id = ?'; $params[] = $filterTrans; }

$stmt = $db->prepare("
    SELECT a.*, t.nom as transitaire_nom,
           (SELECT COUNT(*) FROM colis WHERE arrivage_id = a.id) as nb_colis_reel
    FROM arrivages a
    JOIN transitaires t ON a.transitaire_id = t.id
    WHERE $where
    ORDER BY a.date_arrivee DESC
");
$stmt->execute($params);
$arrivages = $stmt->fetchAll();

$transitaires = $db->query("SELECT * FROM transitaires ORDER BY nom")->fetchAll();
?>
<div class="page-content">

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>" data-auto-dismiss="4000">
            <i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'times-circle' ?>"></i> <?= $msg ?>
        </div>
    <?php endif; ?>

    <!-- HEADER -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-truck-loading"></i> Gestion des arrivages</div>
            <button class="btn btn-primary" onclick="openModal('modalArrivage')">
                <i class="fas fa-plus"></i> Nouvel arrivage
            </button>
        </div>
        <div class="card-body">
            <!-- FILTRES -->
            <form method="get" class="filters-bar">
                <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>" class="form-control" style="width:160px">
                <select name="transitaire_id" class="form-control" style="width:180px">
                    <option value="">Tous les transitaires</option>
                    <?php foreach ($transitaires as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $filterTrans == $t['id'] ? 'selected' : '' ?>>
                            <?= sanitize($t['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline"><i class="fas fa-filter"></i> Filtrer</button>
                <a href="arrivages.php" class="btn btn-outline"><i class="fas fa-times"></i> Réinitialiser</a>
                <button type="button" class="btn btn-outline" onclick="exportTableToCSV('tableArrivages','arrivages')">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
            </form>

            <div class="table-responsive">
                <table id="tableArrivages">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date arrivée</th>
                            <th>Transitaire</th>
                            <th>Colis déclarés</th>
                            <th>Colis saisis</th>
                            <th>Poids total (kg)</th>
                            <th>Coût total</th>
                            <th>Devise</th>
                            <th class="no-export">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($arrivages): ?>
                            <?php foreach ($arrivages as $a): ?>
                            <tr>
                                <td><?= $a['id'] ?></td>
                                <td><?= date('d/m/Y', strtotime($a['date_arrivee'])) ?></td>
                                <td><?= sanitize($a['transitaire_nom']) ?></td>
                                <td><?= $a['nb_colis_total'] ?></td>
                                <td>
                                    <span class="badge badge-<?= $a['nb_colis_reel'] >= $a['nb_colis_total'] ? 'success' : 'warning' ?>">
                                        <?= $a['nb_colis_reel'] ?>
                                    </span>
                                </td>
                                <td><?= number_format((float)$a['poids_total'], 2) ?></td>
                                <td><?= number_format((float)$a['cout_total'], 0, ',', ' ') ?></td>
                                <td><?= $a['devise'] ?></td>
                                <td class="no-export">
                                    <div class="table-actions">
                                        <a href="../pages/colis.php?arrivage_id=<?= $a['id'] ?>" class="btn-icon view" data-tooltip="Voir colis">
                                            <i class="fas fa-list"></i>
                                        </a>
                                        <button class="btn-icon edit" data-tooltip="Modifier"
                                            onclick="openEditArrivage(<?= htmlspecialchars(json_encode($a)) ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="post" style="display:inline" onsubmit="return confirmDelete('Supprimer cet arrivage et tous ses colis ?')">
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
                            <tr><td colspan="9">
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

<!-- MODAL NOUVEL ARRIVAGE -->
<div class="modal-overlay" id="modalArrivage">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-truck-loading"></i> <span id="modalArrivageTitre">Nouvel arrivage</span></div>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <form method="post">
            <input type="hidden" name="action" id="arrivage_action" value="create">
            <input type="hidden" name="id" id="arrivage_id" value="">
            <div class="modal-body">
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Transitaire <span class="required">*</span></label>
                        <select name="transitaire_id" id="arrivage_transitaire" class="form-control" required>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($transitaires as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= sanitize($t['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date d'arrivée <span class="required">*</span></label>
                        <input type="date" name="date_arrivee" id="arrivage_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="form-row cols-3">
                    <div class="form-group">
                        <label class="form-label">Nombre de colis</label>
                        <input type="number" name="nb_colis_total" id="arrivage_nb" class="form-control" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Poids total (kg)</label>
                        <input type="number" name="poids_total" id="arrivage_poids" class="form-control" min="0" step="0.01" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Devise</label>
                        <select name="devise" id="arrivage_devise" class="form-control">
                            <option value="FCFA">FCFA</option>
                            <option value="EUR">EUR</option>
                            <option value="USD">USD</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Coût total</label>
                    <input type="number" name="cout_total" id="arrivage_cout" class="form-control" min="0" step="0.01" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" id="arrivage_notes" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline modal-close">Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditArrivage(data) {
    document.getElementById('arrivage_action').value    = 'update';
    document.getElementById('arrivage_id').value        = data.id;
    document.getElementById('arrivage_transitaire').value = data.transitaire_id;
    document.getElementById('arrivage_date').value      = data.date_arrivee;
    document.getElementById('arrivage_nb').value        = data.nb_colis_total;
    document.getElementById('arrivage_poids').value     = data.poids_total;
    document.getElementById('arrivage_cout').value      = data.cout_total;
    document.getElementById('arrivage_devise').value    = data.devise;
    document.getElementById('arrivage_notes').value     = data.notes || '';
    document.getElementById('modalArrivageTitre').textContent = 'Modifier arrivage #' + data.id;
    openModal('modalArrivage');
}
</script>

<?php require_once '../includes/footer.php'; ?>
