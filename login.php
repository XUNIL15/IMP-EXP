<?php
require_once 'includes/config.php';

// Déjà connecté → redirection
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email      = trim($_POST['email'] ?? '');
    $motDePasse = $_POST['mot_de_passe'] ?? '';

    if (!$email || !$motDePasse) {
        $error = 'Veuillez renseigner votre email et votre mot de passe.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE email = ? AND actif = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($motDePasse, $user['mot_de_passe'])) {
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_nom']   = $user['nom'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role'];

            $db->prepare("UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = ?")
               ->execute([$user['id']]);

            header('Location: index.php');
            exit;
        } else {
            $error = 'Email ou mot de passe incorrect.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #1e3a5f 0%, #1e40af 100%);
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .login-wrapper {
            width: 100%;
            max-width: 420px;
        }
        .login-card {
            background: #fff;
            border-radius: 18px;
            padding: 44px 40px 36px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 32px;
        }
        .login-logo .logo-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: #fff;
            margin-bottom: 14px;
            box-shadow: 0 8px 20px rgba(59,130,246,.35);
        }
        .login-logo h1 {
            font-size: 22px;
            font-weight: 800;
            color: #1e293b;
            margin: 0 0 4px;
        }
        .login-logo p {
            font-size: 13px;
            color: #94a3b8;
            margin: 0;
        }
        .pwd-wrapper {
            position: relative;
        }
        .pwd-wrapper input {
            padding-right: 46px;
        }
        .pwd-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #94a3b8;
            font-size: 14px;
            padding: 4px;
        }
        .pwd-toggle:hover { color: #64748b; }
        .btn-login {
            width: 100%;
            margin-top: 10px;
            padding: 12px;
            font-size: 15px;
            font-weight: 700;
        }
        .login-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: rgba(255,255,255,.6);
        }
        .login-footer a {
            color: rgba(255,255,255,.85);
            text-decoration: none;
        }
        .login-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-logo">
                <div class="logo-icon"><i class="fas fa-ship"></i></div>
                <h1>Import<strong>Export</strong></h1>
                <p>Connectez-vous pour accéder au système</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger" style="margin-bottom:20px">
                    <i class="fas fa-times-circle"></i> <?= sanitize($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="on">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-envelope" style="color:#94a3b8;margin-right:6px"></i>
                        Adresse email
                    </label>
                    <input type="email" name="email" class="form-control"
                           value="<?= sanitize($_POST['email'] ?? '') ?>"
                           placeholder="exemple@domaine.com" required autofocus autocomplete="username">
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock" style="color:#94a3b8;margin-right:6px"></i>
                        Mot de passe
                    </label>
                    <div class="pwd-wrapper">
                        <input type="password" name="mot_de_passe" id="loginPwd"
                               class="form-control" placeholder="••••••••"
                               required autocomplete="current-password">
                        <button type="button" class="pwd-toggle" onclick="togglePwd()">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-login">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </button>
            </form>
        </div>

        <div class="login-footer">
            <?= APP_NAME ?> v<?= APP_VERSION ?> &mdash;
            <a href="setup.php">Première installation</a>
        </div>
    </div>

    <script>
    function togglePwd() {
        const input = document.getElementById('loginPwd');
        const icon  = document.getElementById('eyeIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }
    </script>
</body>
</html>
