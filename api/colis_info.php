<?php
require_once '../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$code = trim($_GET['code'] ?? '');
$db   = getDB();

if (!$code) {
    echo json_encode(['error' => 'Code requis']);
    exit;
}

$stmt = $db->prepare("
    SELECT c.*, a.date_arrivee, t.nom as transitaire_nom,
           COUNT(cp.id) as nb_proprietaires
    FROM colis c
    JOIN arrivages a ON c.arrivage_id = a.id
    JOIN transitaires t ON a.transitaire_id = t.id
    LEFT JOIN colis_proprietaires cp ON cp.colis_id = c.id
    WHERE c.code_complet = ? OR c.code_reel = ?
    GROUP BY c.id
    LIMIT 1
");
$stmt->execute([$code, $code]);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(['error' => 'Colis introuvable']);
    exit;
}

echo json_encode($row, JSON_UNESCAPED_UNICODE);
