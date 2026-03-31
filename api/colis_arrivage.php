<?php
require_once '../includes/config.php';
requireAuth();
header('Content-Type: application/json; charset=utf-8');

$arrivageId = (int)($_GET['arrivage_id'] ?? 0);
if (!$arrivageId) { echo json_encode([]); exit; }

$db = getDB();
$stmt = $db->prepare("
    SELECT c.id, c.code_complet, c.code_reel, c.type, c.poids,
           t.nom AS transitaire_nom,
           (SELECT COUNT(*) FROM repartitions WHERE colis_id = c.id) AS nb_repartitions
    FROM colis c
    LEFT JOIN transitaires t ON c.transitaire_id = t.id
    WHERE c.arrivage_id = ?
    ORDER BY t.nom, c.code_complet
");
$stmt->execute([$arrivageId]);
echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
