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

$loginBg = 'images/login-bg.jpg';
$hasBg   = file_exists($loginBg);
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
            min-height: 100vh;
            display: flex;
            background: #f7f3ec;
        }

        /* ── LEFT PANEL ── */
        .lp-left {
            flex: 0 0 50%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 36px 56px 36px 52px;
            background: linear-gradient(150deg, #fffdf7 0%, #fdf5e0 50%, #f7ecce 100%);
            position: relative;
        }

        /* Brand pill */
        .lp-brand {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 2px solid #1a1a1a;
            border-radius: 50px;
            padding: 7px 20px;
            font-size: 15px;
            font-weight: 700;
            color: #1a1a1a;
            width: fit-content;
            letter-spacing: .1px;
        }

        .lp-brand i { font-size: 16px; color: #c9a84c; }

        /* Form area */
        .lp-form-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding-bottom: 24px;
            max-width: 380px;
        }

        .lp-title {
            font-size: 30px;
            font-weight: 800;
            color: #111;
            margin-bottom: 8px;
            line-height: 1.2;
        }

        .lp-subtitle {
            font-size: 14px;
            color: #9a8e7e;
            margin-bottom: 38px;
            line-height: 1.5;
        }

        /* Field */
        .lp-field { margin-bottom: 20px; }

        .lp-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #aea197;
            margin-bottom: 7px;
            letter-spacing: .3px;
        }

        .lp-input {
            width: 100%;
            padding: 14px 18px;
            background: #fff;
            border: 1.5px solid transparent;
            border-radius: 14px;
            font-size: 14px;
            font-family: inherit;
            color: #1a1a1a;
            box-shadow: 0 2px 10px rgba(0,0,0,.07);
            transition: border-color .2s, box-shadow .2s;
            outline: none;
        }

        .lp-input::placeholder { color: #c8bfb3; }

        .lp-input:focus {
            border-color: #e8c040;
            box-shadow: 0 0 0 3px rgba(232,192,64,.2), 0 2px 10px rgba(0,0,0,.07);
        }

        /* Password toggle */
        .lp-pwd-wrap { position: relative; }

        .lp-pwd-wrap .lp-input { padding-right: 48px; }

        .lp-pwd-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #bdb3a8;
            font-size: 15px;
            transition: color .15s;
            padding: 4px;
        }

        .lp-pwd-toggle:hover { color: #7a6a55; }

        /* Error */
        .lp-error {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 13px;
            margin-bottom: 22px;
        }

        /* Submit button */
        .lp-submit {
            width: 100%;
            padding: 16px;
            background: #e9c13d;
            border: none;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 700;
            color: #1a1a1a;
            cursor: pointer;
            margin-top: 6px;
            letter-spacing: .2px;
            transition: background .2s, transform .1s, box-shadow .2s;
            box-shadow: 0 4px 16px rgba(233,193,61,.35);
        }

        .lp-submit:hover {
            background: #d4ac2a;
            box-shadow: 0 6px 20px rgba(233,193,61,.45);
        }

        .lp-submit:active { transform: scale(.98); }

        /* Divider */
        .lp-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
            color: #c5b9ac;
            font-size: 12px;
        }

        .lp-divider::before,
        .lp-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e4ddd4;
        }

        /* Social buttons */
        .lp-socials {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .lp-social-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            background: #fff;
            border: 1.5px solid #e4ddd4;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 600;
            color: #333;
            cursor: default;
            box-shadow: 0 1px 4px rgba(0,0,0,.05);
        }

        .lp-social-btn .fa-apple { font-size: 16px; }
        .lp-social-btn .google-g {
            width: 16px; height: 16px;
            background: url('https://www.google.com/favicon.ico') center/contain no-repeat;
            display: inline-block;
        }

        /* Setup link */
        .lp-setup {
            text-align: center;
            margin-top: 22px;
            font-size: 12px;
            color: #a89f93;
        }

        .lp-setup a {
            color: #c9a84c;
            font-weight: 600;
            text-decoration: none;
        }

        .lp-setup a:hover { text-decoration: underline; }

        /* ── RIGHT PANEL ── */
        .lp-right {
            flex: 1;
            position: relative;
            overflow: hidden;
            min-height: 100vh;
        }

        .lp-right img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .lp-right-placeholder {
            width: 100%;
            height: 100%;
            min-height: 100vh;
            background: linear-gradient(145deg, #3a3028 0%, #1a1410 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
            color: rgba(255,255,255,.3);
            font-size: 14px;
        }

        .lp-right-placeholder i { font-size: 52px; opacity: .25; }
        .lp-right-placeholder small {
            font-size: 11px;
            opacity: .5;
            font-family: monospace;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 860px) {
            .lp-right { display: none; }
            .lp-left  { flex: 1; padding: 32px 28px; }
            .lp-form-area { max-width: 100%; }
        }
    </style>
</head>
<body>

    <!-- ═══ LEFT: FORM ═══ -->
    <div class="lp-left">
        <div class="lp-brand">
            <i class="fas fa-ship"></i>
            Import<strong>Export</strong>
        </div>

        <div class="lp-form-area">
            <h1 class="lp-title">Connexion</h1>
            <p class="lp-subtitle">Connectez-vous pour accéder<br>au système de gestion</p>

            <?php if ($error): ?>
                <div class="lp-error">
                    <i class="fas fa-times-circle"></i>
                    <?= sanitize($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="on">
                <div class="lp-field">
                    <label class="lp-label">Adresse email</label>
                    <input
                        type="email"
                        name="email"
                        class="lp-input"
                        value="<?= sanitize($_POST['email'] ?? '') ?>"
                        placeholder="exemple@domaine.com"
                        required autofocus autocomplete="username">
                </div>

                <div class="lp-field">
                    <label class="lp-label">Mot de passe</label>
                    <div class="lp-pwd-wrap">
                        <input
                            type="password"
                            name="mot_de_passe"
                            id="loginPwd"
                            class="lp-input"
                            placeholder="••••••••••••••••••"
                            required autocomplete="current-password">
                        <button type="button" class="lp-pwd-toggle" onclick="togglePwd()" tabindex="-1">
                            <i class="fas fa-eye-slash" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="lp-submit">Se connecter</button>
            </form>

            <div class="lp-divider">ou</div>

            <div class="lp-socials">
                <div class="lp-social-btn">
                    <i class="fab fa-apple"></i> Apple
                </div>
                <div class="lp-social-btn">
                    <span class="google-g"></span> Google
                </div>
            </div>

            <div class="lp-setup">
                Première installation ? <a href="setup.php">Créer le compte admin</a>
            </div>
        </div>
    </div>

    <!-- ═══ RIGHT: PHOTO ═══ -->
    <div class="lp-right">
        <?php if ($hasBg): ?>
            <img src="<?= $loginBg ?>?v=<?= filemtime($loginBg) ?>" alt="">
        <?php else: ?>
            <div class="lp-right-placeholder">
                <i class="fas fa-image"></i>
                <span>Votre photo ici</span>
                <small>Déposez votre image : images/login-bg.jpg</small>
            </div>
        <?php endif; ?>
    </div>

    <script>
    function togglePwd() {
        const input = document.getElementById('loginPwd');
        const icon  = document.getElementById('eyeIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye-slash';
        }
    }
    </script>
</body>
</html>
