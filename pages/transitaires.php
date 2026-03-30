<?php
$pageTitle  = 'Transitaires';
$activePage = 'transitaires';
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
                $db->prepare("INSERT INTO transitaires (nom, telephone, email, adresse) VALUES (?,?,?,?)")
                   ->execute([$nom, $telephone, $email, $adresse]);
                $msg = 'Transitaire ajouté.';
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $db->prepare("UPDATE transitaires SET nom=?, telephone=?, email=?, adresse=? WHERE id=?")
                   ->execute([$nom, $telephone, $email, $adresse, $id]);
                $msg = 'Transitaire modifié.';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $db->prepare("DELETE FROM transitaires WHERE id=?")->execute([$id]);
            $msg = 'Transitaire supprimé.';
        } catch (PDOException $e) {
            $msg = 'Impossible : ce transitaire a des arrivages associés.';
            $msgType = 'danger';
        }
    }
}

$transitaires = $db->query("
    SELECT t.*, COUNT(a.id) as nb_arrivages
    FROM transitaires t
    LEFT JOIN arrivages a ON a.transitaire_id = t.id
    GROUP BY t.id ORDER BY t.nom
")->fetchAll();
?>
<div class="page-content">
    <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>" data-auto-dismiss="4000">
            <i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'times-circle' ?>"></i> <?= $msg ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-handshake"></i> Gestion des transitaires</div>
            <button class="btn btn-primary" onclick="openModal('modalTransitaire')">
                <i class="fas fa-plus"></i> Nouveau transitaire
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nom</th>
                            <th>Téléphone</th>
                            <th>Email</th>
                            <th>Adresse</th>
                            <th>Arrivages</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($transitaires): ?>
                            <?php foreach ($transitaires as $t): ?>
                            <tr>
                                <td><?= $t['id'] ?></td>
                                <td><strong><?= sanitize($t['nom']) ?></strong></td>
                                <td><?= sanitize($t['telephone'] ?: '-') ?></td>
                                <td><?= sanitize($t['email'] ?: '-') ?></td>
                                <td><?= sanitize($t['adresse'] ?: '-') ?></td>
                                <td><span class="badge badge-info"><?= $t['nb_arrivages'] ?></span></td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-icon edit" onclick="openEditTransitaire(<?= htmlspecialchars(json_encode($t)) ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="post" style="display:inline" onsubmit="return confirmDelete('Supprimer ce transitaire ?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                            <button type="submit" class="btn-icon delete"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-handshake"></i>
                                    <h3>Aucun transitaire</h3>
                                </div>
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modalTransitaire">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-handshake"></i> <span id="modalTransTitre">Nouveau transitaire</span></div>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <form method="post">
            <input type="hidden" name="action" id="trans_action" value="create">
            <input type="hidden" name="id" id="trans_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nom <span class="required">*</span></label>
                    <input type="text" name="nom" id="trans_nom" class="form-control" required>
                </div>
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Téléphone</label>
                        <input type="text" name="telephone" id="trans_tel" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="trans_email" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Adresse</label>
                    <textarea name="adresse" id="trans_adresse" class="form-control"></textarea>
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
function openEditTransitaire(data) {
    document.getElementById('trans_action').value = 'update';
    document.getElementById('trans_id').value     = data.id;
    document.getElementById('trans_nom').value    = data.nom;
    document.getElementById('trans_tel').value    = data.telephone || '';
    document.getElementById('trans_email').value  = data.email || '';
    document.getElementById('trans_adresse').value = data.adresse || '';
    document.getElementById('modalTransTitre').textContent = 'Modifier ' + data.nom;
    openModal('modalTransitaire');
}
</script>

<?php require_once '../includes/footer.php'; ?>
