<?php
$pageTitle  = 'Utilisateurs';
$activePage = 'utilisateurs';
$root       = '../';
require_once '../includes/header.php';

// Réservé aux administrateurs
if (!isAdmin()) {
    echo '<div class="page-content"><div class="alert alert-danger"><i class="fas fa-ban"></i> Accès réservé aux administrateurs.</div></div>';
    require_once '../includes/footer.php';
    exit;
}

$db      = getDB();
$msg     = '';
$msgType = 'success';
$me      = getCurrentUser();

// ============================================================
// TRAITEMENT FORMULAIRE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nom   = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pwd   = $_POST['mot_de_passe'] ?? '';
        $role  = $_POST['role'] ?? 'gestionnaire';

        if (!$nom || !$email || !$pwd) {
            $msg = 'Nom, email et mot de passe sont obligatoires.';
            $msgType = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = 'Adresse email invalide.';
            $msgType = 'danger';
        } elseif (strlen($pwd) < 6) {
            $msg = 'Le mot de passe doit contenir au moins 6 caractères.';
            $msgType = 'danger';
        } else {
            try {
                $hash = password_hash($pwd, PASSWORD_DEFAULT);
                $db->prepare("INSERT INTO utilisateurs (nom, email, mot_de_passe, role) VALUES (?,?,?,?)")
                   ->execute([$nom, $email, $hash, $role]);
                $msg = "Utilisateur <strong>" . sanitize($nom) . "</strong> créé avec succès.";
            } catch (PDOException $e) {
                $msg = 'Email déjà utilisé ou erreur : ' . htmlspecialchars($e->getMessage());
                $msgType = 'danger';
            }
        }
    }

    if ($action === 'update') {
        $id    = (int)($_POST['id'] ?? 0);
        $nom   = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role  = $_POST['role'] ?? 'gestionnaire';
        $actif = isset($_POST['actif']) ? 1 : 0;
        $pwd   = $_POST['mot_de_passe'] ?? '';

        if (!$nom || !$email) {
            $msg = 'Nom et email sont obligatoires.';
            $msgType = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = 'Adresse email invalide.';
            $msgType = 'danger';
        } else {
            // Ne pas désactiver son propre compte ni se rétrograder
            if ($id === $me['id']) {
                $actif = 1;
                $role  = 'admin';
            }
            if ($pwd !== '') {
                if (strlen($pwd) < 6) {
                    $msg = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
                    $msgType = 'danger';
                } else {
                    $hash = password_hash($pwd, PASSWORD_DEFAULT);
                    $db->prepare("UPDATE utilisateurs SET nom=?, email=?, mot_de_passe=?, role=?, actif=? WHERE id=?")
                       ->execute([$nom, $email, $hash, $role, $actif, $id]);
                    $msg = 'Utilisateur modifié avec succès (mot de passe mis à jour).';
                }
            } else {
                $db->prepare("UPDATE utilisateurs SET nom=?, email=?, role=?, actif=? WHERE id=?")
                   ->execute([$nom, $email, $role, $actif, $id]);
                $msg = 'Utilisateur modifié avec succès.';
            }
            // Mettre à jour la session si on modifie son propre compte
            if ($msgType === 'success' && $id === $me['id']) {
                $_SESSION['user_nom']   = $nom;
                $_SESSION['user_email'] = $email;
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === $me['id']) {
            $msg = 'Vous ne pouvez pas supprimer votre propre compte.';
            $msgType = 'danger';
        } else {
            $db->prepare("DELETE FROM utilisateurs WHERE id=?")->execute([$id]);
            $msg = 'Utilisateur supprimé.';
        }
    }
}

// ============================================================
// LISTE
// ============================================================
$utilisateurs = $db->query("SELECT * FROM utilisateurs ORDER BY role DESC, nom ASC")->fetchAll();
?>
<div class="page-content">

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>" data-auto-dismiss="5000">
            <i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'times-circle' ?>"></i> <?= $msg ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-user-cog"></i> Gestion des utilisateurs</div>
            <button class="btn btn-primary" onclick="openModal('modalNewUser')">
                <i class="fas fa-user-plus"></i> Nouvel utilisateur
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="tableUtilisateurs">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Statut</th>
                            <th>Derniere connexion</th>
                            <th>Cree le</th>
                            <th class="no-export">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($utilisateurs as $u): ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">
                                    <div style="width:36px;height:36px;border-radius:50%;background:<?= $u['role']==='admin' ? 'var(--primary)' : 'var(--gray-300)' ?>;display:flex;align-items:center;justify-content:center;color:<?= $u['role']==='admin' ? '#fff' : 'var(--gray-600)' ?>;font-weight:700;font-size:14px;flex-shrink:0">
                                        <?= strtoupper(mb_substr($u['nom'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <strong><?= sanitize($u['nom']) ?></strong>
                                        <?php if ($u['id'] === $me['id']): ?>
                                            <span class="badge badge-info" style="font-size:10px;margin-left:4px">Moi</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?= sanitize($u['email']) ?></td>
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge badge-primary"><i class="fas fa-shield-alt"></i> Administrateur</span>
                                <?php else: ?>
                                    <span class="badge badge-gray"><i class="fas fa-user"></i> Gestionnaire</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['actif']): ?>
                                    <span class="badge badge-success"><i class="fas fa-circle" style="font-size:8px"></i> Actif</span>
                                <?php else: ?>
                                    <span class="badge badge-danger"><i class="fas fa-circle" style="font-size:8px"></i> Inactif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $u['derniere_connexion'] ? date('d/m/Y H:i', strtotime($u['derniere_connexion'])) : '<span style="color:var(--gray-400)">Jamais</span>' ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($u['date_creation'])) ?></td>
                            <td class="no-export">
                                <div class="table-actions">
                                    <button class="btn-icon edit" data-tooltip="Modifier"
                                        onclick="openEditUser(<?= htmlspecialchars(json_encode([
                                            'id'    => $u['id'],
                                            'nom'   => $u['nom'],
                                            'email' => $u['email'],
                                            'role'  => $u['role'],
                                            'actif' => $u['actif'],
                                        ])) ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($u['id'] !== $me['id']): ?>
                                        <form method="post" style="display:inline"
                                              onsubmit="return confirmDeleteUser('<?= sanitize($u['nom']) ?>')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn-icon delete" data-tooltip="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="btn-icon" data-tooltip="Impossible de supprimer votre propre compte"
                                              style="opacity:.35;cursor:not-allowed">
                                            <i class="fas fa-trash"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL NOUVEL UTILISATEUR -->
