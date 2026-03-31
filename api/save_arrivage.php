<?php
require_once '../includes/config.php';
requireAuth();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Méthode non autorisée'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    jsonResponse(['error' => 'Données JSON invalides'], 400);
}

$date        = trim($input['date'] ?? '');
$transitaires = $input['transitaires'] ?? [];

if (!$date) {
    jsonResponse(['error' => 'La date est obligatoire'], 400);
}

if (empty($transitaires)) {
    jsonResponse(['error' => 'Au moins un transitaire est requis'], 400);
}

foreach ($transitaires as $bloc) {
    if (empty($bloc['transitaire_id'])) {
        jsonResponse(['error' => 'Chaque bloc doit avoir un transitaire sélectionné'], 400);
    }
    if (empty($bloc['colis'])) {
        jsonResponse(['error' => 'Chaque transitaire doit avoir au moins un colis'], 400);
    }
    foreach ($bloc['colis'] as $c) {
        if (empty(trim($c['code'] ?? ''))) {
            jsonResponse(['error' => 'Chaque colis doit avoir un code'], 400);
        }
    }
}

$db = getDB();

try {
    $db->beginTransaction();

    $stmtArr = $db->prepare("INSERT INTO arrivages (date_arrivee, transitaire_id, nb_colis_total, poids_total, cout_total, devise) VALUES (?, NULL, 0, 0, 0, 'FCFA')");
    $stmtArr->execute([$date]);
    $arrivageId = $db->lastInsertId();

    $totalColis = 0;
    $totalPoids = 0;
    $codesInsered = [];

    $stmtColis = $db->prepare("INSERT INTO colis (arrivage_id, transitaire_id, code_reel, code_complet, type, poids, montant) VALUES (?, ?, ?, ?, ?, ?, 0)");

    foreach ($transitaires as $bloc) {
        $transId = (int)$bloc['transitaire_id'];
        foreach ($bloc['colis'] as $c) {
            $code  = strtoupper(trim($c['code']));
            $poids = (float)($c['poids'] ?? 0);
            $type  = in_array($c['type'] ?? '', ['individuel', 'mixte']) ? $c['type'] : 'individuel';
            $codeComplet = genererCodeComplet($code, $date);

            if (in_array($codeComplet, $codesInsered)) {
                $db->rollBack();
                jsonResponse(['error' => "Code colis dupliqué : $codeComplet"], 400);
            }
            $codesInsered[] = $codeComplet;

            $stmtColis->execute([$arrivageId, $transId, $code, $codeComplet, $type, $poids]);
            $totalColis++;
            $totalPoids += $poids;
        }
    }

    $db->prepare("UPDATE arrivages SET nb_colis_total=?, poids_total=? WHERE id=?")
       ->execute([$totalColis, $totalPoids, $arrivageId]);

    $db->commit();

    jsonResponse([
        'success'    => true,
        'arrivage_id' => $arrivageId,
        'nb_colis'   => $totalColis,
        'poids_total' => $totalPoids,
        'message'    => "Arrivage du $date enregistré avec $totalColis colis."
    ]);

} catch (PDOException $e) {
    $db->rollBack();
    $msg = $e->getMessage();
    if (str_contains($msg, 'Duplicate entry')) {
        $msg = 'Un ou plusieurs codes colis existent déjà pour cette date.';
    }
    jsonResponse(['error' => $msg], 500);
}
