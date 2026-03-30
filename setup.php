<?php
/**
 * setup.php — Installation initiale
 * Crée le compte administrateur si aucun utilisateur n'existe.
 * A SUPPRIMER après la première utilisation.
 */
require_once 'includes/config.php';

$db = getDB();

// Vérifier si la table existe
try {
    $count = (int)$db->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn();
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;max-width:500px;margin:60px auto;padding:24px;background:#fef2f2;border:1px solid #fca5a5;border-radius:10px">
        <h2 style="color:#dc2626">Erreur de base de données</h2>
        <p>La table <strong>utilisateurs</strong> n\'existe pas encore.</p>
        <p>Importez d\'abord le fichier <code>database.sql</code> dans phpMyAdmin, puis revenez ici.</p>
        <p style="font-size:12px;color:#6b7280">' . htmlspecialchars($e->getMessage()) . '</p>
    </div>');
}

$msg     = '';
$msgType = 'danger';
$done    = false;

if ($count > 0 && !isset($_GET['force'])) {
    $msg = 'Des utilisateurs existent déjà. Pour forcer la création, ajoutez <code>?force=1</code> à l\'URL. Ou <a href="login.php">connectez-vous</a>.';
    $msgType = 'warning';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom      = trim($_POST['nom'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $pwd      = $_POST['mot_de_passe'] ?? '';
    $pwdConf  = $_POST['mot_de_passe_confirm'] ?? '';

    if (!$nom || !$email || !$pwd) {
        $msg = 'Tous les champs sont obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Adresse email invalide.';
    } elseif ($pwd !== $pwdConf) {
        $msg = 'Les mots de passe ne correspondent pas.';
    } elseif (strlen($pwd) < 6) {
        $msg = 'Le mot de passe doit contenir au moins 6 caractères.';
    } else {
        $hash = password_hash($pwd, PASSWORD_DEFAULT);
        try {
            $db->prepare("INSERT INTO utilisateurs (nom, email, mot_de_passe, role) VALUES (?, ?, ?, 'admin')")
               ->execute([$nom, $email, $hash]);
            $msg     = 'Compte administrateur créé avec succès. Vous pouvez maintenant <a href="login.php">vous connecter</a>.';
            $msgType = 'success';
            $done    = true;
        } catch (PDOException $e) {
            $msg = 'Erreur : ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #1e3a5f 0%, #1e40af 100%);
            padding: 20px; box-sizing: border-box;
        }
        .setup-card {
            background: #fff; border-radius: 18px;
            padding: 40px 36px; max-width: 480px; width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,.25);
        }
        .setup-card h1 { font-size: 20px; font-weight: 800; color: #1e293b; margin-bottom: 4px; }
        .setup-card .subtitle { font-size: 13px; color: #64748b; margin-bottom: 28px; }
        .setup-steps { font-size: 12px; color: #64748b; margin-bottom: 20px; padding-left: 16px; }
        .setup-steps li { margin-bottom: 4px; }
    </style>
</head>
<body>
<div class="setup-card">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
        <div style="width:48px;height:48px;background:#1e40af;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px">
            <i class="fas fa-cogs"></i>
        </div>
        <div>
            <h1>Installation initiale</h1>
            <div class="subtitle">Création du compte administrateur</div>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>" style="margin-bottom:20px">
            <?= $msg ?>
        </div>
    <?php endif; ?>

    <?php if (!$done && ($count === 0 || isset($_GET['force']))): ?>
    <form method="post">
        <div class="form-group">
            <label class="form-label">Nom complet <span class="required">*</span></label>
            <input type="text" name="nom" class="form-control"
                   value="<?= sanitize($_POST['nom'] ?? 'Administrateur') ?>"
                   placeholder="Ex: Jean Dupont" required>
        </div>
        <div class="form-group">
            <label class="form-label">Email <span class="required">*</span></label>
            <input type="email" name="email" class="form-control"
                   value="<?= sanitize($_POST['email'] ?? '') ?>"
                   placeholder="admin@domaine.com" required>
        </div>
        <div class="form-group">
            <label class="form-label">Mot de passe <span class="required">*</span></label>
            <input type="password" name="mot_de_passe" class="form-control"
                   placeholder="Minimum 6 caractères" required>
        </div>
        <div class="form-group">
            <label class="form-label">Confirmer le mot de passe <span class="required">*</span></label>
            <input type="password" name="mot_de_passe_confirm" class="form-control"
                   placeholder="Répétez le mot de passe" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px">
            <i class="fas fa-user-plus"></i> Créer le compte administrateur
        </button>
    </form>
    <?php elseif ($done): ?>
        <div style="text-align:center;padding:16px 0">
            <i class="fas fa-check-circle" style="font-size:48px;color:var(--success);margin-bottom:16px;display:block"></i>
            <p style="font-size:13px;color:#64748b">
                <strong>Pensez à supprimer ce fichier setup.php</strong> de votre serveur pour des raisons de sécurité.
            </p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
