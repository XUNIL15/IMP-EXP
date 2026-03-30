<?php
require_once '../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$q = trim($_GET['q'] ?? '');
$db = getDB();

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$stmt = $db->prepare("SELECT id, nom, telephone FROM clients WHERE nom LIKE ? OR telephone LIKE ? ORDER BY nom LIMIT 10");
$stmt->execute(["%$q%", "%$q%"]);
echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
