<?php
// ============================================================
// CONFIGURATION DE LA BASE DE DONNEES
// ============================================================
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'import_export');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'GestionImportExport');
define('APP_VERSION', '1.0.0');
define('DEVISE_DEFAULT', 'FCFA');

// ============================================================
// CONNEXION PDO
// ============================================================
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Connexion BDD échouée : ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ============================================================
// HELPER : Générer le code complet d'un colis
// Format : CODE_JJMMAA (ex: A109_290326)
// ============================================================
function genererCodeComplet(string $codeReel, string $dateArrivee): string {
    $ts = strtotime($dateArrivee);
    $suffixe = date('dmy', $ts); // JJMMAA
    return strtoupper(trim($codeReel)) . '_' . $suffixe;
}

// ============================================================
// HELPER : Formater un montant
// ============================================================
function formatMontant(float $montant, string $devise = DEVISE_DEFAULT): string {
    return number_format($montant, 0, ',', ' ') . ' ' . $devise;
}

// ============================================================
// HELPER : Sanitize input XSS
// ============================================================
function sanitize(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

// ============================================================
// HELPER : Réponse JSON
// ============================================================
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// AUTHENTIFICATION
// ============================================================

/**
 * Vérifie que l'utilisateur est connecté.
 * Redirige vers login.php si ce n'est pas le cas.
 */
function requireAuth(): void {
    if (empty($_SESSION['user_id'])) {
        $script = $_SERVER['PHP_SELF'] ?? '';
        if (strpos($script, '/pages/') !== false || strpos($script, '/api/') !== false) {
            header('Location: ../login.php');
        } else {
            header('Location: login.php');
        }
        exit;
    }
}

/**
 * Retourne les informations de l'utilisateur connecté.
 */
function getCurrentUser(): array {
    return [
        'id'    => (int)($_SESSION['user_id']   ?? 0),
        'nom'   => $_SESSION['user_nom']  ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role'  => $_SESSION['user_role']  ?? 'gestionnaire',
    ];
}

/**
 * Indique si l'utilisateur connecté est administrateur.
 */
function isAdmin(): bool {
    return ($_SESSION['user_role'] ?? '') === 'admin';
}