<div class="modal-overlay" id="modalNewUser">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-user-plus"></i> Nouvel utilisateur</div>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Nom complet <span class="required">*</span></label>
                        <input type="text" name="nom" class="form-control" placeholder="Ex: Jean Dupont" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-control">
                            <option value="gestionnaire">Gestionnaire</option>
                            <option value="admin">Administrateur</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Email <span class="required">*</span></label>
                    <input type="email" name="email" class="form-control"
                           placeholder="utilisateur@domaine.com" required>
                </div>
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Mot de passe <span class="required">*</span></label>
                        <input type="password" name="mot_de_passe" class="form-control"
                               placeholder="Min. 6 caractères" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirmer le mot de passe</label>
                        <input type="password" id="new_pwd_confirm" class="form-control"
                               placeholder="Répéter le mot de passe">
                    </div>
                </div>
                <div id="pwd_match_err" class="form-error" style="display:none">
                    Les mots de passe ne correspondent pas.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline modal-close">Annuler</button>
                <button type="submit" class="btn btn-primary" id="btnCreateUser">
                    <i class="fas fa-save"></i> Créer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL MODIFIER UTILISATEUR -->
<div class="modal-overlay" id="modalEditUser">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-user-edit"></i> Modifier l'utilisateur</div>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_user_id">
            <div class="modal-body">
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label class="form-label">Nom complet <span class="required">*</span></label>
                        <input type="text" name="nom" id="edit_user_nom" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" id="edit_user_role" class="form-control">
                            <option value="gestionnaire">Gestionnaire</option>
                            <option value="admin">Administrateur</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Email <span class="required">*</span></label>
                    <input type="email" name="email" id="edit_user_email" class="form-control" required>
                </div>
                <div class="form-group" id="edit_actif_group">
                    <label class="form-label">
                        <input type="checkbox" name="actif" id="edit_user_actif" value="1" style="margin-right:6px">
                        Compte actif
                    </label>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock" style="color:#94a3b8;margin-right:4px"></i>
                        Nouveau mot de passe <span style="font-weight:400;color:var(--gray-500)">(laisser vide pour ne pas changer)</span>
                    </label>
                    <input type="password" name="mot_de_passe" class="form-control"
                           placeholder="Nouveau mot de passe (optionnel, min. 6 car.)">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline modal-close">Annuler</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const MOI_ID = <?= $me['id'] ?>;

function openEditUser(data) {
    document.getElementById('edit_user_id').value    = data.id;
    document.getElementById('edit_user_nom').value   = data.nom;
    document.getElementById('edit_user_email').value = data.email;
    document.getElementById('edit_user_role').value  = data.role;
    document.getElementById('edit_user_actif').checked = data.actif == 1;

    // Verrouiller role et actif si on modifie son propre compte
    const isSelf = data.id == MOI_ID;
    document.getElementById('edit_user_role').disabled  = isSelf;
    document.getElementById('edit_user_actif').disabled = isSelf;
    document.getElementById('edit_actif_group').style.display = isSelf ? 'none' : 'block';

    openModal('modalEditUser');
}

// Vérification correspondance mdp (formulaire nouveau)
function checkPwdMatch() {
    const pwd1 = document.querySelector('#modalNewUser [name="mot_de_passe"]')?.value || '';
    const pwd2 = document.getElementById('new_pwd_confirm')?.value || '';
    const err  = document.getElementById('pwd_match_err');
    const btn  = document.getElementById('btnCreateUser');
    if (pwd2 && pwd1 !== pwd2) {
        err.style.display = 'block';
        btn.disabled = true;
    } else {
        err.style.display = 'none';
        btn.disabled = false;
    }
}

document.querySelector('#modalNewUser [name="mot_de_passe"]')?.addEventListener('input', checkPwdMatch);
document.getElementById('new_pwd_confirm')?.addEventListener('input', checkPwdMatch);

function confirmDeleteUser(nom) {
    return confirm('Supprimer l\'utilisateur "' + nom + '" ? Cette action est irreversible.');
}
</script>

<?php require_once '../includes/footer.php'; ?>
