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
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            background: #ffffff;
        }

        /* ── CARD WRAPPER ── */
        .lp-card {
            display: flex;
            width: 50%;
            max-width: 900px;
            max-height: 500px;
            min-height: 200px;
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 24px 64px rgba(0, 0, 0, .22), 0 8px 24px rgba(0, 0, 0, .12);
        }

        /* ── LEFT: FORM PANEL ── */
        .lp-left {
            flex: 0 0 48%;
            display: flex;
            flex-direction: column;
            padding: 36px 44px 32px 44px;
            background: #91a3ec;
        }

        /* Brand pill */
        .lp-brand {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 2px solid #1a1a1a;
            border-radius: 50px;
            padding: 6px 18px;
            font-size: 14px;
            font-weight: 700;
            color: #1a1a1a;
            width: fit-content;
            margin-bottom: 32px;
        }

        
        .lp-brand i { font-size: 15px; color: #c9a84c; }

        /* Titles */
        .lp-title {
            font-size: 26px;
            font-weight: 800;
            color: #111;
            margin-bottom: 6px;
            line-height: 1.2;
        }

        .lp-subtitle {
            font-size: 13px;
            color: #ffffff;
            margin-bottom: 28px;
            line-height: 1.5;
        }

        /* Fields */
        .lp-field { margin-bottom: 16px; }

        .lp-label {
            display: block;
            font-size: 11.5px;
            font-weight: 900;
            color: #0d0247;
            margin-bottom: 6px;
            letter-spacing: .3px;
            text-transform: uppercase;
        }

        .lp-input {
            width: 100%;
            padding: 13px 16px;
            background: #fff;
            border: 1.5px solid transparent;
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
            color: #1a1a1a;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
            transition: border-color .2s, box-shadow .2s;
            outline: none;
        }

        .lp-input::placeholder { color: #c8bfb3; }

        .lp-input:focus {
            border-color: #e8c040;
            box-shadow: 0 0 0 3px rgba(232,192,64,.18), 0 2px 8px rgba(0,0,0,.07);
        }

        /* Password toggle */
        .lp-pwd-wrap { position: relative; }
        .lp-pwd-wrap .lp-input { padding-right: 46px; }

        .lp-pwd-toggle {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #bdb3a8;
            font-size: 14px;
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
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 12.5px;
            margin-bottom: 16px;
        }

        /* Submit */
        .lp-submit {
            width: 100%;
            padding: 14px;
            background: #020c6a;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            color: #ffffff;
            cursor: pointer;
            margin-top: 4px;
            letter-spacing: .2px;
            transition: background .2s, transform .1s, box-shadow .2s;
            box-shadow: 0 4px 14px rgba(1, 1, 1, 0.4);
        }

        .lp-submit:hover { background: #010028; box-shadow: 0 6px 18px rgba(0, 0, 0, 0.5); }
        .lp-submit:active { transform: scale(.98); }

        /* Divider */
        .lp-divider {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 16px 0;
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
            gap: 10px;
        }

        .lp-social-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 11px 10px;
            background: #fff;
            border: 1.5px solid #e4ddd4;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #333;
            box-shadow: 0 1px 4px rgba(0,0,0,.05);
        }

        .lp-social-btn .fa-apple { font-size: 15px; }

        /* Setup link */
        .lp-setup {
            text-align: center;
            margin-top: 18px;
            font-size: 12px;
            color: #010127;
        }

        .lp-setup a { color: #ffffff; font-weight: 700; text-decoration: none; }
        .lp-setup a:hover { text-decoration: underline; }

        /* ── RIGHT: PHOTO PANEL ── */
        .lp-right {
            flex: 1;
            position: relative;
            overflow: hidden;
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
            background: linear-gradient(145deg, #3a3028 0%, #1a1410 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            color: rgba(255,255,255,.3);
            font-size: 13px;
        }

        .lp-right-placeholder i { font-size: 44px; opacity: .2; }
        .lp-right-placeholder small { font-size: 11px; opacity: .45; font-family: monospace; }

        /* ── RESPONSIVE ── */
        @media (max-width: 720px) {
            .lp-right  { display: none; }
            .lp-left   { flex: 1; border-radius: 28px; padding: 32px 28px; }
            .lp-card   { border-radius: 28px; }
        }
    </style>
</head>
<body>

    <div class="lp-card">

        <!-- ═══ LEFT: FORM ═══ -->
        <div class="lp-left">
            <div class="lp-brand">
                <i class="fa-solid fa-ship" style="color: rgb(3, 3, 74);"></i>
                Import<strong>Export</strong>
            </div>

            <h1 class="lp-title">Connexion</h1>
            <p class="lp-subtitle">Connectez-vous pour accéder au système de gestion</p>

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
                            placeholder="••••••••••••••••"
                            required autocomplete="current-password">
                        <button type="button" class="lp-pwd-toggle" onclick="togglePwd()" tabindex="-1">
                            <i class="fas fa-eye-slash" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="lp-submit">Se connecter</button>
            </form>

            <div class="lp-setup">
                <span style="color: #ffffff; font-weight: 700;">Première installation ?</span> <a href="setup.php" style="color: #0c0137; font-weight: 700;">Créer le compte admin</a>
            </div>
        </div>

        <!-- ═══ RIGHT: PHOTO ═══ -->
        <div class="lp-right">
            <?php if ($hasBg): ?>
                <img src="<?= $loginBg ?>?v=<?= filemtime($loginBg) ?>" alt="">
            <?php else: ?>
                <div class="lp-right-placeholder">
                    <img src="images\global-logistics-international-shipping.jpg" alt="">
                </div>
            <?php endif; ?>
        </div>

    </div><!-- /.lp-card -->

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
