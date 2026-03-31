<?php
require_once '../includes/config.php';
requireAuth();
header('Content-Type: application/json; charset=utf-8');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $colisId = (int)($_GET['colis_id'] ?? 0);
    if (!$colisId) { jsonResponse([]); }

    $stmt = $db->prepare("
        SELECT r.*, cl.nom as client_nom, cl.telephone
        FROM repartitions r
        JOIN clients cl ON r.client_id = cl.id
        WHERE r.colis_id = ?
        ORDER BY cl.nom
    ");
    $stmt->execute([$colisId]);
    jsonResponse($stmt->fetchAll());
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) { jsonResponse(['error' => 'JSON invalide'], 400); }

    $colisId      = (int)($input['colis_id'] ?? 0);
    $repartitions = $input['repartitions'] ?? [];

    if (!$colisId) { jsonResponse(['error' => 'colis_id manquant'], 400); }

    $stmtColis = $db->prepare("SELECT * FROM colis WHERE id=?");
    $stmtColis->execute([$colisId]);
    $colis = $stmtColis->fetch();

    if (!$colis) { jsonResponse(['error' => 'Colis introuvable'], 404); }

    if (empty($repartitions)) { jsonResponse(['error' => 'Aucune répartition fournie'], 400); }

    if ($colis['type'] === 'individuel' && count($repartitions) > 1) {
        jsonResponse(['error' => 'Un colis individuel ne peut avoir qu\'un seul client'], 400);
    }

    $totalPoids = (float)$colis['poids'];
    $sumPoids   = 0;
    foreach ($repartitions as $r) {
        $sumPoids += (float)($r['poids'] ?? 0);
    }
    if ($totalPoids > 0 && abs($sumPoids - $totalPoids) > 0.01) {
        jsonResponse(['error' => sprintf(
            'La somme des poids (%.2f kg) doit être égale au poids du colis (%.2f kg)',
            $sumPoids, $totalPoids
        )], 400);
    }

    try {
        $db->beginTransaction();

        $db->prepare("DELETE FROM repartitions WHERE colis_id=?")->execute([$colisId]);

        $stmtIns = $db->prepare("INSERT INTO repartitions (colis_id, client_id, poids, montant, statut) VALUES (?,?,?,?,?)");
        foreach ($repartitions as $r) {
            $clientId = (int)($r['client_id'] ?? 0);
            $poids    = (float)($r['poids'] ?? 0);
            $montant  = (float)($r['montant'] ?? 0);
            $statut   = (int)($r['statut'] ?? 0) ? 1 : 0;
            if (!$clientId) continue;
            $stmtIns->execute([$colisId, $clientId, $poids, $montant, $statut]);
        }

        $db->commit();
        jsonResponse(['success' => true, 'message' => 'Répartition enregistrée.']);

    } catch (PDOException $e) {
        $db->rollBack();
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}

jsonResponse(['error' => 'Méthode non autorisée'], 405);
