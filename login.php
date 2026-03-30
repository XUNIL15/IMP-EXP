<?php
require_once 'includes/config.php';

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
        $db   = getDB();
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
    <title>Connexion — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            display: flex;
            min-height: 100vh;
            background: #f5f0e8;
        }

        /* ── LEFT PANEL ─────────────────────────────────────── */
        .login-left {
            flex: 0 0 480px;
            display: flex;
            flex-direction: column;
            padding: 40px 52px;
            background: linear-gradient(160deg, #fdf6e3 0%, #f5ead0 60%, #ede0c4 100%);
            min-height: 100vh;
        }

        .login-brand {
            font-size: 17px;
            font-weight: 700;
            color: #2d2d2d;
            border: 2px solid #2d2d2d;
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            letter-spacing: .3px;
            width: fit-content;
        }

        .login-brand span { color: #c8a84b; }

        .login-form-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 0 0 20px;
        }

        .login-title {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 6px;
        }

        .login-subtitle {
            font-size: 14px;
            color: #7a7163;
            margin-bottom: 36px;
        }

        .field-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #9a8f80;
            text-transform: uppercase;
            letter-spacing: .6px;
            margin-bottom: 8px;
        }

        .field-wrap {
            margin-bottom: 22px;
        }

        .field-input {
            width: 100%;
            padding: 14px 18px;
            background: #fff;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            color: #1a1a1a;
            font-family: inherit;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
            transition: box-shadow .2s;
            outline: none;
        }

        .field-input:focus {
            box-shadow: 0 0 0 3px rgba(200,168,75,.35), 0 2px 8px rgba(0,0,0,.06);
        }

        .field-input::placeholder { color: #b8ae9f; }

        .pwd-wrap {
            position: relative;
        }

        .pwd-wrap .field-input {
            padding-right: 48px;
        }

        .pwd-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #b0a595;
            font-size: 15px;
            padding: 4px;
            transition: color .15s;
        }

        .pwd-toggle:hover { color: #7a6a55; }

        .btn-submit {
            width: 100%;
            padding: 15px;
            background: #e8c040;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            color: #1a1a1a;
            cursor: pointer;
            margin-top: 8px;
            transition: background .2s, transform .1s;
            letter-spacing: .2px;
        }

        .btn-submit:hover { background: #d4ad2e; }
        .btn-submit:active { transform: scale(.98); }

        .error-msg {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .login-footer-link {
            text-align: center;
            margin-top: 28px;
            font-size: 12px;
            color: #9a8f80;
        }

        .login-footer-link a {
            color: #c8a84b;
            text-decoration: none;
            font-weight: 600;
        }

        .login-footer-link a:hover { text-decoration: underline; }

        /* ── RIGHT PANEL ─────────────────────────────────────── */
        .login-right {
            flex: 1;
            position: relative;
            background: #c8b89a;
            overflow: hidden;
        }

        .login-right img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .login-right-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #d4c0a0, #b8a484);
            color: rgba(255,255,255,.6);
            font-size: 15px;
            gap: 12px;
        }

        .login-right-placeholder i { font-size: 48px; opacity: .5; }

        /* ── RESPONSIVE ─────────────────────────────────────── */
        @media (max-width: 820px) {
            .login-right { display: none; }
            .login-left  { flex: 1; padding: 40px 28px; }
        }
    </style>
</head>
<body>

    <!-- LEFT: FORM PANEL -->
    <div class="login-left">
        <div class="login-brand">Import<span>Export</span></div>

        <div class="login-form-area">
            <h1 class="login-title">Connexion</h1>
            <p class="login-subtitle">Connectez-vous pour accéder au système de gestion</p>

            <?php if ($error): ?>
                <div class="error-msg">
                    <i class="fas fa-times-circle"></i>
                    <?= sanitize($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="on">
                <div class="field-wrap">
                    <label class="field-label">Email</label>
                    <input
                        type="email"
                        name="email"
                        class="field-input"
                        value="<?= sanitize($_POST['email'] ?? '') ?>"
                        placeholder="exemple@domaine.com"
                        required autofocus autocomplete="username">
                </div>

                <div class="field-wrap">
                    <label class="field-label">Mot de passe</label>
                    <div class="pwd-wrap">
                        <input
                            type="password"
                            name="mot_de_passe"
                            id="loginPwd"
                            class="field-input"
                            placeholder="••••••••••••"
                            required autocomplete="current-password">
                        <button type="button" class="pwd-toggle" onclick="togglePwd()">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Se connecter</button>
            </form>

            <div class="login-footer-link">
                Première utilisation ? <a href="setup.php">Créer le compte admin</a>
            </div>
        </div>
    </div>

    <!-- RIGHT: PHOTO PANEL -->
    <div class="login-right">
        <?php
        $imgFile = 'images/login-bg.jpg';
        if (file_exists($imgFile)): ?>
            <img src="<?= $imgFile ?>?v=<?= filemtime($imgFile) ?>" alt="Background">
        <?php else: ?>
            <div class="login-right-placeholder">
                <i class="fas fa-image"></i>
                <span>Déposez votre image ici</span>
                <small>Fichier : images/login-bg.jpg</small>
            </div>
        <?php endif; ?>
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
