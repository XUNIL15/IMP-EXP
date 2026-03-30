<?php
require_once '../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$colisId = (int)($_GET['colis_id'] ?? 0);
if (!$colisId) {
    echo json_encode([]);
    exit;
}

$db = getDB();
$stmt = $db->prepare("
    SELECT cp.*, cl.nom as client_nom, cl.telephone
    FROM colis_proprietaires cp
    JOIN clients cl ON cp.client_id = cl.id
    WHERE cp.colis_id = ?
    ORDER BY cl.nom
");
$stmt->execute([$colisId]);
$rows = $stmt->fetchAll();

echo json_encode($rows, JSON_UNESCAPED_UNICODE);
