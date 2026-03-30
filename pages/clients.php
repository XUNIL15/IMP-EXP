<?php
$pageTitle  = 'Clients';
$activePage = 'clients';
$root       = '../';
require_once '../includes/header.php';

$db = getDB();
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $nom       = sanitize($_POST['nom'] ?? '');
        $telephone = sanitize($_POST['telephone'] ?? '');
        $email     = sanitize($_POST['email'] ?? '');
        $adresse   = sanitize($_POST['adresse'] ?? '');

        if (!$nom) {
            $msg = 'Le nom est obligatoire.';
            $msgType = 'danger';
        } else {
            if ($action === 'create') {
                $db->prepare("INSERT INTO clients (nom, telephone, email, adresse) VALUES (?,?,?,?)")
                   ->execute([$nom, $telephone, $email, $adresse]);
                $msg = 'Client ajouté avec succès.';
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $db->prepare("UPDATE clients SET nom=?, telephone=?, email=?, adresse=? WHERE id=?")
                   ->execute([$nom, $telephone, $email, $adresse, $id]);
                $msg = 'Client modifié.';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $db->prepare("DELETE FROM clients WHERE id=?")->execute([$id]);
            $msg = 'Client supprimé.';
        } catch (PDOException $e) {
            $msg = 'Impossible de supprimer : ce client a des colis associés.';
            $msgType = 'danger';
        }
    }
}

$search = trim($_GET['q'] ?? '');
$where  = $search ? "WHERE nom LIKE ? OR telephone LIKE ? OR email LIKE ?" : "";
$params = $search ? ["%$search%", "%$search%", "%$search%"] : [];

$stmt = $db->prepare("SELECT c.*, 
    (SELECT COUNT(*) FROM colis_proprietaires cp WHERE cp.client_id = c.id) as nb_colis,
    (SELECT COALESCE(SUM(cp.solde), 0) FROM colis_proprietaires cp WHERE cp.client_id = c.id AND cp.statut != 'paye') as total_dette
FROM clients c $where ORDER BY c.nom");
$stmt->execute($params);
$clients = $stmt->fetchAll();
?>
<div class="page-content">
    <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>" data-auto-dismiss="4000">
            <i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'times-circle' ?>"></i> <?= $msg ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-users"></i> Gestion des clients</div>
            <button class="btn btn-primary" onclick="openModal('modalClient')">
                <i class="fas fa-plus"></i> Nouveau client
            </button>
        </div>
        <div class="card-body">
            <form method="get" class="filters-bar">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher client...">
                </div>
                <button type="submit" class="btn btn-outline"><i class="fas fa-search"></i> Rechercher</button>
                <button type="button" class="btn btn-outline" onclick="exportTableToCSV('tableClients','clients')">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
            </form>

            <div class="table-responsive">
                <table id="tableClients">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nom</th>
                            <th>Téléphone</th>
                            <th>Email</th>
                            <th>Adresse</th>
                            <th>Colis</th>
                            <th>Dette totale</th>
                            <th class="no-export">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($clients): ?>
                            <?php foreach ($clients as $c): ?>
                            <tr>
                                <td><?= $c['id'] ?></td>
                                <td><strong><?= sanitize($c['nom']) ?></strong></td>
                                <td><?= sanitize($c['telephone'] ?: '-') ?></td>
                                <td><?= sanitize($c['email'] ?: '-') ?></td>
                                <td><?= sanitize($c['adresse'] ?: '-') ?></td>
                                <td><span class="badge badge-primary"><?= $c['nb_colis'] ?></span></td>
                                <td style="font-weight:700;color:<?= (float)$c['total_dette'] > 0 ? 'var(--danger)' : 'var(--success)' ?>">
                                    <?= number_format((float)$c['total_dette'], 0, ',', ' ') ?> FCFA
                                </td>
                                <td class="no-export">
                                    <div class="table-actions">
                                        <a href="paiements.php?client_id=<?= $c['id'] ?>" class="btn-icon view" data-tooltip="Historique paiements">
                                            <i class="fas fa-history"></i>
                                        </a>
                                        <button class="btn-icon edit" onclick="openEditClient(<?= htmlspecialchars(json_encode($c)) ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="post" style="display:inline" onsubmit="return confirmDelete('Supprimer ce client ?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="btn-icon delete"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-users"></i>
                                    <h3>Aucun client</h3>
                                </div>
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modalClient">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-user-plus"></i> <span id="modalClientTitre">Nouveau client</span></div>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <form method="post">
            <input type="hidden" name="action" id="client_action" value="create">
            <input type="hidden" name="id" id="client_id" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nom complet <span class="required">*</span></label>
                    <input type="text" name="nom" id="client_nom" class="form-control" required>
                </div>
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Téléphone</label>
                        <input type="text" name="telephone" id="client_tel" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="client_email" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Adresse</label>
                    <textarea name="adresse" id="client_adresse" class="form-control"></textarea>
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
function openEditClient(data) {
    document.getElementById('client_action').value = 'update';
    document.getElementById('client_id').value     = data.id;
    document.getElementById('client_nom').value    = data.nom;
    document.getElementById('client_tel').value    = data.telephone || '';
    document.getElementById('client_email').value  = data.email || '';
    document.getElementById('client_adresse').value = data.adresse || '';
    document.getElementById('modalClientTitre').textContent = 'Modifier ' + data.nom;
    openModal('modalClient');
}
</script>

<?php require_once '../includes/footer.php'; ?>
